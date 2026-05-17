<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\migrations;

/**
 * Every ACP and UCP module the extension registers. Depends on
 * `v1_0_0_config` because the ACP module_auth strings reference the
 * admin/mod permissions that migration adds (`acl_a_accounts`,
 * `acl_u_accounts_view`). UCP modules gate only on `ext_avathar/
 * bbaccounts` — any logged-in user sees their own wallet.
 *
 * The UCP parent category sits at top-level (parent = 0) so
 * "bbAccounts" appears as its own UCP tab alongside Profile /
 * Preferences / PMs, not nested inside the Overview tab.
 *
 * Idempotency is keyed on the UCP wallet module row.
 */
class v1_0_0_modules extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbaccounts\migrations\v1_0_0_config'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT 1 FROM ' . MODULES_TABLE . "
		        WHERE module_class = 'ucp'
		          AND module_basename = '\\\\avathar\\\\bbaccounts\\\\ucp\\\\main_module'
		          AND module_mode = 'wallet'";
		return (bool) $this->db->sql_fetchfield('1', false, $this->db->sql_query_limit($sql, 1));
	}

	public function update_data()
	{
		return [
			// ACP — top-level category sits under phpBB's MODs cluster
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				[
					'module_basename' => '',
					'module_langname' => 'ACP_BBACCOUNTS',
					'module_mode'     => '',
					'module_auth'     => '',
				],
			]],
			['module.add', [
				'acp',
				'ACP_BBACCOUNTS',
				[
					'module_basename' => '\avathar\bbaccounts\acp\main_module',
					'module_langname' => 'ACP_BBACCOUNTS_CURRENCIES',
					'module_mode'     => 'currencies',
					'module_auth'     => 'ext_avathar/bbaccounts && acl_a_accounts',
				],
			]],
			['module.add', [
				'acp',
				'ACP_BBACCOUNTS',
				[
					'module_basename' => '\avathar\bbaccounts\acp\main_module',
					'module_langname' => 'ACP_BBACCOUNTS_ACCOUNTS',
					'module_mode'     => 'accounts',
					'module_auth'     => 'ext_avathar/bbaccounts && acl_a_accounts',
				],
			]],
			['module.add', [
				'acp',
				'ACP_BBACCOUNTS',
				[
					'module_basename' => '\avathar\bbaccounts\acp\main_module',
					'module_langname' => 'ACP_BBACCOUNTS_JOURNAL',
					'module_mode'     => 'journal',
					'module_auth'     => 'ext_avathar/bbaccounts && acl_a_accounts',
				],
			]],
			['module.add', [
				'acp',
				'ACP_BBACCOUNTS',
				[
					'module_basename' => '\avathar\bbaccounts\acp\main_module',
					'module_langname' => 'ACP_BBACCOUNTS_REPORTS',
					'module_mode'     => 'reports',
					'module_auth'     => 'ext_avathar/bbaccounts && (acl_a_accounts || acl_u_accounts_view)',
				],
			]],

			// UCP — top-level tab (parent = 0)
			['module.add', ['ucp', 0, 'UCP_BBACCOUNTS']],
			['module.add', [
				'ucp',
				'UCP_BBACCOUNTS',
				[
					'module_basename' => '\avathar\bbaccounts\ucp\main_module',
					'module_langname' => 'UCP_BBACCOUNTS_WALLET',
					'module_mode'     => 'wallet',
					'module_auth'     => 'ext_avathar/bbaccounts',
				],
			]],
			['module.add', [
				'ucp',
				'UCP_BBACCOUNTS',
				[
					'module_basename' => '\avathar\bbaccounts\ucp\main_module',
					'module_langname' => 'UCP_BBACCOUNTS_STATEMENT',
					'module_mode'     => 'statement',
					'module_auth'     => 'ext_avathar/bbaccounts',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			// UCP — children before parent
			['module.remove', ['ucp', false, 'UCP_BBACCOUNTS_STATEMENT']],
			['module.remove', ['ucp', false, 'UCP_BBACCOUNTS_WALLET']],
			['module.remove', ['ucp', false, 'UCP_BBACCOUNTS']],

			// ACP — children before parent
			['module.remove', ['acp', false, 'ACP_BBACCOUNTS_REPORTS']],
			['module.remove', ['acp', false, 'ACP_BBACCOUNTS_JOURNAL']],
			['module.remove', ['acp', false, 'ACP_BBACCOUNTS_ACCOUNTS']],
			['module.remove', ['acp', false, 'ACP_BBACCOUNTS_CURRENCIES']],
			['module.remove', ['acp', false, 'ACP_BBACCOUNTS']],
		];
	}
}
