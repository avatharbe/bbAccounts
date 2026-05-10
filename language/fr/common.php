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
	'BBACCOUNTS_REQUIRES_PHPBB_33'   => 'bbAccounts nécessite phpBB 3.3.0 ou ultérieur.',
	'BBACCOUNTS_REQUIRES_PHP_81'     => 'bbAccounts nécessite PHP 8.1 ou ultérieur.',
	'BBACCOUNTS_REQUIRES_BCMATH'     => 'bbAccounts nécessite l&#8217;extension PHP <strong>bcmath</strong>.',

	'BBACCOUNTS_PROFILE_BADGE_HEADING' => 'Portefeuille',
	'BBACCOUNTS_BALANCE_ABNORMAL'      => 'Solde anormal — ce pool est dans le sens opposé à son type normal. À vérifier.',
	'BBACCOUNTS_FE_REPORTS'            => 'Rapports bbAccounts',
]);
