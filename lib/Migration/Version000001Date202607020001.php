<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date202607020001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('flz_planer_team_settings')) {
            $table = $schema->createTable('flz_planer_team_settings');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('display_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('settings_json', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['team_code'], 'flz_planer_team_code_unique');
        }

        if (!$schema->hasTable('flz_planer_shift_slots')) {
            $table = $schema->createTable('flz_planer_shift_slots');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('plan_month', Types::STRING, ['notnull' => true, 'length' => 7]);
            $table->addColumn('work_date', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('segment_key', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('starts_at', Types::STRING, ['notnull' => true, 'length' => 5]);
            $table->addColumn('ends_at', Types::STRING, ['notnull' => true, 'length' => 5]);
            $table->addColumn('enabled', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['team_code', 'work_date', 'segment_key'], 'flz_planer_slot_unique');
            $table->addIndex(['team_code', 'plan_month'], 'flz_planer_slot_month');
        }

        if (!$schema->hasTable('flz_planer_shift_candidates')) {
            $table = $schema->createTable('flz_planer_shift_candidates');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('slot_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('assistant_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_by_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['slot_id', 'assistant_uid'], 'flz_planer_slot_user_unique');
            $table->addIndex(['assistant_uid'], 'flz_planer_candidate_user');
        }

        if (!$schema->hasTable('flz_planer_day_notes')) {
            $table = $schema->createTable('flz_planer_day_notes');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('work_date', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('note', Types::TEXT, ['notnull' => false]);
            $table->addColumn('updated_by_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['team_code', 'work_date'], 'flz_planer_day_note_unique');
        }

        return $schema;
    }
}
