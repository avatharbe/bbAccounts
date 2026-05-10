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
	'UCP_BBACCOUNTS_WALLET'                => 'Mein Wallet',
	'UCP_BBACCOUNTS_WALLET_EXPLAIN'        => 'Dein aktueller Saldo pro Pool. Jede Zeile ist ein eigenes Konto, auf dem du Aktivität hast; der Endsaldo ist die minutengenaue Summe aller Journalbuchungen, die auf deinen Subledger auf diesem Konto gebucht wurden.',
	'UCP_BBACCOUNTS_WALLET_EMPTY'          => 'Noch keine Salden — du hast keine Subledger-Aktivität in bbAccounts.',

	'UCP_BBACCOUNTS_STATEMENT'             => 'Mein Auszug',
	'UCP_BBACCOUNTS_STATEMENT_EXPLAIN'     => 'Jede Journalzeile, die auf deinen Subledger gebucht wurde, neueste zuerst. Ein optionaler Zeitraum grenzt das Fenster ein; Anfangs- und Endsaldo in der Zusammenfassung beziehen sich auf denselben Zeitraum.',
	'UCP_BBACCOUNTS_STATEMENT_SUMMARY'     => 'Zusammenfassung',
	'UCP_BBACCOUNTS_STATEMENT_EMPTY'       => 'Keine Buchungen in diesem Zeitraum.',
	'UCP_BBACCOUNTS_STATEMENT_FILTER'      => 'Filter',
]);
