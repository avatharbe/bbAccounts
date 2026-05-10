<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\migrations;

/**
 * Database schema only — every table the extension owns. Data, config,
 * permissions, and modules live in the sibling migrations
 * (`v1_3_0_config`, `v1_3_0_modules`). Splitting by type makes each
 * file purposeful and keeps DDL diffs reviewable.
 *
 * `effectively_installed` keys on `bbaccounts_journal` because the
 * journal table is the heart of the schema; if it exists the schema
 * is considered set up. The currencies table is created in the same
 * migration here (it was a separate Phase-1.5 add originally; the
 * three-way split rolls it back into the schema baseline).
 */
class v1_3_0_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'bbaccounts_journal');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'bbaccounts_currencies' => [
					'COLUMNS' => [
						'currency_code' => ['VCHAR:8',  ''],
						'currency_name' => ['VCHAR:64', ''],
						'is_active'     => ['BOOL',     1],
					],
					'PRIMARY_KEY' => 'currency_code',
				],
				$this->table_prefix . 'bbaccounts_accounts' => [
					'COLUMNS' => [
						'account_id'     => ['UINT', null, 'auto_increment'],
						'account_code'   => ['VCHAR:20', ''],
						'account_name'   => ['VCHAR:100', ''],
						'account_type'   => ['VCHAR:16', ''],
						'parent_id'      => ['UINT', 0],
						'currency_code'  => ['VCHAR:8', 'POINTS'],
						'subledger_type' => ['VCHAR:16', ''],
						'is_active'      => ['BOOL', 1],
					],
					'PRIMARY_KEY' => 'account_id',
					'KEYS' => [
						'account_code'   => ['UNIQUE', 'account_code'],
						'parent_id'      => ['INDEX', 'parent_id'],
						'currency_code'  => ['INDEX', 'currency_code'],
					],
				],
				$this->table_prefix . 'bbaccounts_journal' => [
					'COLUMNS' => [
						'journal_id'        => ['UINT', null, 'auto_increment'],
						'entry_date'        => ['UINT:11', 0],
						'description'       => ['VCHAR:255', ''],
						'reference_type'    => ['VCHAR:32', 'manual'],
						'reference_source'  => ['VCHAR:64', ''],
						'reference_id'      => ['UINT', 0],
						'created_by'        => ['UINT', 0],
						'created_at'        => ['UINT:11', 0],
						'reversal_of'       => ['UINT', 0],
					],
					'PRIMARY_KEY' => 'journal_id',
					'KEYS' => [
						'reversal_of'    => ['INDEX', 'reversal_of'],
						'ref_lookup'     => ['INDEX', ['reference_source', 'reference_id']],
						'entry_date'     => ['INDEX', 'entry_date'],
					],
				],
				$this->table_prefix . 'bbaccounts_journal_lines' => [
					'COLUMNS' => [
						'line_id'           => ['UINT', null, 'auto_increment'],
						'journal_id'        => ['UINT', 0],
						'account_id'        => ['UINT', 0],
						'debit'             => ['DECIMAL:20,2', '0.00'],
						'credit'            => ['DECIMAL:20,2', '0.00'],
						'subledger_user_id' => ['UINT', 0],
						'memo'              => ['VCHAR:255', ''],
					],
					'PRIMARY_KEY' => 'line_id',
					'KEYS' => [
						'journal_id'        => ['INDEX', 'journal_id'],
						'account_id'        => ['INDEX', 'account_id'],
						'subledger_user_id' => ['INDEX', 'subledger_user_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'bbaccounts_journal_lines',
				$this->table_prefix . 'bbaccounts_journal',
				$this->table_prefix . 'bbaccounts_accounts',
				$this->table_prefix . 'bbaccounts_currencies',
			],
		];
	}
}
