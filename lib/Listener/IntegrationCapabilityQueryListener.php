<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Listener;

use OCA\FlzPlaner\AppInfo\Application;
use OCA\LocalBase\Integration\FlzIntegrationCapabilities;
use OCA\LocalBase\Integration\IntegrationCapabilityQueryEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<IntegrationCapabilityQueryEvent> */
final class IntegrationCapabilityQueryListener implements IEventListener {
    public function handle(Event $event): void {
        if (!$event instanceof IntegrationCapabilityQueryEvent) return;
        $event->provide(Application::APP_ID, [FlzIntegrationCapabilities::ASSISTANT_SCHEDULE_READ]);
    }
}
