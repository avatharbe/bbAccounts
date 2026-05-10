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
	'UCP_BBACCOUNTS'                       => 'bbAccounts',
	'UCP_BBACCOUNTS_WALLET'                => 'Mon portefeuille',
	'UCP_BBACCOUNTS_WALLET_EXPLAIN'        => 'Ton solde actuel par pool. Chaque ligne correspond à un compte distinct sur lequel tu as de l&#8217;activité ; le solde de clôture est la somme à la minute près de toutes les écritures comptables passées sur ton compte auxiliaire.',
	'UCP_BBACCOUNTS_WALLET_EMPTY'          => 'Aucun solde pour le moment — tu n&#8217;as aucune activité de compte auxiliaire sur bbAccounts.',

	'UCP_BBACCOUNTS_STATEMENT'             => 'Mon relevé',
	'UCP_BBACCOUNTS_STATEMENT_EXPLAIN'     => 'Toutes les lignes d&#8217;écriture passées sur ton compte auxiliaire, des plus récentes aux plus anciennes. Une plage de dates optionnelle restreint la fenêtre ; les soldes d&#8217;ouverture et de clôture du résumé respectent la même fenêtre.',
	'UCP_BBACCOUNTS_STATEMENT_SUMMARY'     => 'Résumé',
	'UCP_BBACCOUNTS_STATEMENT_EMPTY'       => 'Aucune transaction sur cette plage.',
	'UCP_BBACCOUNTS_STATEMENT_FILTER'      => 'Filtrer',
]);
