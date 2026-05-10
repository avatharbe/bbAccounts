<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\ucp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\avathar\bbaccounts\ucp\main_module',
			'title'    => 'UCP_BBACCOUNTS',
			'modes'    => [
				'wallet' => [
					'title' => 'UCP_BBACCOUNTS_WALLET',
					'auth'  => 'ext_avathar/bbaccounts',
					'cat'   => ['UCP_BBACCOUNTS'],
				],
				'statement' => [
					'title' => 'UCP_BBACCOUNTS_STATEMENT',
					'auth'  => 'ext_avathar/bbaccounts',
					'cat'   => ['UCP_BBACCOUNTS'],
				],
			],
		];
	}
}
