<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000007Date202608300002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('adp_shift_candidates')) {
            $candidates = $schema->getTable('adp_shift_candidates');
            if (!$candidates->hasColumn('assignment_source')) {
                $candidates->addColumn('assignment_source', Types::STRING, ['length'=>16, 'notnull'=>true, 'default'=>'manual']);
            }
            if (!$candidates->hasColumn('fixed_deleted')) {
                $candidates->addColumn('fixed_deleted', Types::BOOLEAN, ['notnull'=>true, 'default'=>false]);
            }
        }

        if (!$schema->hasTable('adp_regular_shifts')) {
            $rules = $schema->createTable('adp_regular_shifts');
            $rules->addColumn('id', Types::BIGINT, ['autoincrement'=>true, 'notnull'=>true]);
            $rules->addColumn('team_code', Types::STRING, ['length'=>16, 'notnull'=>true]);
            $rules->addColumn('user_uid', Types::STRING, ['length'=>64, 'notnull'=>true]);
            $rules->addColumn('weekday', Types::INTEGER, ['notnull'=>true]);
            $rules->addColumn('segment_key', Types::STRING, ['length'=>32, 'notnull'=>true]);
            $rules->addColumn('updated_at', Types::DATETIME, ['notnull'=>true]);
            $rules->setPrimaryKey(['id']);
            $rules->addUniqueIndex(['team_code','user_uid','weekday','segment_key'], 'adp_regular_shift_unique');
            $rules->addIndex(['team_code','weekday'], 'adp_regular_shift_team_day');
            $rules->addIndex(['user_uid'], 'adp_regular_shift_user');
        }

        if (!$schema->hasTable('adp_fixed_conflicts')) {
            $conflicts = $schema->createTable('adp_fixed_conflicts');
            $conflicts->addColumn('id', Types::BIGINT, ['autoincrement'=>true, 'notnull'=>true]);
            $conflicts->addColumn('slot_id', Types::BIGINT, ['notnull'=>true]);
            $conflicts->addColumn('status', Types::STRING, ['length'=>16, 'notnull'=>true, 'default'=>'escalated']);
            $conflicts->addColumn('reported_by_uid', Types::STRING, ['length'=>64, 'notnull'=>true]);
            $conflicts->addColumn('reported_at', Types::DATETIME, ['notnull'=>true]);
            $conflicts->addColumn('kept_uid', Types::STRING, ['length'=>64, 'notnull'=>false]);
            $conflicts->addColumn('resolved_by_uid', Types::STRING, ['length'=>64, 'notnull'=>false]);
            $conflicts->addColumn('resolved_at', Types::DATETIME, ['notnull'=>false]);
            $conflicts->setPrimaryKey(['id']);
            $conflicts->addUniqueIndex(['slot_id'], 'adp_fixed_conflict_slot_unique');
        }

        return $schema;
    }
}
