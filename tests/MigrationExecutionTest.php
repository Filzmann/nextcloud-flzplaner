<?php

declare(strict_types=1);

namespace OCP\Migration {
    abstract class SimpleMigrationStep {
    }

    interface IOutput {
    }
}

namespace OCP\DB {
    interface ISchemaWrapper {
    }

    final class Types {
        public const BIGINT = 'bigint';
        public const INTEGER = 'integer';
        public const STRING = 'string';
        public const DATETIME = 'datetime';
        public const TEXT = 'text';
        public const BOOLEAN = 'boolean';
    }
}

namespace {
    require_once __DIR__ . '/bootstrap.php';

    use OCA\FlzPlaner\Migration\Version000003Date202608080001;
    use OCA\FlzPlaner\Migration\Version000004Date202608090001;
    use OCA\FlzPlaner\Migration\Version000006Date202608300001;
    use OCA\FlzPlaner\Migration\Version000007Date202608300002;
    use OCA\FlzPlaner\Migration\Version000008Date202608300003;
    use OCP\DB\ISchemaWrapper;
    use OCP\Migration\IOutput;
    use function OCA\FlzPlaner\Tests\assertSameValue;

    final class MigrationOutputFake implements IOutput {
    }

    final class MigrationTableFake {
        public array $columns = [];
        public array $rows = [];
        public array $uniqueIndexes = [];
        public array $primaryKey = [];
        public int $revisionAdditions = 0;
        public array $indexes = [];

        public function addColumn(string $name, string $type, array $options): void {
            $this->columns[$name] = compact('type', 'options');
            if ($name === 'revision') {
                $this->revisionAdditions++;
            }
            if (array_key_exists('default', $options)) {
                foreach ($this->rows as &$row) {
                    $row[$name] ??= $options['default'];
                }
                unset($row);
            }
        }

        public function hasColumn(string $name): bool {
            return isset($this->columns[$name]);
        }

        public function setPrimaryKey(array $columns): void {
            $this->primaryKey = $columns;
        }

        public function addUniqueIndex(array $columns, string $name): void {
            $this->uniqueIndexes[$name] = $columns;
        }

        public function addIndex(array $columns, string $name): void { $this->indexes[$name] = $columns; }
    }

    final class MigrationSchemaFake implements ISchemaWrapper {
        /** @var array<string, MigrationTableFake> */
        public array $tables = [];

        public function hasTable(string $name): bool {
            return isset($this->tables[$name]);
        }

        public function createTable(string $name): MigrationTableFake {
            return $this->tables[$name] = new MigrationTableFake();
        }

        public function getTable(string $name): MigrationTableFake {
            return $this->tables[$name];
        }
    }

    $run = static function (object $migration, MigrationSchemaFake $schema): void {
        $result = $migration->changeSchema(
            new MigrationOutputFake(),
            static fn(): MigrationSchemaFake => $schema,
            []
        );
        assertSameValue($schema, $result, 'A schema migration should return the schema it changed.');
    };

