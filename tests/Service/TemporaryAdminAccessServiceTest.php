<?php

declare(strict_types=1);

namespace OCP {
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isAdmin(string $uid): bool; public function isInGroup(string $uid, string $gid): bool; }
}

namespace OCP\AppFramework\Utility {
    interface ITimeFactory { public function now(): \DateTimeImmutable; }
}

namespace Psr\Log {
    interface LoggerInterface {
        public function info(string|\Stringable $message, array $context = []): void;
        public function error(string|\Stringable $message, array $context = []): void;
    }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\FlzPlaner\Repository\TemporaryAdminAccessRepositoryInterface;
    use OCA\FlzPlaner\Service\TemporaryAdminAccessDeniedException;
    use OCA\FlzPlaner\Service\TemporaryAdminAccessService;

    $session = new class implements OCP\IUserSession {
        public ?OCP\IUser $user;
        public function __construct() { $this->user = new class implements OCP\IUser { public function getUID(): string { return 'native-admin'; } }; }
        public function getUser(): ?OCP\IUser { return $this->user; }
    };
    $groups = new class implements OCP\IGroupManager {
        public array $admins = ['native-admin', 'admin-target'];
        public array $memberships = ['privacy-officer' => ['Datenschutzbeauftragte']];
        public function isAdmin(string $uid): bool { return in_array($uid, $this->admins, true); }
        public function isInGroup(string $uid, string $gid): bool { return in_array($gid, $this->memberships[$uid] ?? [], true); }
    };
    $repository = new class implements TemporaryAdminAccessRepositoryInterface {
        public array $rows = [];
        public int $mutations = 0;
        public bool $failReads = false;
        public function replaceActive(string $targetUid, string $grantedBy, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array {
            $this->mutations++;
            return $this->rows[] = ['id'=>count($this->rows)+1, 'targetUid'=>$targetUid, 'grantedBy'=>$grantedBy, 'startsAt'=>$startsAt, 'endsAt'=>$endsAt, 'revokedAt'=>null, 'revokedBy'=>null];
        }
        public function revokeActive(string $targetUid, string $revokedBy, DateTimeImmutable $revokedAt): bool {
            foreach ($this->rows as &$row) if ($row['targetUid'] === $targetUid && $row['revokedAt'] === null) { $row['revokedAt']=$revokedAt; $row['revokedBy']=$revokedBy; $this->mutations++; return true; }
            return false;
        }
        public function activeFor(string $targetUid, DateTimeImmutable $at): ?array {
            if ($this->failReads) throw new RuntimeException('storage unavailable');
            foreach (array_reverse($this->rows) as $row) if ($row['targetUid'] === $targetUid && $row['revokedAt'] === null && $row['startsAt'] <= $at && $row['endsAt'] > $at) return $row;
            return null;
        }
        public function history(): array { return array_reverse($this->rows); }
        public function historyForUid(string $uid, int $limit): array { return []; }
    };
    $clock = new class implements OCP\AppFramework\Utility\ITimeFactory { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-09-22T10:00:00Z'); } };
    $logger = new class implements Psr\Log\LoggerInterface {
        public array $messages = [];
        public function info(string|Stringable $message, array $context = []): void { $this->messages[] = ['info', (string)$message]; }
        public function error(string|Stringable $message, array $context = []): void { $this->messages[] = ['error', (string)$message]; }
    };
    $service = new TemporaryAdminAccessService($session, $groups, $repository, $clock, $logger);

    $before = $repository->mutations;
    try { $service->activate('admin-target', 60); throw new RuntimeException('Native Administration durfte ohne Datenschutzrolle freigeben.'); } catch (TemporaryAdminAccessDeniedException) {}
    try { $service->revoke('admin-target'); throw new RuntimeException('Native Administration durfte ohne Datenschutzrolle widerrufen.'); } catch (TemporaryAdminAccessDeniedException) {}
    try { $service->state(); throw new RuntimeException('Native Administration durfte ohne Datenschutzrolle Historie lesen.'); } catch (TemporaryAdminAccessDeniedException) {}
    if ($repository->mutations !== $before) throw new RuntimeException('Abgewiesene Adminanfragen dürfen nichts persistieren.');

    $session->user = new class implements OCP\IUser { public function getUID(): string { return 'privacy-officer'; } };
    if (!$service->canManageGrants() || $service->state()['history'] !== []) throw new RuntimeException('Datenschutzbeauftragte ohne Adminstatus erreichen die Freigabesteuerung nicht.');
    foreach ([['ordinary', 60], ['admin-target', 1441]] as [$target, $duration]) {
        $before = $repository->mutations;
        try { $service->activate($target, $duration); throw new RuntimeException('Manipulierte Freigabe wurde akzeptiert.'); } catch (InvalidArgumentException) {}
        if ($repository->mutations !== $before) throw new RuntimeException('Manipulierte Freigabe darf nichts persistieren.');
    }
    $before = $repository->mutations;
    try { $service->revoke('ordinary'); throw new RuntimeException('Manipulierter Widerruf wurde akzeptiert.'); } catch (InvalidArgumentException) {}
    if ($repository->mutations !== $before) throw new RuntimeException('Manipulierter Widerruf darf nichts persistieren.');

    $grant = $service->activate('admin-target', 1440);
    if ($grant['grantedBy'] !== 'privacy-officer' || $grant['endsAt']->format(DATE_ATOM) !== '2026-09-23T10:00:00+00:00') throw new RuntimeException('Gültige DPO-Freigabe ist nicht auf 24 Stunden begrenzt oder falsch auditiert.');
    if (!$service->hasActiveGrant('admin-target')) throw new RuntimeException('Gültige Adminfreigabe ist nicht aktiv.');
    $groups->admins = ['native-admin'];
    if ($service->hasActiveGrant('admin-target')) throw new RuntimeException('Verlust des nativen Adminstatus muss die Freigabe sofort entziehen.');
    $groups->admins[] = 'admin-target';

    $groups->memberships = [];
    $before = $repository->mutations;
    try { $service->revoke('admin-target'); throw new RuntimeException('Entzogene Datenschutzrolle durfte widerrufen.'); } catch (TemporaryAdminAccessDeniedException) {}
    if ($repository->mutations !== $before) throw new RuntimeException('Abgewiesener Widerruf darf nichts persistieren.');
    $groups->memberships = ['privacy-officer' => ['Datenschutzbeauftragte']];
    if (!$service->revoke('admin-target') || $service->hasActiveGrant('admin-target')) throw new RuntimeException('DPO-Widerruf beendet die Freigabe nicht.');

    $repository->failReads = true;
    if ($service->hasActiveGrant('admin-target')) throw new RuntimeException('Persistenzfehler muss fail-closed bleiben.');
    $repository->failReads = false;

    $session->user = new class implements OCP\IUser { public function getUID(): string { return 'ordinary'; } };
    $before = $repository->mutations;
    try { $service->activate('admin-target', 60); throw new RuntimeException('Gewöhnliches Konto durfte freigeben.'); } catch (TemporaryAdminAccessDeniedException) {}
    try { $service->revoke('admin-target'); throw new RuntimeException('Gewöhnliches Konto durfte widerrufen.'); } catch (TemporaryAdminAccessDeniedException) {}
    try { $service->state(); throw new RuntimeException('Gewöhnliches Konto durfte Historie lesen.'); } catch (TemporaryAdminAccessDeniedException) {}
    if ($repository->mutations !== $before || $service->currentAdminNeedsGrant()) throw new RuntimeException('Gewöhnliches Konto erhielt Adminzustand oder mutierte Historie.');

    $session->user = new class implements OCP\IUser { public function getUID(): string { return 'native-admin'; } };
    if (!$service->currentAdminNeedsGrant() || $service->canManageGrants()) throw new RuntimeException('Nativer Admin ohne Freigabe erhält falschen Eintritts- oder Linkzustand.');
    $groups->memberships['native-admin'] = ['Datenschutzbeauftragte'];
    if (!$service->canManageGrants()) throw new RuntimeException('Admin mit Datenschutzrolle erhält keinen Direktlinkzustand.');

    echo "FlzPlaner temporary admin access service tests passed\n";
}
