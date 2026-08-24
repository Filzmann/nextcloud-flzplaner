<?php

declare(strict_types=1);

namespace OCP {
    interface IDBConnection {
        public function getQueryBuilder();
    }
}

namespace OCP\DB\QueryBuilder {
    interface IQueryBuilder {
        public const PARAM_DATETIME_IMMUTABLE = 'datetime_immutable';
    }
}

namespace OCP\DB {
    class Exception extends \Exception {
        public const REASON_DRIVER = 6;
        public const REASON_UNIQUE_CONSTRAINT_VIOLATION = 14;

        public function __construct(private ?int $reason) {
            parent::__construct('Synthetic database failure');
        }

        public function getReason(): ?int {
            return $this->reason;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Repository\TeamSettingsRepository;
    use OCP\DB\Exception;
    use OCP\IDBConnection;
    use function OCA\AdPlaner\Tests\assertSameValue;

    final class TeamSettingsResultFake {
        public function __construct(private array|false $row) {
        }

        public function fetchAssociative(): array|false {
            return $this->row;
        }
    }

    final class TeamSettingsExpressionFake {
        public function eq(mixed $left, mixed $right): array {
            return [$left, $right];
        }
    }

    final class TeamSettingsConnectionFake implements IDBConnection {
        public array|false $row = false;
        public ?int $insertFailureReason = null;
        public int $insertAttempts = 0;
        public int $updateAttempts = 0;

        public function getQueryBuilder(): TeamSettingsQueryBuilderFake {
            return new TeamSettingsQueryBuilderFake($this);
        }
    }

    final class TeamSettingsQueryBuilderFake {
        private string $operation = 'select';
        private array $values = [];

        public function __construct(private TeamSettingsConnectionFake $connection) {
        }

        public function select(string ...$columns): self {
            $this->operation = 'select';
            return $this;
        }

        public function from(string $table): self {
            return $this;
        }

        public function where(mixed $condition): self {
            return $this;
        }

        public function expr(): TeamSettingsExpressionFake {
            return new TeamSettingsExpressionFake();
        }

        public function createNamedParameter(mixed $value, mixed $type = null): mixed {
            return $value;
        }

        public function insert(string $table): self {
            $this->operation = 'insert';
            return $this;
        }

        public function values(array $values): self {
            $this->values = $values;
            return $this;
        }

        public function update(string $table): self {
            $this->operation = 'update';
            return $this;
        }

        public function set(string $column, mixed $value): self {
            $this->values[$column] = $value;
            return $this;
        }

        public function executeQuery(): TeamSettingsResultFake {
            return new TeamSettingsResultFake($this->connection->row);
        }

        public function executeStatement(): int {
            if ($this->operation === 'insert') {
                $this->connection->insertAttempts++;
                if ($this->connection->insertFailureReason !== null) {
                    $reason = $this->connection->insertFailureReason;
                    if ($reason === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                        $this->connection->row = [
                            'team_code' => $this->values['team_code'],
                            'display_name' => 'Concurrent name',
                            'settings_json' => '{}',
                        ];
                    }
                    throw new Exception($reason);
                }
                $this->connection->row = $this->values;
                return 1;
            }

            $this->connection->updateAttempts++;
            if ($this->connection->row !== false) {
                $this->connection->row = array_merge($this->connection->row, $this->values);
            }
            return 1;
        }
    }

    $connection = new TeamSettingsConnectionFake();
    $connection->insertFailureReason = Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION;
    $repository = new TeamSettingsRepository($connection);
    $repository->save('A1', 'Latest name', ['meetingDay' => 'monday']);

    assertSameValue(1, $connection->insertAttempts, 'A first save should attempt one insert.');
    assertSameValue(1, $connection->updateAttempts, 'A concurrent first insert must be recovered with one update.');
    assertSameValue('Latest name', $connection->row['display_name'] ?? null, 'The successfully completed save must persist its display name.');
    assertSameValue('{"meetingDay":"monday"}', $connection->row['settings_json'] ?? null, 'The successfully completed save must persist its settings.');

    $failingConnection = new TeamSettingsConnectionFake();
    $failingConnection->insertFailureReason = Exception::REASON_DRIVER;
    $failingRepository = new TeamSettingsRepository($failingConnection);
    try {
        $failingRepository->save('A1', 'Name', []);
    } catch (Exception $exception) {
        assertSameValue(Exception::REASON_DRIVER, $exception->getReason(), 'A non-unique database failure must propagate unchanged.');
        assertSameValue(0, $failingConnection->updateAttempts, 'A non-unique database failure must not trigger an update.');
        echo 'AdPlaner team settings repository tests passed' . PHP_EOL;
        return;
    }

    throw new RuntimeException('A non-unique database failure must not be swallowed.');
}
