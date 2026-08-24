<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000004Date202608090001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('adp_month_plans')) {
            return $schema;
        }

        $table = $schema->getTable('adp_month_plans');
        if ($table->hasColumn('revision')) {
            return $schema;
        }

        $table->addColumn('revision', Types::INTEGER, [
            'notnull' => true,
            'default' => 0,
        ]);

        return $schema;
    }
}
