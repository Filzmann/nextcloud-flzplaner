<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Listener;

use OCA\LocalBase\Service\StandaloneAppNavigationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** @template-implements IEventListener<LoadAdditionalEntriesEvent> */
final class StandaloneNavigationListener implements IEventListener {
    public function __construct(private StandaloneAppNavigationService $navigation, private ?IL10N $l10n = null) {
    }

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent) return;
        $this->navigation->addWhenStandalone('flzplaner', 'Assistenzplanung', 'flzplaner.page.index', 'app.svg', 82);
    }
}
