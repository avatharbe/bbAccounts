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
	'BBACCOUNTS_REQUIRES_PHPBB_33'   => 'bbAccounts benötigt phpBB 3.3.0 oder neuer.',
	'BBACCOUNTS_REQUIRES_PHP_81'     => 'bbAccounts benötigt PHP 8.1 oder neuer.',
	'BBACCOUNTS_REQUIRES_BCMATH'     => 'bbAccounts benötigt die PHP-Erweiterung <strong>bcmath</strong>.',

	'BBACCOUNTS_PROFILE_BADGE_HEADING' => 'Wallet',
	'BBACCOUNTS_BALANCE_ABNORMAL'      => 'Ungewöhnlicher Saldo — dieser Pool steht entgegen seiner normalen Saldenrichtung. Bitte prüfen.',
	'BBACCOUNTS_FE_REPORTS'            => 'bbAccounts Berichte',
]);
