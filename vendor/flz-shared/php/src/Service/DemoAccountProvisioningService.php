<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Vendor\Filzmann\Shared\Service;

use InvalidArgumentException;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUserManager;
use RuntimeException;

/**
 * Zweck: Erzeugt ausschließlich eindeutig registrierte lokale Demokonten und deren Gruppenmitgliedschaften.
 * Zusammenspiel: App-spezifische Demo-Packs -> DemoAccountProvisioningService -> native Nextcloud-Benutzer- und Gruppenverwaltung.
 * Vertrag: Fremde oder LDAP-verwaltete Konten werden niemals wiederverwendet; alle Konten und bestehenden Gruppen werden vor der ersten Mutation geprüft.
 * Die Registrierung liegt im AppConfig der jeweils owning App (ownerAppId), nicht in einer zentralen Basis-App.
 */
final class DemoAccountProvisioningService {
    private const REGISTRY_KEY = 'demo_account_registry';

    public function __construct(
        private IUserManager $users,
        private IGroupManager $groups,
        private IAppConfig $config,
    ) {}

    /**
     * @param list<array{uid:string,displayName:string,groups:list<string>}> $fixtures
     * @return array{createdUsers:int,reusedUsers:int,createdGroups:int}
     */
    public function provision(string $ownerAppId, array $fixtures): array {
        $ownerAppId = trim($ownerAppId);
        if ($ownerAppId === '') throw new InvalidArgumentException('Die Demo-App-ID fehlt.');

        $fixtures = $this->normalizeFixtures($fixtures);
        $registry = $this->registry($ownerAppId);
        $knownUsers = [];
        $knownGroups = [];

        foreach ($fixtures as $fixture) {
            $user = $this->users->get($fixture['uid']);
            if ($user !== null) {
                $owner = $registry[$fixture['uid']]['ownerAppId'] ?? null;
                $backend = $registry[$fixture['uid']]['backendClass'] ?? null;
                if ($owner !== $ownerAppId) {
                    throw new RuntimeException("Das Konto {$fixture['uid']} ist bereits vorhanden und gehört nicht diesem Demo-Pack. Es wurde nichts verändert.");
                }
                if ($backend !== $user->getBackendClassName()) {
                    throw new RuntimeException("Das registrierte Demokonto {$fixture['uid']} verwendet inzwischen ein anderes Benutzer-Backend. Es wurde nichts verändert.");
                }
                if (!$user->canChangePassword()) {
                    throw new RuntimeException("Das Passwort des registrierten Demokontos {$fixture['uid']} kann im Benutzer-Backend nicht geändert werden. Es wurde nichts verändert.");
                }
                if (!$user->canChangeDisplayName()) {
                    throw new RuntimeException("Das registrierte Demokonto {$fixture['uid']} ist im Benutzer-Backend schreibgeschützt.");
                }
            }
            $knownUsers[$fixture['uid']] = $user;

            foreach ($fixture['groups'] as $groupId) {
                if (array_key_exists($groupId, $knownGroups)) continue;
                $group = $this->groups->get($groupId);
                if ($group !== null && !$group->canAddUser()) {
                    throw new RuntimeException("Die Gruppe {$groupId} ist schreibgeschützt. Das Demo-Pack wurde vor der ersten Änderung abgebrochen.");
                }
                $knownGroups[$groupId] = $group;
            }
        }

        $registry = $this->withoutOrphans($ownerAppId, $registry);

        $createdGroups = 0;
        foreach ($knownGroups as $groupId => $group) {
            if ($group !== null) continue;
            $group = $this->groups->createGroup($groupId);
            if ($group === null || !$group->canAddUser()) {
                throw new RuntimeException("Die lokale Demogruppe {$groupId} konnte nicht schreibbar angelegt werden.");
            }
            $knownGroups[$groupId] = $group;
            $createdGroups++;
        }

        $createdUsers = 0;
        $reusedUsers = 0;
        foreach ($fixtures as $fixture) {
            $user = $knownUsers[$fixture['uid']];
            if ($user === null) {
                $user = $this->users->createUser($fixture['uid'], $fixture['uid']);
                if ($user === null) throw new RuntimeException("Das Demokonto {$fixture['uid']} konnte nicht angelegt werden.");
                $registry[$fixture['uid']] = [
                    'ownerAppId' => $ownerAppId,
                    'backendClass' => $user->getBackendClassName(),
                ];
                $this->saveRegistry($ownerAppId, $registry);
                $createdUsers++;
            } else {
                if (!$user->setPassword($fixture['uid'])) {
                    throw new RuntimeException("Das Passwort des registrierten Demokontos {$fixture['uid']} konnte nicht aktualisiert werden.");
                }
                $reusedUsers++;
            }

            $user->setDisplayName($fixture['displayName']);
            foreach ($fixture['groups'] as $groupId) {
                $group = $knownGroups[$groupId] ?? null;
                if (!$group instanceof IGroup) throw new RuntimeException("Die Demogruppe {$groupId} ist nicht verfügbar.");
                $group->addUser($user);
            }
        }

        return compact('createdUsers', 'reusedUsers', 'createdGroups');
    }

    public function removeRegistryEntry(string $ownerAppId, string $uid): void {
        $registry = $this->registry($ownerAppId);
        if (!array_key_exists($uid, $registry)) return;
        unset($registry[$uid]);
        $this->saveRegistry($ownerAppId, $registry);
    }

    /** @return list<array{uid:string,displayName:string,groups:list<string>}> */
    private function normalizeFixtures(array $fixtures): array {
        $result = [];
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)) throw new InvalidArgumentException('Ein Demokonto ist ungültig.');
            $uid = trim((string)($fixture['uid'] ?? ''));
            $displayName = trim((string)($fixture['displayName'] ?? ''));
            $groups = array_values(array_unique(array_filter(array_map(
                static fn(mixed $group): string => trim((string)$group),
                is_array($fixture['groups'] ?? null) ? $fixture['groups'] : [],
            ))));
            if ($uid === '' || $displayName === '') throw new InvalidArgumentException('UID und Anzeigename eines Demokontos sind erforderlich.');
            $result[] = ['uid' => $uid, 'displayName' => $displayName, 'groups' => $groups];
        }
        return $result;
    }

    /** @return array<string,array{ownerAppId:string,backendClass:string}> */
    private function registry(string $ownerAppId): array {
        $raw = $this->config->getValueString($ownerAppId, self::REGISTRY_KEY, '');
        if ($raw === '') return [];
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            throw new RuntimeException('Die Registrierung der Demokonten ist beschädigt. Es wurde nichts verändert.');
        }
    }

    /** @param array<string,array{ownerAppId:string,backendClass:string}> $registry */
    private function saveRegistry(string $ownerAppId, array $registry): void {
        $this->config->setValueString(
            $ownerAppId,
            self::REGISTRY_KEY,
            json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /** @param array<string,array{ownerAppId:string,backendClass:string}> $registry @return array<string,array{ownerAppId:string,backendClass:string}> */
    private function withoutOrphans(string $ownerAppId, array $registry): array {
        $cleaned = $registry;
        foreach (array_keys($registry) as $uid) {
            if ($this->users->get($uid) === null) unset($cleaned[$uid]);
        }
        if ($cleaned !== $registry) $this->saveRegistry($ownerAppId, $cleaned);
        return $cleaned;
    }
}
