<?php
declare(strict_types=1);

namespace OCA\TalkRh\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types; // available types helper
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20250919 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('talk_rh_leaves')) {
            $table = $schema->createTable('talk_rh_leaves');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('uid', Types::STRING, [
                'length' => 64,
                'notnull' => true,
            ]);
            $table->addColumn('start_date', Types::STRING, [
                'length' => 20,
                'notnull' => true,
            ]);
            $table->addColumn('end_date', Types::STRING, [
                'length' => 20,
                'notnull' => true,
            ]);
            $table->addColumn('type', Types::STRING, [
                'length' => 32,
                'notnull' => true,
                'default' => 'paid',
            ]);
            $table->addColumn('status', Types::STRING, [
                'length' => 16,
                'notnull' => true,
                'default' => 'pending',
            ]);
            $table->addColumn('reason', Types::TEXT, [
                'notnull' => true,
                'default' => '',
            ]);
            $table->addColumn('admin_comment', Types::TEXT, [
                'notnull' => true,
                'default' => '',
            ]);
            $table->addColumn('created_at', Types::STRING, [
                'length' => 32,
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::STRING, [
                'length' => 32,
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['uid'], 'talk_rh_leaves_uid_idx');
        }

        return $schema;
    }
}
