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
    }
}

namespace {
    require_once __DIR__ . '/bootstrap.php';

    use OCA\AdPlaner\Migration\Version000003Date202608080001;
    use OCA\AdPlaner\Migration\Version000004Date202608090001;
    use OCP\DB\ISchemaWrapper;
    use OCP\Migration\IOutput;
    use function OCA\AdPlaner\Tests\assertSameValue;

    final class MigrationOutputFake implements IOutput {
    }

    final class MigrationTableFake {
        public array $columns = [];
        public array $rows = [];
        public array $uniqueIndexes = [];
        public array $primaryKey = [];
        public int $revisionAdditions = 0;

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
    $run(new Version000003Date202608080001(), $freshSchema);
    $run(new Version000004Date202608090001(), $freshSchema);
    $freshTable = $freshSchema->getTable('adp_month_plans');
    assertSameValue(true, $freshTable->hasColumn('status'), 'A fresh migration sequence should create the month status column.');
    assertSameValue(true, $freshTable->hasColumn('revision'), 'A fresh migration sequence should create the revision column.');
    assertSameValue(0, $freshTable->columns['revision']['options']['default'] ?? null, 'A fresh revision column should default to zero.');
    assertSameValue(['team_code', 'plan_month'], $freshTable->uniqueIndexes['adp_month_plan_unique'] ?? null, 'A fresh schema should enforce one status per team and month.');

    $upgradeSchema = new MigrationSchemaFake();
    $upgradeTable = $upgradeSchema->createTable('adp_month_plans');
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
    assertSameValue(false, $missingBaseSchema->hasTable('adp_month_plans'), 'The revision migration must not invent a missing base table out of sequence.');

    echo 'AdPlaner migration execution tests passed' . PHP_EOL;
}
