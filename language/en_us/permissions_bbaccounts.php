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
	'ACL_A_ACCOUNTS'      => ['lang' => 'Can manage bbAccounts (full access)'],
	'ACL_U_ACCOUNTS_VIEW' => ['lang' => 'Can view bbAccounts reports (other users&#8217; balances)'],
]);
