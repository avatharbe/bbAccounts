<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\service;

/**
 * Per-pool balance summary for a user, folded from
 * `ledger::get_subledger_account_balances()` into one row per
 * currency_code (sum of closing balances).
 *
 * Cached per user with a short TTL — a fresh journal entry takes up to
 * TTL seconds to appear, which is the price for keeping hot read paths
 * cheap. Callers that need live numbers must read the ledger directly.
 */
class balance_summary
{
	public const CACHE_TTL = 60;

	/** @var \avathar\bbaccounts\service\ledger */
	protected $ledger;
	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	public function __construct(
		\avathar\bbaccounts\service\ledger $ledger,
		\phpbb\cache\driver\driver_interface $cache
	)
	{
		$this->ledger = $ledger;
		$this->cache = $cache;
	}

	/**
	 * @return array<int, array{currency_code: string, balance: string, is_abnormal: bool}>
	 */
	public function get_pool_balances(int $user_id): array
	{
		if ($user_id <= 0)
		{
			return [];
		}

		$cache_key = '_bbaccounts_pool_bal_' . $user_id;
		$cached = $this->cache->get($cache_key);
		if (is_array($cached))
		{
			return $cached;
		}

		$accounts = $this->ledger->get_subledger_account_balances($user_id);
		$by_pool = [];
		foreach ($accounts as $row)
		{
			$pool = (string) $row['currency_code'];
			$by_pool[$pool] = bcadd($by_pool[$pool] ?? '0.00', (string) $row['closing'], 2);
		}

		$summary = [];
		foreach ($by_pool as $pool => $balance)
		{
			$summary[] = [
				'currency_code' => $pool,
				'balance'       => $balance,
				'is_abnormal'   => bccomp($balance, '0', 2) < 0,
			];
		}
		// Stable ordering by pool code so consumers don't see drift between
		// cache hits and misses.
		usort($summary, static fn ($a, $b) => strcmp($a['currency_code'], $b['currency_code']));

		$this->cache->put($cache_key, $summary, self::CACHE_TTL);
		return $summary;
	}

	/**
	 * Drop the cached summary for a user. Call after posting a journal
	 * entry against the user's subledger when up-to-the-minute accuracy
	 * matters before the next TTL window expires.
	 */
	public function invalidate(int $user_id): void
	{
		if ($user_id > 0)
		{
			$this->cache->destroy('_bbaccounts_pool_bal_' . $user_id);
		}
	}
}
