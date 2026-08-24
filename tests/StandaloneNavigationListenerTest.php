<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { class Event {} interface IEventListener { public function handle(Event $event): void; } }
namespace OCP\Navigation\Events { class LoadAdditionalEntriesEvent extends \OCP\EventDispatcher\Event {} }
namespace OCP { interface IUser {} interface IUserSession { public function getUser(): ?IUser; } interface IURLGenerator { public function linkToRoute(string $routeName, array $arguments = []): string; public function imagePath(string $appName, string $file): string; } interface INavigationManager { public const TYPE_APPS = 'link'; public function add(callable $entry): void; } }
namespace OCP\App { interface IAppManager { public function isEnabledForUser($appId, $user = null); } }

namespace {
    require_once __DIR__ . '/bootstrap.php';
    use OCA\AdPlaner\Listener\StandaloneNavigationListener; use OCA\LocalBase\Service\StandaloneAppNavigationService; use OCP\App\IAppManager; use OCP\INavigationManager; use OCP\IURLGenerator; use OCP\IUser; use OCP\IUserSession; use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
    $user = new class implements IUser {}; $session = new class($user) implements IUserSession { public function __construct(private IUser $user) {} public function getUser(): ?IUser { return $this->user; } }; $apps = new class implements IAppManager { public function isEnabledForUser($appId, $user = null): bool { return false; } }; $nav = new class implements INavigationManager { public array $entries = []; public function add(callable $entry): void { $this->entries[] = $entry; } }; $url = new class implements IURLGenerator { public function linkToRoute(string $routeName, array $arguments = []): string { return $routeName; } public function imagePath(string $appName, string $file): string { return "$appName/$file"; } };
    (new StandaloneNavigationListener(new StandaloneAppNavigationService($session, $apps, $nav, $url)))->handle(new LoadAdditionalEntriesEvent()); $entry = ($nav->entries[0] ?? static fn(): array => [])();
    if (($entry['id'] ?? '') !== 'adplaner' || ($entry['name'] ?? '') !== 'Assistenzplanung' || ($entry['href'] ?? '') !== 'adplaner.page.index') throw new RuntimeException('Standalone-Planernavigation fehlt.');
    echo "AD Planer standalone navigation test passed\n";
}