    $freshSchema = new MigrationSchemaFake();
    $freshSchema->createTable('flz_planer_shift_candidates');
    $run(new Version000003Date202608080001(), $freshSchema);
    $run(new Version000004Date202608090001(), $freshSchema);
    $run(new Version000006Date202608300001(), $freshSchema);
    $run(new Version000007Date202608300002(), $freshSchema);
    $run(new Version000008Date202608300003(), $freshSchema);
    $freshTable = $freshSchema->getTable('flz_planer_month_plans');
    assertSameValue(true, $freshTable->hasColumn('status'), 'A fresh migration sequence should create the month status column.');
    assertSameValue(true, $freshTable->hasColumn('revision'), 'A fresh migration sequence should create the revision column.');
    assertSameValue(0, $freshTable->columns['revision']['options']['default'] ?? null, 'A fresh revision column should default to zero.');
    assertSameValue(['team_code', 'plan_month'], $freshTable->uniqueIndexes['flz_planer_month_plan_unique'] ?? null, 'A fresh schema should enforce one status per team and month.');
    assertSameValue('neutral', $freshSchema->getTable('flz_planer_shift_candidates')->columns['preference']['options']['default'] ?? null, 'Fresh candidate metadata should start neutral.');
    assertSameValue(['team_code', 'user_uid'], $freshSchema->getTable('flz_planer_workload_limits')->uniqueIndexes['flz_planer_workload_user_unique'] ?? null, 'Fresh limits must be unique per team and user.');
    assertSameValue('manual',$freshSchema->getTable('flz_planer_shift_candidates')->columns['assignment_source']['options']['default'] ?? null,'Fresh and existing candidates must default to manual.');
    assertSameValue(['team_code','user_uid','weekday','segment_key'],$freshSchema->getTable('flz_planer_regular_shifts')->uniqueIndexes['flz_planer_regular_shift_unique'] ?? null,'Regular rules must be unique per team, person, weekday and segment.');
    assertSameValue(['slot_id'],$freshSchema->getTable('flz_planer_fixed_conflicts')->uniqueIndexes['flz_planer_fixed_conflict_slot_unique'] ?? null,'Only one escalation state may exist per slot.');
    assertSameValue(false,$freshSchema->getTable('flz_planer_shift_candidates')->columns['fixed_modified']['options']['default'] ?? null,'Fresh candidates must not start as individually resolved occurrences.');

    $preferenceUpgrade = new MigrationSchemaFake();
    $candidateTable = $preferenceUpgrade->createTable('flz_planer_shift_candidates');
    $candidateTable->rows = [['id'=>1], ['id'=>2]];
    $run(new Version000006Date202608300001(), $preferenceUpgrade);
    assertSameValue([['id'=>1,'preference'=>'neutral'], ['id'=>2,'preference'=>'neutral']], $candidateTable->rows, 'Existing shift wishes must remain neutral during upgrade.');
    $run(new Version000006Date202608300001(), $preferenceUpgrade);
    assertSameValue(3, count($candidateTable->columns), 'Repeating the migration must not duplicate candidate metadata columns.');
    $run(new Version000007Date202608300002(),$preferenceUpgrade);
    assertSameValue('manual',$candidateTable->rows[0]['assignment_source'] ?? null,'Existing candidates must remain manual during upgrade.');
    assertSameValue(false,$candidateTable->rows[0]['fixed_deleted'] ?? null,'Existing candidates must remain visible during upgrade.');
    $run(new Version000008Date202608300003(),$preferenceUpgrade);
    assertSameValue(false,$candidateTable->rows[0]['fixed_modified'] ?? null,'Existing candidates must remain unmodified during override upgrade.');
    $columnCount=count($candidateTable->columns);
    $run(new Version000007Date202608300002(),$preferenceUpgrade);
    assertSameValue($columnCount,count($candidateTable->columns),'Repeating the fixed shift migration must be idempotent.');

    $upgradeSchema = new MigrationSchemaFake();
    $upgradeTable = $upgradeSchema->createTable('flz_planer_month_plans');
    $upgradeTable->addColumn('status', 'string', ['notnull' => true, 'default' => 'draft']);
    $upgradeTable->rows = [
        ['status' => 'planned'],
        ['status' => 'approved'],
    ];
    $run(new Version000004Date202608090001(), $upgradeSchema);
    assertSameValue(
        [
            ['status' => 'planned', 'revision' => 0],
            ['status' => 'approved', 'revision' => 0],
        ],
        $upgradeTable->rows,
        'Existing month statuses should be preserved and receive revision zero during upgrade.'
    );

    $run(new Version000004Date202608090001(), $upgradeSchema);
    assertSameValue(1, $upgradeTable->revisionAdditions, 'Repeating the revision migration must not add the column twice.');

    $missingBaseSchema = new MigrationSchemaFake();
    $run(new Version000004Date202608090001(), $missingBaseSchema);
    assertSameValue(false, $missingBaseSchema->hasTable('flz_planer_month_plans'), 'The revision migration must not invent a missing base table out of sequence.');

    echo 'FlzPlaner migration execution tests passed' . PHP_EOL;
}
