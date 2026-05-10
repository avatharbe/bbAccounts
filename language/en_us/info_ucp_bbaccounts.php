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
	'UCP_BBACCOUNTS_WALLET'                => 'My Wallet',
	'UCP_BBACCOUNTS_WALLET_EXPLAIN'        => 'Your current balance per pool. Each row is a separate account you have activity on; the closing balance is the up-to-the-minute sum of every journal entry posted against your subledger on that account.',
	'UCP_BBACCOUNTS_WALLET_EMPTY'          => 'No balances yet — you don&#8217;t have any subledger activity on bbAccounts.',

	'UCP_BBACCOUNTS_STATEMENT'             => 'My Statement',
	'UCP_BBACCOUNTS_STATEMENT_EXPLAIN'     => 'Every journal line posted against your subledger, newest first. Optional date range narrows the window; opening / closing balances in the summary respect the same window.',
	'UCP_BBACCOUNTS_STATEMENT_SUMMARY'     => 'Summary',
	'UCP_BBACCOUNTS_STATEMENT_EMPTY'       => 'No transactions in this range.',
	'UCP_BBACCOUNTS_STATEMENT_FILTER'      => 'Filter',
]);
