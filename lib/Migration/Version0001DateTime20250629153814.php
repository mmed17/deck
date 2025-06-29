<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\ISchemaWrapper;

class Version0001DateTime20250629153814 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('private_card_notes')) {
            $table = $schema->createTable('private_card_notes');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('card_id', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('content', 'text', [
                'notnull' => true,
                'length' => 65535,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', 'datetime', [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id', 'card_id'], 'private_notes_user_card_index');
        }

        return $schema;
    }
}