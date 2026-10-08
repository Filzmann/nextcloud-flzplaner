<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000008Date202608300003 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('flz_planer_shift_candidates')) {
            $candidates = $schema->getTable('flz_planer_shift_candidates');
            if (!$candidates->hasColumn('fixed_modified')) {
                $candidates->addColumn('fixed_modified', Types::BOOLEAN, [
                    'notnull' => true,
                    'default' => false,
                ]);
            }
        }

        return $schema;
    }
}
