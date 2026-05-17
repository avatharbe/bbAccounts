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
	'ACL_A_ACCOUNTS'                 => ['lang' => 'bbAccounts: Manage (full access)'],
	'ACL_U_ACCOUNTS_VIEW_AGGREGATES' => ['lang' => 'bbAccounts: View aggregate reports (trial balance, balance lookup)'],
	'ACL_U_ACCOUNTS_VIEW_USERS'      => ['lang' => 'bbAccounts: View per-user data (ledger, statements, profile badge)'],
]);
