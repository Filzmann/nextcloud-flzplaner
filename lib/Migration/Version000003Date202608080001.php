<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000003Date202608080001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('adp_month_plans')) {
            return $schema;
        }

        $table = $schema->createTable('adp_month_plans');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
        $table->addColumn('plan_month', Types::STRING, ['notnull' => true, 'length' => 7]);
        $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
        $table->addColumn('updated_by_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['team_code', 'plan_month'], 'adp_month_plan_unique');

        return $schema;
    }
}
