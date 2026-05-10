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
	'ACL_A_ACCOUNTS'      => ['lang' => 'Kann bbAccounts verwalten (Vollzugriff)'],
	'ACL_U_ACCOUNTS_VIEW' => ['lang' => 'Kann bbAccounts-Berichte einsehen (Salden anderer User)'],
]);
