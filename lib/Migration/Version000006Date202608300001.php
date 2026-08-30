<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000006Date202608300001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('adp_shift_candidates')) {
            $candidates = $schema->getTable('adp_shift_candidates');
            if (!$candidates->hasColumn('preference')) {
                $candidates->addColumn('preference', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'neutral']);
            }
            if (!$candidates->hasColumn('candidate_note')) {
                $candidates->addColumn('candidate_note', Types::TEXT, ['notnull' => false]);
            }
            if (!$candidates->hasColumn('metadata_updated_at')) {
                $candidates->addColumn('metadata_updated_at', Types::DATETIME, ['notnull' => false]);
            }
        }

        if (!$schema->hasTable('adp_workload_limits')) {
            $limits = $schema->createTable('adp_workload_limits');
            $limits->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $limits->addColumn('team_code', Types::STRING, ['length' => 16, 'notnull' => true]);
            $limits->addColumn('user_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $limits->addColumn('weekly_min', Types::INTEGER, ['notnull' => false]);
            $limits->addColumn('weekly_max', Types::INTEGER, ['notnull' => false]);
            $limits->addColumn('monthly_min', Types::INTEGER, ['notnull' => false]);
            $limits->addColumn('monthly_max', Types::INTEGER, ['notnull' => false]);
            $limits->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $limits->setPrimaryKey(['id']);
            $limits->addUniqueIndex(['team_code', 'user_uid'], 'adp_workload_user_unique');
            $limits->addIndex(['user_uid'], 'adp_workload_user');
        }

        return $schema;
    }
}
