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
	'UCP_BBACCOUNTS_WALLET'                => 'Mi cartera',
	'UCP_BBACCOUNTS_WALLET_EXPLAIN'        => 'Tu saldo actual por pool. Cada fila es una cuenta distinta en la que tienes actividad; el saldo final es la suma al minuto de cada asiento del libro diario registrado contra tu subcuenta auxiliar en esa cuenta.',
	'UCP_BBACCOUNTS_WALLET_EMPTY'          => 'Aún no tienes saldos — no tienes actividad de subcuenta auxiliar en bbAccounts.',

	'UCP_BBACCOUNTS_STATEMENT'             => 'Mi extracto',
	'UCP_BBACCOUNTS_STATEMENT_EXPLAIN'     => 'Cada línea del libro diario registrada contra tu subcuenta auxiliar, las más recientes primero. Un rango de fechas opcional acota la ventana; los saldos inicial / final del resumen respetan la misma ventana.',
	'UCP_BBACCOUNTS_STATEMENT_SUMMARY'     => 'Resumen',
	'UCP_BBACCOUNTS_STATEMENT_EMPTY'       => 'No hay transacciones en este rango.',
	'UCP_BBACCOUNTS_STATEMENT_FILTER'      => 'Filtro',
]);
