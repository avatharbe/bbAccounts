<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\service;

class ledger
{
	public const VALID_REFERENCE_TYPES = ['manual', 'auto', 'import'];
	public const VALID_ACCOUNT_TYPES   = ['asset', 'liability', 'equity', 'revenue', 'expense'];
	public const VALID_SUBLEDGER_TYPES = ['', 'customer', 'supplier'];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $accounts_table;

	/** @var string */
	protected $journal_table;

	/** @var string */
	protected $lines_table;

	/** @var string */
	protected $currencies_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		string $accounts_table,
		string $journal_table,
		string $lines_table,
		string $currencies_table = ''
	)
	{
		$this->db = $db;
		$this->accounts_table = $accounts_table;
		$this->journal_table = $journal_table;
		$this->lines_table = $lines_table;
		$this->currencies_table = $currencies_table;
	}

	/**
	 * Programmatic chart-of-accounts seed for consumer extensions.
	 *
	 * Consumers (ultimatepoints, bbDKP, …) call this from their install
	 * migrations instead of writing directly into bbaccounts_accounts —
	 * the table layout is not part of the public contract (see
	 * contrib/events.md §1.2). Every validation here is the same set the
	 * ACP "Add account" form enforces, just available without the HTTP
	 * round-trip.
	 *
	 * @throws \InvalidArgumentException on any validation failure.
	 * @return int new account_id
	 */
	public function create_account(
		string $account_code,
		string $account_name,
		string $account_type,
		string $currency_code = 'POINTS',
		int $parent_id = 0,
		string $subledger_type = '',
		bool $is_active = true
	): int
	{
		$account_code = trim($account_code);
		$account_name = trim($account_name);

		if ($account_code === '')
		{
			throw new \InvalidArgumentException('account_code is required.');
		}
		if (mb_strlen($account_code) > 20)
		{
			throw new \InvalidArgumentException('account_code exceeds the 20-character limit.');
		}
		if ($account_name === '')
		{
			throw new \InvalidArgumentException('account_name is required.');
		}
		if (!in_array($account_type, self::VALID_ACCOUNT_TYPES, true))
		{
			throw new \InvalidArgumentException(
				'account_type must be one of: ' . implode(', ', self::VALID_ACCOUNT_TYPES) . '.'
			);
		}
		if (!in_array($subledger_type, self::VALID_SUBLEDGER_TYPES, true))
		{
			throw new \InvalidArgumentException(
				"subledger_type must be 'customer', 'supplier', or empty."
			);
		}
		if (!$this->currency_is_active($currency_code))
		{
			throw new \InvalidArgumentException(
				"currency_code '{$currency_code}' is not a known active currency."
			);
		}
		if ($parent_id !== 0 && $this->load_account($parent_id) === null)
		{
			throw new \InvalidArgumentException(
				"parent_id {$parent_id} does not reference an existing account."
			);
		}

		// Pre-check duplicate code so consumers get a clean exception
		// rather than a DBAL UNIQUE-constraint error message.
		$sql = 'SELECT account_id FROM ' . $this->accounts_table
			 . " WHERE account_code = '" . $this->db->sql_escape($account_code) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = $this->db->sql_fetchrow($result) !== false;
		$this->db->sql_freeresult($result);
		if ($exists)
		{
			throw new \InvalidArgumentException(
				"account_code '{$account_code}' is already in use."
			);
		}

		$sql = 'INSERT INTO ' . $this->accounts_table . ' ' . $this->db->sql_build_array('INSERT', [
			'account_code'   => $account_code,
			'account_name'   => $account_name,
			'account_type'   => $account_type,
			'parent_id'      => $parent_id,
			'currency_code'  => $currency_code,
			'subledger_type' => $subledger_type,
			'is_active'      => $is_active ? 1 : 0,
		]);
		$this->db->sql_query($sql);

		return (int) $this->db->sql_nextid();
	}

	/**
	 * Read-side enumeration for consumer extensions populating UI
	 * pickers (e.g. UltimatePoints' bbAccounts mapping ACP page).
	 * Returns inactive accounts as well — admins may need to see them
	 * for re-mapping or historic-balance inspection.
	 *
	 * @param string      $account_type   '' = no filter; otherwise must be in VALID_ACCOUNT_TYPES.
	 * @param string|null $subledger_type null = no filter. '' = match accounts with no subledger.
	 *                                    'customer' / 'supplier' = match that subledger.
	 * @throws \InvalidArgumentException on invalid filter values.
	 * @return array<int, array<string, string|int>> ordered by account_code.
	 */
	public function list_accounts(string $account_type = '', ?string $subledger_type = null): array
	{
		if ($account_type !== '' && !in_array($account_type, self::VALID_ACCOUNT_TYPES, true))
		{
			throw new \InvalidArgumentException(
				'account_type must be one of: ' . implode(', ', self::VALID_ACCOUNT_TYPES) . ' or empty.'
			);
		}
		if ($subledger_type !== null && !in_array($subledger_type, self::VALID_SUBLEDGER_TYPES, true))
		{
			throw new \InvalidArgumentException(
				"subledger_type must be 'customer', 'supplier', empty string, or null."
			);
		}

		$where = [];
		if ($account_type !== '')
		{
			$where[] = "account_type = '" . $this->db->sql_escape($account_type) . "'";
		}
		if ($subledger_type !== null)
		{
			$where[] = "subledger_type = '" . $this->db->sql_escape($subledger_type) . "'";
		}

		$sql = 'SELECT account_id, account_code, account_name, account_type,
		               currency_code, subledger_type, is_active, parent_id
		        FROM ' . $this->accounts_table;
		if (!empty($where))
		{
			$sql .= ' WHERE ' . implode(' AND ', $where);
		}
		$sql .= ' ORDER BY account_code';

		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function create_entry(
		int $entry_date,
		string $description,
		array $lines,
		string $reference_type = 'manual',
		int $reference_id = 0,
		string $reference_source = '',
		int $created_by = 0
	): int
	{
		$this->validate_reference_type($reference_type);
		$this->validate_lines($lines);

		$now = time();
		$sql = 'INSERT INTO ' . $this->journal_table . ' ' . $this->db->sql_build_array('INSERT', [
			'entry_date'        => $entry_date,
			'description'       => $description,
			'reference_type'    => $reference_type,
			'reference_source'  => $reference_source,
			'reference_id'      => $reference_id,
			'created_by'        => $created_by,
			'created_at'        => $now,
			'reversal_of'       => 0,
		]);
		$this->db->sql_query($sql);
		$journal_id = (int) $this->db->sql_nextid();

		foreach ($lines as $line)
		{
			$sql = 'INSERT INTO ' . $this->lines_table . ' ' . $this->db->sql_build_array('INSERT', [
				'journal_id'        => $journal_id,
				'account_id'        => (int) $line['account_id'],
				'debit'             => (string) $line['debit'],
				'credit'            => (string) $line['credit'],
				'subledger_user_id' => (int) ($line['subledger_user_id'] ?? 0),
				'memo'              => (string) ($line['memo'] ?? ''),
			]);
			$this->db->sql_query($sql);
		}

		return $journal_id;
	}

	public function reverse_entry(int $journal_id, string $description = '', int $created_by = 0): int
	{
		$sql = 'SELECT entry_date, description, reversal_of FROM ' . $this->journal_table . ' WHERE journal_id = ' . $journal_id;
		$orig = $this->db->sql_fetchrow($this->db->sql_query($sql));
		if (!$orig)
		{
			throw new \InvalidArgumentException("Journal entry {$journal_id} not found.");
		}
		if ((int) $orig['reversal_of'] !== 0)
		{
			throw new \LogicException("Cannot create a reversal of a reversal (#{$journal_id}).");
		}

		$sql = 'SELECT account_id, debit, credit, subledger_user_id, memo
                FROM ' . $this->lines_table . '
                WHERE journal_id = ' . $journal_id . '
                ORDER BY line_id';
		$result = $this->db->sql_query($sql);
		$orig_lines = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$orig_lines[] = $row;
		}
		$this->db->sql_freeresult($result);

		$mirror = [];
		foreach ($orig_lines as $line)
		{
			$mirror[] = [
				'account_id'        => (int) $line['account_id'],
				'debit'             => $line['credit'],
				'credit'            => $line['debit'],
				'subledger_user_id' => (int) $line['subledger_user_id'],
				'memo'              => $line['memo'],
			];
		}

		$now = time();
		$sql = 'INSERT INTO ' . $this->journal_table . ' ' . $this->db->sql_build_array('INSERT', [
			'entry_date'        => (int) $orig['entry_date'],
			'description'       => $description !== '' ? $description : 'Reversal of #' . $journal_id,
			'reference_type'    => 'auto',
			'reference_source'  => '',
			'reference_id'      => 0,
			'created_by'        => $created_by,
			'created_at'        => $now,
			'reversal_of'       => $journal_id,
		]);
		$this->db->sql_query($sql);
		$reverse_id = (int) $this->db->sql_nextid();

		foreach ($mirror as $line)
		{
			$sql = 'INSERT INTO ' . $this->lines_table . ' ' . $this->db->sql_build_array('INSERT', [
				'journal_id'        => $reverse_id,
				'account_id'        => $line['account_id'],
				'debit'             => $line['debit'],
				'credit'            => $line['credit'],
				'subledger_user_id' => $line['subledger_user_id'],
				'memo'              => $line['memo'],
			]);
			$this->db->sql_query($sql);
		}

		return $reverse_id;
	}

	public function get_account_ledger(
		int $account_id,
		int $from = 0,
		int $to = 0,
		int $page = 1,
		int $per_page = 25
	): array
 {
		return $this->paginated_lines(
			'l.account_id = ' . $account_id,
			$from,
			$to,
			$page,
			$per_page
		);
	}

	public function get_subledger_statement(
		int $user_id,
		int $from = 0,
		int $to = 0,
		int $page = 1,
		int $per_page = 25
	): array
 {
		return $this->paginated_lines(
			'l.subledger_user_id = ' . $user_id,
			$from,
			$to,
			$page,
			$per_page
		);
	}

	/**
	 * Paginated journal-entry list with the is_reversed flag derived in SQL.
	 * Centralising the EXISTS subquery here keeps Phase 2 callers from
	 * duplicating it (issue #59 cleanup item 6).
	 *
	 * @return array{rows: array<int, array<string, string|int>>, total: int}
	 */
	public function get_journal_list(int $offset = 0, int $limit = 25): array
	{
		$offset = max(0, $offset);
		$limit = max(1, $limit);

		$sql = 'SELECT COUNT(*) AS c FROM ' . $this->journal_table;
		$total = (int) $this->db->sql_fetchfield('c', false, $this->db->sql_query($sql));

		// CASE WHEN EXISTS — wrap so the result is an int (0/1) on every
		// DBAL phpBB supports. Bare EXISTS returns boolean 't'/'f' on
		// PostgreSQL, which (int)-coerces to 0 for both true and false
		// (issue #28).
		$sql = 'SELECT j.journal_id, j.entry_date, j.description, j.reference_type,
		               j.reference_source, j.reference_id, j.reversal_of,
		               CASE WHEN EXISTS(SELECT 1 FROM ' . $this->journal_table . ' r WHERE r.reversal_of = j.journal_id) THEN 1 ELSE 0 END AS is_reversed
		        FROM ' . $this->journal_table . ' j
		        ORDER BY j.entry_date DESC, j.journal_id DESC';
		$result = $this->db->sql_query_limit($sql, $limit, $offset);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return ['rows' => $rows, 'total' => $total];
	}

	protected function paginated_lines(string $where, int $from, int $to, int $page, int $per_page): array
	{
		if ($from > 0)
		{
			$where .= ' AND j.entry_date >= ' . $from;
		}
		if ($to > 0)
		{
			$where .= ' AND j.entry_date <= ' . $to;
		}

		// Aggregate totals across the entire filtered range (count + dr/cr)
		$sql = 'SELECT COUNT(*) AS c,
                       COALESCE(SUM(l.debit), 0) AS total_dr,
                       COALESCE(SUM(l.credit), 0) AS total_cr
                FROM ' . $this->lines_table . ' l
                INNER JOIN ' . $this->journal_table . ' j ON j.journal_id = l.journal_id
                WHERE ' . $where;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$total = (int) $row['c'];
		$total_debit  = bcadd((string) $row['total_dr'], '0', 2);
		$total_credit = bcadd((string) $row['total_cr'], '0', 2);

		// Page
		$page = max(1, $page);
		$per_page = max(1, $per_page);
		$start = ($page - 1) * $per_page;
		$sql = 'SELECT l.line_id, l.journal_id, l.account_id, l.debit, l.credit,
                       l.subledger_user_id, l.memo,
                       j.entry_date, j.description, j.reference_type, j.reference_source,
                       j.reference_id, j.reversal_of
                FROM ' . $this->lines_table . ' l
                INNER JOIN ' . $this->journal_table . ' j ON j.journal_id = l.journal_id
                WHERE ' . $where . '
                ORDER BY j.entry_date DESC, l.line_id DESC';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return [
			'rows'         => $rows,
			'total'        => $total,
			'total_debit'  => $total_debit,
			'total_credit' => $total_credit,
			'page'         => $page,
			'per_page'     => $per_page,
		];
	}

	/**
	 * For a phpBB user, return one summary row per account they have any
	 * activity in (within the optional [from, to] range), with opening,
	 * period debit/credit, and closing balance on each row.
	 *
	 * Used by the ACP subledger statement to render a header strip above
	 * the line-level detail.
	 */
	public function get_subledger_account_balances(int $user_id, int $from = 0, int $to = 0): array
	{
		// Find every account this user has touched within the cutoff (or ever, if no cutoff)
		$where = 'l.subledger_user_id = ' . $user_id;
		if ($to > 0)
		{
			$where .= ' AND j.entry_date <= ' . $to;
		}
		$sql = 'SELECT DISTINCT l.account_id
                FROM ' . $this->lines_table . ' l
                INNER JOIN ' . $this->journal_table . ' j ON j.journal_id = l.journal_id
                WHERE ' . $where;
		$result = $this->db->sql_query($sql);
		$account_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$account_ids[] = (int) $row['account_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($account_ids))
		{
			return [];
		}

		$accounts = [];
		foreach ($account_ids as $aid)
		{
			$account = $this->load_account($aid);
			if ($account === null)
			{
				continue;
			}

			// Opening: balance as of (from - 1). With from = 0 there is no "before",
			// so opening collapses to 0.00 — matches the convention that an unbounded
			// statement has no separate opening period.
			$opening = $from > 0
				? $this->get_subledger_balance($aid, $user_id, $from - 1)
				: '0.00';

			$closing = $this->get_subledger_balance($aid, $user_id, $to);

			// Period movement (raw debit/credit during the window)
			$period_where = 'l.account_id = ' . $aid . ' AND l.subledger_user_id = ' . $user_id;
			if ($from > 0)
			{
				$period_where .= ' AND j.entry_date >= ' . $from;
			}
			if ($to > 0)
			{
				$period_where .= ' AND j.entry_date <= ' . $to;
			}

			$sql = 'SELECT COALESCE(SUM(l.debit), 0) AS dr, COALESCE(SUM(l.credit), 0) AS cr
                    FROM ' . $this->lines_table . ' l
                    INNER JOIN ' . $this->journal_table . ' j ON j.journal_id = l.journal_id
                    WHERE ' . $period_where;
			$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
			$period_debit  = bcadd((string) $row['dr'], '0', 2);
			$period_credit = bcadd((string) $row['cr'], '0', 2);

			$accounts[] = [
				'account_id'    => $aid,
				'account_code'  => $account['account_code'],
				'account_name'  => $account['account_name'],
				'account_type'  => $account['account_type'],
				'currency_code' => $account['currency_code'],
				'opening'       => $opening,
				'period_debit'  => $period_debit,
				'period_credit' => $period_credit,
				'closing'       => $closing,
			];
		}

		usort($accounts, static fn ($a, $b) => strcmp($a['account_code'], $b['account_code']));

		return $accounts;
	}

	public function get_trial_balance(int $as_of = 0, string $currency_code = ''): array
	{
		$where = '1=1';
		if ($currency_code !== '')
		{
			$where .= " AND a.currency_code = '" . $this->db->sql_escape($currency_code) . "'";
		}
		$date_where = $as_of > 0 ? ' AND j.entry_date <= ' . $as_of : '';

		// SUM is gated on j.journal_id IS NOT NULL because the journal LEFT
		// JOIN may set j.* to NULL when the date predicate fails — and in
		// that case the line is still present in the row but must NOT be
		// counted. Without this gate the date cutoff was silently ignored.
		$sql = 'SELECT a.account_id, a.account_code, a.account_name, a.account_type, a.currency_code,
                       COALESCE(SUM(CASE WHEN j.journal_id IS NULL THEN 0 ELSE l.debit  END), 0) AS debit_total,
                       COALESCE(SUM(CASE WHEN j.journal_id IS NULL THEN 0 ELSE l.credit END), 0) AS credit_total
                FROM ' . $this->accounts_table . ' a
                LEFT JOIN ' . $this->lines_table . ' l ON l.account_id = a.account_id
                LEFT JOIN ' . $this->journal_table . ' j ON j.journal_id = l.journal_id' . $date_where . '
                WHERE ' . $where . '
                GROUP BY a.account_id, a.account_code, a.account_name, a.account_type, a.currency_code
                ORDER BY a.currency_code, a.account_code';

		$result = $this->db->sql_query($sql);
		$grouped = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$row['debit_total']  = bcadd((string) $row['debit_total'],  '0', 2);
			$row['credit_total'] = bcadd((string) $row['credit_total'], '0', 2);
			$grouped[$row['currency_code']][] = $row;
		}
		$this->db->sql_freeresult($result);

		return $grouped;
	}

	public function get_account_balance(int $account_id, int $as_of = 0): string
	{
		$account = $this->load_account($account_id);
		if ($account === null)
		{
			throw new \InvalidArgumentException("Account {$account_id} not found.");
		}

		[$dr, $cr] = $this->sum_lines_for_account($account_id, 0, $as_of);
		return $this->normal_balance($account['account_type'], $dr, $cr);
	}

	public function get_subledger_balance(int $account_id, int $user_id, int $as_of = 0): string
	{
		$account = $this->load_account($account_id);
		if ($account === null)
		{
			throw new \InvalidArgumentException("Account {$account_id} not found.");
		}

		[$dr, $cr] = $this->sum_lines_for_account($account_id, $user_id, $as_of);
		return $this->normal_balance($account['account_type'], $dr, $cr);
	}

	protected function sum_lines_for_account(int $account_id, int $user_id, int $as_of): array
	{
		$where = 'l.account_id = ' . $account_id;
		if ($user_id > 0)
		{
			$where .= ' AND l.subledger_user_id = ' . $user_id;
		}
		if ($as_of > 0)
		{
			$where .= ' AND j.entry_date <= ' . $as_of;
		}

		$sql = 'SELECT COALESCE(SUM(l.debit), 0) AS dr, COALESCE(SUM(l.credit), 0) AS cr
                FROM ' . $this->lines_table . ' l
                INNER JOIN ' . $this->journal_table . ' j ON j.journal_id = l.journal_id
                WHERE ' . $where;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		return [bcadd((string) $row['dr'], '0', 2), bcadd((string) $row['cr'], '0', 2)];
	}

	protected function normal_balance(string $account_type, string $dr, string $cr): string
	{
		if (in_array($account_type, ['asset', 'expense'], true))
		{
			return bcsub($dr, $cr, 2);
		}
		return bcsub($cr, $dr, 2);
	}

	protected function validate_reference_type(string $reference_type): void
	{
		if (!in_array($reference_type, self::VALID_REFERENCE_TYPES, true))
		{
			throw new \InvalidArgumentException(
				"Unknown reference_type '{$reference_type}'. Allowed: "
				. implode(', ', self::VALID_REFERENCE_TYPES) . '.'
			);
		}
	}

	protected function validate_lines(array $lines): void
	{
		if (count($lines) < 2)
		{
			throw new \InvalidArgumentException('A journal entry needs at least two lines.');
		}

		$debits  = '0.00';
		$credits = '0.00';
		$first_currency = null;

		foreach ($lines as $i => $line)
		{
			$debit  = (string) ($line['debit']  ?? '0.00');
			$credit = (string) ($line['credit'] ?? '0.00');
			$debit_pos  = bccomp($debit, '0.00', 2) > 0;
			$credit_pos = bccomp($credit, '0.00', 2) > 0;

			if ($debit_pos && $credit_pos)
			{
				throw new \InvalidArgumentException("Line {$i}: cannot have both debit and credit.");
			}
			if (!$debit_pos && !$credit_pos)
			{
				throw new \InvalidArgumentException("Line {$i}: must have either a positive debit or a positive credit.");
			}

			$account = $this->load_account((int) $line['account_id']);
			if ($account === null || (int) $account['is_active'] !== 1)
			{
				throw new \InvalidArgumentException("Line {$i}: account_id {$line['account_id']} does not exist or is inactive.");
			}

			// Defence-in-depth: the account form's currency dropdown only
			// shows active currencies, but service callers (external
			// extensions, tests, future imports) might reach this path
			// with an account whose currency was deactivated or removed.
			// Reversals construct lines without going through validate_lines,
			// so they remain unaffected and can always undo prior activity.
			if (!$this->currency_is_active((string) $account['currency_code']))
			{
				throw new \InvalidArgumentException("Line {$i}: currency {$account['currency_code']} is unknown or inactive.");
			}

			if ($first_currency === null)
			{
				$first_currency = $account['currency_code'];
			}
			else if ($account['currency_code'] !== $first_currency)
			{
				throw new \InvalidArgumentException("Line {$i}: mixed-pool entry rejected (expected {$first_currency}, got {$account['currency_code']}).");
			}

			$is_subledger = $account['subledger_type'] !== '';
			$sub_id = (int) ($line['subledger_user_id'] ?? 0);
			if ($is_subledger && $sub_id === 0)
			{
				throw new \InvalidArgumentException("Line {$i}: account requires a subledger_user_id.");
			}
			if (!$is_subledger && $sub_id !== 0)
			{
				throw new \InvalidArgumentException("Line {$i}: account does not accept a subledger_user_id.");
			}

			$debits  = bcadd($debits,  $debit,  2);
			$credits = bcadd($credits, $credit, 2);
		}

		if (bccomp($debits, $credits, 2) !== 0)
		{
			throw new \InvalidArgumentException("Entry is unbalanced: debits={$debits}, credits={$credits}.");
		}
	}

	protected function load_account(int $account_id): ?array
	{
		$sql = 'SELECT account_id, account_code, account_name, account_type, currency_code, subledger_type, is_active
                FROM ' . $this->accounts_table . '
                WHERE account_id = ' . $account_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row ?: null;
	}

	/**
	 * True iff the currency exists in the managed currencies table and is
	 * marked active. When the table parameter is unset (legacy callers
	 * that constructed the service with the old four-arg signature) the
	 * check is skipped — keeps the optional-parameter constructor sane.
	 */
	protected function currency_is_active(string $code): bool
	{
		if ($this->currencies_table === '')
		{
			return true;
		}
		$sql = 'SELECT is_active FROM ' . $this->currencies_table
			 . " WHERE currency_code = '" . $this->db->sql_escape($code) . "'";
		$row = $this->db->sql_fetchrow($this->db->sql_query_limit($sql, 1));
		return $row !== false && (int) $row['is_active'] === 1;
	}
}
