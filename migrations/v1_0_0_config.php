<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\migrations;

/**
 * Config keys, permissions, and seed data. Sits between the schema
 * baseline (`v1_0_0_schema`) and the module rows (`v1_0_0_modules`)
 * because:
 *
 * - It needs the tables to exist (so it can seed them).
 * - It defines the permission names that the module rows reference in
 *   their `module_auth` strings.
 *
 * Idempotency is keyed on the `bbaccounts_per_page` config row — it's
 * the canonical "config phase ran" marker. A re-run after partial
 * setup re-applies only the missing pieces (config.add and
 * permission.add are no-ops if the row already exists).
 */
class v1_0_0_config extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbaccounts\migrations\v1_0_0_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['bbaccounts_per_page']);
	}

	public function update_data()
	{
		return [
			['config.add', ['bbaccounts_enable', 1]],
			['config.add', ['bbaccounts_currency_default', 'POINTS']],
			['config.add', ['bbaccounts_per_page', 25]],

			// Custom permissions kept lean: admin perm for write paths,
			// user perm for read access to other users' data (Reports +
			// other-profile balance badge). UCP "My Wallet" is intentionally
			// NOT permission-gated — any logged-in user sees their own
			// data, the extension being enabled is the gate. `u_accounts_view`
			// (rather than `m_accounts_view`) because phpBB strictly maps
			// the perm prefix to a UI tab — `u_*` perms surface in User
			// permissions, where they're easy to grant; `m_*` would be
			// hidden behind per-forum mod scoping.
			['permission.add', ['a_accounts',      true]],
			['permission.add', ['u_accounts_view', true]],
			['permission.permission_set', ['ROLE_ADMIN_FULL', 'a_accounts',      'role']],
			['permission.permission_set', ['ROLE_MOD_FULL',   'u_accounts_view', 'role']],

			['custom', [[$this, 'seed_currencies']]],
			['custom', [[$this, 'seed_chart_of_accounts']]],
		];
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['u_accounts_view']],
			['permission.remove', ['a_accounts']],
			['config.remove', ['bbaccounts_per_page']],
			['config.remove', ['bbaccounts_currency_default']],
			['config.remove', ['bbaccounts_enable']],
		];
	}

	/**
	 * Seed POINTS plus any other currency_code already referenced by an
	 * account row, so the service-layer "currency must be active" guard
	 * has every needed pool on hand the moment the migration finishes.
	 */
	public function seed_currencies()
	{
		$currencies_table = $this->table_prefix . 'bbaccounts_currencies';
		$accounts_table   = $this->table_prefix . 'bbaccounts_accounts';

		$codes = ['POINTS' => 'Forum points'];

		$sql = 'SELECT DISTINCT currency_code FROM ' . $accounts_table;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$code = trim((string) $row['currency_code']);
			if ($code !== '' && !isset($codes[$code]))
			{
				$codes[$code] = $code;
			}
		}
		$this->db->sql_freeresult($result);

		foreach ($codes as $code => $name)
		{
			$sql = 'INSERT INTO ' . $currencies_table . ' ' . $this->db->sql_build_array('INSERT', [
				'currency_code' => $code,
				'currency_name' => $name,
				'is_active'     => 1,
			]);
			$this->db->sql_query($sql);
		}
	}

	/**
	 * Seed the five postable accounts an admin actually touches.
	 * Hierarchical rollup parents (1000 Assets, 2000 Liabilities,
	 * 3000 Equity) are deliberately NOT seeded: they collect totals
	 * only and would let a non-accountant admin accidentally post to a
	 * category instead of a real account. parent_id stays in the
	 * schema so admins can build their own hierarchies later.
	 */
	public function seed_chart_of_accounts()
	{
		$accounts_table = $this->table_prefix . 'bbaccounts_accounts';

		$accounts = [
			['1010', 'Cash on Hand',     'asset',     ''        ],
			['2100', 'User Wallets',     'liability', 'customer'],
			['3010', 'Opening Balances', 'equity',    ''        ],
			['4000', 'Revenue',          'revenue',   ''        ],
			['5000', 'Expenses',         'expense',   ''        ],
		];
		foreach ($accounts as [$code, $name, $type, $subledger])
		{
			$sql = 'INSERT INTO ' . $accounts_table . ' ' . $this->db->sql_build_array('INSERT', [
				'account_code'   => $code,
				'account_name'   => $name,
				'account_type'   => $type,
				'parent_id'      => 0,
				'currency_code'  => 'POINTS',
				'subledger_type' => $subledger,
				'is_active'      => 1,
			]);
			$this->db->sql_query($sql);
		}
	}
}
