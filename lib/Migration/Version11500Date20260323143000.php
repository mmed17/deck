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

class Version11500Date20260323143000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_board_policy_settings')) {
			$table = $schema->getTable('deck_board_policy_settings');
			if (!$table->hasColumn('done_stack_id')) {
				$table->addColumn('done_stack_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
			}
		}

		return $schema;
	}
}

