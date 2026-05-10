<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'BBACCOUNTS_REQUIRES_PHPBB_33'   => 'bbAccounts requires phpBB 3.3.0 or later.',
	'BBACCOUNTS_REQUIRES_PHP_81'     => 'bbAccounts requires PHP 8.1 or later.',
	'BBACCOUNTS_REQUIRES_BCMATH'     => 'bbAccounts requires the PHP <strong>bcmath</strong> extension.',

	'BBACCOUNTS_PROFILE_BADGE_HEADING' => 'Wallet',
	'BBACCOUNTS_BALANCE_ABNORMAL'      => 'Abnormal balance — this pool is in the opposite direction from its normal type. Investigate.',
	'BBACCOUNTS_FE_REPORTS'            => 'bbAccounts Reports',
]);
