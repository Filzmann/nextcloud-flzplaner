<?php

declare(strict_types=1);

namespace OCP {
    interface IDBConnection {
        public function getQueryBuilder();
    }
}

namespace OCP\DB\QueryBuilder {
    interface IQueryBuilder {
        public const PARAM_INT = 'int';
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

    use OCA\AdPlaner\Repository\ShiftPlanRepository;
    use OCP\DB\Exception;
    use OCP\IDBConnection;
    use function OCA\AdPlaner\Tests\assertSameValue;

    final class CandidateResultFake {
        public function __construct(private array|false $row = false) {}

        public function fetchAssociative(): array|false {
            return $this->row;
        }
    }

    final class CandidateExpressionFake {
        public function eq(mixed $left, mixed $right): array {
            return [$left, $right];
        }

        public function orX(mixed ...$conditions): array {
            return $conditions;
        }
    }

    final class CandidateConnectionFake implements IDBConnection {
        public int $insertFailureReason = Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION;
        public int $insertAttempts = 0;
        public int $updateAttempts = 0;
        public array|false $statusRow = false;
        public array $updatedColumns = [];

        public function getQueryBuilder(): CandidateQueryBuilderFake {
            return new CandidateQueryBuilderFake($this);
        }
    }

    final class CandidateQueryBuilderFake {
        private string $operation = 'select';
        private string $table = '';

        public function __construct(private CandidateConnectionFake $connection) {
        }

        public function select(string ...$columns): self { return $this; }
        public function from(string $table): self {
            $this->table = $table;
            return $this;
        }
        public function where(mixed $condition): self { return $this; }
        public function andWhere(mixed $condition): self { return $this; }
        public function setMaxResults(int $limit): self { return $this; }
        public function expr(): CandidateExpressionFake { return new CandidateExpressionFake(); }
        public function createNamedParameter(mixed $value, mixed $type = null): mixed { return $value; }
        public function createFunction(string $expression): string { return $expression; }

        public function insert(string $table): self {
            $this->operation = 'insert';
            return $this;
        }

        public function values(array $values): self { return $this; }
        public function update(string $table): self {
            $this->operation = 'update';
            $this->table = $table;
            return $this;
        }
        public function set(string $column, mixed $value): self {
            $this->connection->updatedColumns[] = $column;
            return $this;
        }
        public function executeQuery(): CandidateResultFake {
            return new CandidateResultFake($this->table === 'adp_month_plans' ? $this->connection->statusRow : false);
        }

        public function executeStatement(): int {
            if ($this->operation === 'update') {
                $this->connection->updateAttempts++;
                return 1;
            }

            $this->connection->insertAttempts++;
            throw new Exception($this->connection->insertFailureReason);
        }
    }

    $connection = new CandidateConnectionFake();
    $repository = new ShiftPlanRepository($connection);
    $repository->addCandidate(7, 'assistant-a', 'test-eb');
    assertSameValue(1, $connection->insertAttempts, 'A concurrent duplicate candidate should require only one insert attempt.');

    $noteConnection = new CandidateConnectionFake();
    $noteRepository = new ShiftPlanRepository($noteConnection);
    $noteRepository->saveDayNote('A1', '2026-08-03', 'Neuester Inhalt', 'test-eb');
    assertSameValue(1, $noteConnection->insertAttempts, 'A first day-note save should attempt one insert.');
    assertSameValue(1, $noteConnection->updateAttempts, 'A concurrent first day-note insert must be recovered with one update.');

    $lockConnection = new CandidateConnectionFake();
    $lockConnection->statusRow = ['status' => 'planned'];
    $lockRepository = new ShiftPlanRepository($lockConnection);
    assertSameValue('planned', $lockRepository->lockMonthStatus('A1', '2026-08', ['draft', 'planned']), 'A matching month status should be locked.');
    assertSameValue(['revision'], $lockConnection->updatedColumns, 'A technical month lock must not persist viewer identity or overwrite the last functional update time.');

    $failingConnection = new CandidateConnectionFake();
    $failingConnection->insertFailureReason = Exception::REASON_DRIVER;
    $failingRepository = new ShiftPlanRepository($failingConnection);
    try {
        $failingRepository->addCandidate(7, 'assistant-a', 'test-eb');
    } catch (Exception $exception) {
        assertSameValue(Exception::REASON_DRIVER, $exception->getReason(), 'A non-unique candidate database failure must propagate unchanged.');
        echo 'AdPlaner shift plan repository tests passed' . PHP_EOL;
        return;
    }

    throw new RuntimeException('A non-unique candidate database failure must not be swallowed.');
}
