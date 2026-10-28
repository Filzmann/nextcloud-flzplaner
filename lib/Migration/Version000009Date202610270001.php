<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive Zuordnung jedes konfigurierten Assistenzteams zu genau einem Bürobereich. */
final class Version000009Date202610270001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if ($schema->hasTable('flz_planer_team_settings')) {
            $table = $schema->getTable('flz_planer_team_settings');
            if (!$table->hasColumn('bureau_key')) $table->addColumn('bureau_key', Types::STRING, ['length' => 64, 'notnull' => false]);
            if (!$table->hasIndex('flz_planer_team_bureau')) $table->addIndex(['bureau_key'], 'flz_planer_team_bureau');
        }
        return $schema;
    }
}
