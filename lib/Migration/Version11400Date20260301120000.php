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

class Version11400Date20260301120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_perm_tpl')) {
			$table = $schema->createTable('deck_perm_tpl');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);

			$table->addColumn('organization_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);

			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);

			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->addColumn('template_json', Types::TEXT, [
				'notnull' => true,
			]);

			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['organization_id'], 'deck_perm_tpl_org');
			$table->addIndex(['created_by'], 'deck_perm_tpl_created_by');
			$table->addUniqueIndex(['organization_id', 'name'], 'deck_perm_tpl_org_name');
		}

		return $schema;
	}
}
