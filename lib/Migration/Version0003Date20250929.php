<?php
declare(strict_types=1);

namespace OCA\TalkRh\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types; // available types helper
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20250929 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('talk_rh_leaves')) {
            $table = $schema->getTable('talk_rh_leaves');
            if (!$table->hasColumn('calendar_object_uri')) {
                $table->addColumn('calendar_object_uri', Types::STRING, [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
            if (!$table->hasColumn('calendar_component_uid')) {
                $table->addColumn('calendar_component_uid', Types::STRING, [
                    'length' => 255,
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
