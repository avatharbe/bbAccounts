<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts;

class ext extends \phpbb\extension\base
{
	/**
	 * @return true|string True on success, or a language key on failure.
	 */
	public function is_enableable()
	{
		if (phpbb_version_compare(PHPBB_VERSION, '3.3.0', '<'))
		{
			return 'BBACCOUNTS_REQUIRES_PHPBB_33';
		}
		if (PHP_VERSION_ID < 80100)
		{
			return 'BBACCOUNTS_REQUIRES_PHP_81';
		}
		if (!extension_loaded('bcmath'))
		{
			return 'BBACCOUNTS_REQUIRES_BCMATH';
		}
		return true;
	}
}
