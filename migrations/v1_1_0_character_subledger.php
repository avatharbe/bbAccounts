<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\migrations;

/**
 * v1.1.0-alpha — character subledger support.
 *
 * Adds `subledger_player_id` column + index on `bbaccounts_journal_lines`.
 * Together with extended `VALID_SUBLEDGER_TYPES` in ledger.php, this lets
 * consumers (bbDKP) post journal entries keyed by an opaque player ID
 * managed outside bbAccounts (conventionally bb_players.player_id).
 *
 * Source-agnostic: bbAccounts ships no FK and no listener that depends
 * on the external table.
 */
class v1_1_0_character_subledger extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\avathar\\bbaccounts\\migrations\\v1_0_0_schema'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists(
			$this->table_prefix . 'bbaccounts_journal_lines',
			'subledger_player_id'
		);
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'bbaccounts_journal_lines' => [
					'subledger_player_id' => ['UINT', 0],
				],
			],
			'add_index' => [
				$this->table_prefix . 'bbaccounts_journal_lines' => [
					'subledger_player_id' => ['subledger_player_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'bbaccounts_journal_lines' => ['subledger_player_id'],
			],
			'drop_columns' => [
				$this->table_prefix . 'bbaccounts_journal_lines' => ['subledger_player_id'],
			],
		];
	}
}
