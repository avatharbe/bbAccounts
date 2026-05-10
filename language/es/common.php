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
	'BBACCOUNTS_REQUIRES_PHPBB_33'   => 'bbAccounts requiere phpBB 3.3.0 o posterior.',
	'BBACCOUNTS_REQUIRES_PHP_81'     => 'bbAccounts requiere PHP 8.1 o posterior.',
	'BBACCOUNTS_REQUIRES_BCMATH'     => 'bbAccounts requiere la extensión <strong>bcmath</strong> de PHP.',

	'BBACCOUNTS_PROFILE_BADGE_HEADING' => 'Cartera',
	'BBACCOUNTS_BALANCE_ABNORMAL'      => 'Saldo anormal — este pool está en sentido contrario a su tipo normal. Investígalo.',
	'BBACCOUNTS_FE_REPORTS'            => 'Informes de bbAccounts',
]);
