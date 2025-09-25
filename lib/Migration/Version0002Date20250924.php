<?php
declare(strict_types=1);

namespace OCA\TalkRh\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types; // available types helper
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20250924 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('talk_rh_leaves')) {
            $table = $schema->getTable('talk_rh_leaves');
            if (!$table->hasColumn('day_parts')) {
                $table->addColumn('day_parts', Types::TEXT, [
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
