<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Privacy;

use DomainException;
use InvalidArgumentException;
use JsonException;
use OCA\FlzPlaner\AppInfo\Application;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataCatalog;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProvider;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProviderDescriptor;
use OCP\IL10N;

final class PlanerProcessingMetadataProvider implements ProcessingMetadataProvider {
    public function __construct(private ?IL10N $l10n = null) {}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }
    public function descriptor(): ProcessingMetadataProviderDescriptor {
        return new ProcessingMetadataProviderDescriptor(Application::APP_ID, $this->t('Assistenz Dienstplanung'), '1.0');
    }

    public function catalog(): ProcessingMetadataCatalog {
        $path = dirname(__DIR__, 2) . '/resources/privacy-processing.json';
        if (!is_file($path)) {
            throw new DomainException('Der Verarbeitungsmetadaten-Katalog ist nicht verfügbar.');
        }

        try {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new DomainException('Der Verarbeitungsmetadaten-Katalog ist nicht verfügbar.');
            }
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new DomainException('Der Verarbeitungsmetadaten-Katalog ist ungültig.');
            }
            return ProcessingMetadataCatalog::fromArray($payload);
        } catch (JsonException|InvalidArgumentException) {
            throw new DomainException('Der Verarbeitungsmetadaten-Katalog ist ungültig.');
        }
    }
}
