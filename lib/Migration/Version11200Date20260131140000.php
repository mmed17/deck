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
 * Create tables for D-RASCI-VF Role Profiles (Organization-level templates)
 */
class Version11200Date20260131140000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Create deck_role_profiles table
        if (!$schema->hasTable('deck_role_profiles')) {
            $table = $schema->createTable('deck_role_profiles');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);

            $table->addColumn('organization_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('owner_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);

            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['organization_id'], 'deck_rp_org_id');
        }

        // Create deck_role_profile_permissions table
        if (!$schema->hasTable('deck_role_profile_permissions')) {
            $table = $schema->createTable('deck_role_profile_permissions');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('profile_id', Types::BIGINT, [
                'notnull' => true,
                'length' => 20,
            ]);

            $table->addColumn('from_stack_name', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);

            $table->addColumn('to_stack_name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
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
            $table->addIndex(['profile_id'], 'deck_rpp_profile_id');
        }

        return $schema;
    }
}
