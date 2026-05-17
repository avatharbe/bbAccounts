<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'  => '\avathar\bbaccounts\acp\main_module',
			'title'     => 'ACP_BBACCOUNTS',
			'modes'     => [
				'currencies' => [
					'title' => 'ACP_BBACCOUNTS_CURRENCIES',
					'auth'  => 'ext_avathar/bbaccounts && acl_a_accounts',
					'cat'   => ['ACP_BBACCOUNTS'],
				],
				'accounts' => [
					'title' => 'ACP_BBACCOUNTS_ACCOUNTS',
					'auth'  => 'ext_avathar/bbaccounts && acl_a_accounts',
					'cat'   => ['ACP_BBACCOUNTS'],
				],
				'journal' => [
					'title' => 'ACP_BBACCOUNTS_JOURNAL',
					'auth'  => 'ext_avathar/bbaccounts && acl_a_accounts',
					'cat'   => ['ACP_BBACCOUNTS'],
				],
				'reports' => [
					'title' => 'ACP_BBACCOUNTS_REPORTS',
					'auth'  => 'ext_avathar/bbaccounts && (acl_a_accounts || acl_u_accounts_view_aggregates || acl_u_accounts_view_users)',
					'cat'   => ['ACP_BBACCOUNTS'],
				],
			],
		];
	}
}
