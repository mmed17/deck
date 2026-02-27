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
 * Card-based permission model (board roles + per-card policies)
 */
class Version11300Date20260226120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_board_policy_settings')) {
			$table = $schema->createTable('deck_board_policy_settings');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('board_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('permission_mode', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'legacy',
			]);
			$table->addColumn('approved_stack_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['board_id'], 'deck_bps_board_id');
		}

		if (!$schema->hasTable('deck_board_policy_roles')) {
			$table = $schema->createTable('deck_board_policy_roles');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('board_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('role_key', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('color', Types::STRING, [
				'notnull' => true,
				'length' => 7,
				'default' => '#000000',
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_bpr_board_id');
			$table->addUniqueIndex(['board_id', 'role_key'], 'deck_bpr_board_role_key');
		}

		if (!$schema->hasTable('deck_board_policy_role_memberships')) {
			$table = $schema->createTable('deck_board_policy_role_memberships');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('board_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('role_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
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
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_bprm_board_id');
			$table->addIndex(['role_id'], 'deck_bprm_role_id');
			$table->addUniqueIndex(['board_id', 'role_id', 'participant_type', 'participant'], 'deck_bprm_unique');
		}

		if (!$schema->hasTable('deck_board_policy_default_roles')) {
			$table = $schema->createTable('deck_board_policy_default_roles');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('board_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('action', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('role_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_bpdr_board_id');
			$table->addIndex(['role_id'], 'deck_bpdr_role_id');
			$table->addUniqueIndex(['board_id', 'action', 'role_id'], 'deck_bpdr_unique');
		}

		if (!$schema->hasTable('deck_card_policy')) {
			$table = $schema->createTable('deck_card_policy');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('board_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('card_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_cp_board_id');
			$table->addUniqueIndex(['board_id', 'card_id'], 'deck_cp_board_card');
		}

		if (!$schema->hasTable('deck_card_policy_roles')) {
			$table = $schema->createTable('deck_card_policy_roles');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('policy_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('action', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('role_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['policy_id'], 'deck_cpr_policy_id');
			$table->addIndex(['role_id'], 'deck_cpr_role_id');
			$table->addUniqueIndex(['policy_id', 'action', 'role_id'], 'deck_cpr_unique');
		}

		return $schema;
	}
}
