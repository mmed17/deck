<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create table for D-RASCI-VF stack transition permissions
 */
class Version11100Date20260121180000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('deck_stack_transition_permissions')) {
            $table = $schema->createTable('deck_stack_transition_permissions');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('board_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('from_stack_id', Types::BIGINT, [
                'notnull' => false,
                'length' => 20,
            ]);

            $table->addColumn('to_stack_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('required_role', Types::STRING, [
                'notnull' => true,
                'length' => 1,
            ]);

            $table->addColumn('participant', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);

            $table->addColumn('participant_type', Types::INTEGER, [
                'notnull' => true,
                'length' => 4,
                'default' => 0,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['board_id'], 'deck_stp_board_id');
            $table->addIndex(['from_stack_id', 'to_stack_id'], 'deck_stp_transition');

            return $schema;
        }

        return null;
    }
}