<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Zweck: Entfernt die durch Filzmann Urlaubsplanung ersetzte parallele Urlaubstabelle. */
final class Version000002Date202607130001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('flz_planer_vacation_requests')) $schema->dropTable('flz_planer_vacation_requests');
        return $schema;
    }
}
