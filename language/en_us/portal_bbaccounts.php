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
	'BBACCOUNTS_PORTAL_BALANCE'         => 'My Wallet',
	'BBACCOUNTS_PORTAL_NO_BALANCES'     => 'No balances yet.',
	'BBACCOUNTS_PORTAL_LOGIN_REQUIRED'  => 'Log in to see your wallet.',
]);
