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
	'BBACCOUNTS_REQUIRES_PHPBB_33'   => 'bbAccounts vereist phpBB 3.3.0 of nieuwer.',
	'BBACCOUNTS_REQUIRES_PHP_81'     => 'bbAccounts vereist PHP 8.1 of nieuwer.',
	'BBACCOUNTS_REQUIRES_BCMATH'     => 'bbAccounts vereist de PHP <strong>bcmath</strong>-extensie.',

	'BBACCOUNTS_PROFILE_BADGE_HEADING' => 'Wallet',
	'BBACCOUNTS_BALANCE_ABNORMAL'      => 'Abnormaal saldo — deze pool staat in de tegengestelde richting van zijn normale type. Onderzoek dit.',
	'BBACCOUNTS_FE_REPORTS'            => 'bbAccounts Rapporten',
]);
