<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Privacy;

use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class PlanerProcessingMetadataProviderListener implements IEventListener {
    public function __construct(private PlanerProcessingMetadataProvider $provider) {}

    public function handle(Event $event): void {
        if ($event instanceof RegisterProcessingMetadataProvidersEvent) {
            $event->register($this->provider);
        }
    }
}
