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
	'UCP_BBACCOUNTS_WALLET'                => 'Mijn Wallet',
	'UCP_BBACCOUNTS_WALLET_EXPLAIN'        => 'Je huidige saldo per pool. Elke rij is een aparte rekening waarop je activiteit hebt; het eindsaldo is de tot op de minuut bijgewerkte som van elke dagboekboeking die op die rekening op jouw subadministratie is geboekt.',
	'UCP_BBACCOUNTS_WALLET_EMPTY'          => 'Nog geen saldi — je hebt geen subadministratie-activiteit op bbAccounts.',

	'UCP_BBACCOUNTS_STATEMENT'             => 'Mijn overzicht',
	'UCP_BBACCOUNTS_STATEMENT_EXPLAIN'     => 'Elke dagboekregel die op jouw subadministratie is geboekt, nieuwste eerst. Een optioneel datumbereik beperkt het venster; begin- en eindsaldi in de samenvatting respecteren hetzelfde venster.',
	'UCP_BBACCOUNTS_STATEMENT_SUMMARY'     => 'Samenvatting',
	'UCP_BBACCOUNTS_STATEMENT_EMPTY'       => 'Geen transacties in dit bereik.',
	'UCP_BBACCOUNTS_STATEMENT_FILTER'      => 'Filter',
]);
