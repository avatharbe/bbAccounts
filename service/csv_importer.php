<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\service;

/**
 * Parse + validate a journal-entry CSV in one pass.
 *
 * Returns a normalized structure for the ACP preview/commit flow; this
 * service never writes — the controller wraps `create_entry()` in a
 * transaction. DB reads (`accounts`, `users`) are batched per import to
 * stay O(1) regardless of row count.
 */
class csv_importer
{
	public const REQUIRED_COLUMNS = ['entry_ref', 'entry_date', 'description', 'account_code', 'debit', 'credit'];
	public const OPTIONAL_COLUMNS = ['subledger_user_id', 'memo', 'reference_type', 'reference_source', 'reference_id'];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var string */
	protected $accounts_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, string $accounts_table)
	{
		$this->db = $db;
		$this->accounts_table = $accounts_table;
	}

	/**
	 * @param string $content Raw CSV content (UTF-8, RFC 4180).
	 * @return array{
	 *     global_errors: string[],
	 *     entries: array<string, array{
	 *         date: int,
	 *         description: string,
	 *         lines: array<int, array<string, mixed>>,
	 *         errors: string[],
	 *         totals: array{debit: string, credit: string}
	 *     }>
	 * }
	 */
	public function parse_and_validate(string $content): array
	{
		$result = ['global_errors' => [], 'entries' => []];

		$rows = $this->parse_csv($content);
		if (empty($rows))
		{
			$result['global_errors'][] = 'CSV is empty or unreadable.';
			return $result;
		}

		$header = array_map(static fn ($v) => strtolower(trim((string) $v)), array_shift($rows));
		foreach (self::REQUIRED_COLUMNS as $col)
		{
			if (!in_array($col, $header, true))
			{
				$result['global_errors'][] = sprintf('Header missing required column "%s".', $col);
			}
		}
		if (!empty($result['global_errors']))
		{
			return $result;
		}
		$col_index = array_flip($header);

		// First pass — group rows by entry_ref, parse decimals/dates, collect
		// per-row errors for things we can decide without DB lookups.
		$entries = [];
		foreach ($rows as $idx => $row)
		{
			$line_no = $idx + 2; // +1 for header, +1 for 1-based numbering

			// Normalise short rows so undefined-index reads stay safe.
			$row = array_pad($row, count($header), '');

			$entry_ref = trim((string) ($row[$col_index['entry_ref']] ?? ''));
			if ($entry_ref === '')
			{
				$has_data = false;
				foreach ($row as $cell)
				{
					if (trim((string) $cell) !== '')
					{
						$has_data = true;
						break;
					}
				}
				if (!$has_data)
				{
					continue;
				}
				$result['global_errors'][] = sprintf('Row %d: blank entry_ref.', $line_no);
				continue;
			}

			if (!isset($entries[$entry_ref]))
			{
				$entries[$entry_ref] = [
					'date'        => 0,
					'description' => '',
					'lines'       => [],
					'errors'      => [],
					'totals'      => ['debit' => '0.00', 'credit' => '0.00'],
				];
			}

			$debit_raw  = trim((string) ($row[$col_index['debit']] ?? ''));
			$credit_raw = trim((string) ($row[$col_index['credit']] ?? ''));
			$debit  = $debit_raw  === '' ? '0.00' : $this->parse_decimal($debit_raw);
			$credit = $credit_raw === '' ? '0.00' : $this->parse_decimal($credit_raw);

			$line_errors = [];
			if ($debit === null)
			{
				$line_errors[] = sprintf('Row %d: invalid decimal in debit "%s".', $line_no, $debit_raw);
				$debit = '0.00';
			}
			if ($credit === null)
			{
				$line_errors[] = sprintf('Row %d: invalid decimal in credit "%s".', $line_no, $credit_raw);
				$credit = '0.00';
			}

			$dr_nz = bccomp($debit,  '0', 2) !== 0;
			$cr_nz = bccomp($credit, '0', 2) !== 0;
			if ($dr_nz && $cr_nz)
			{
				$line_errors[] = sprintf('Row %d: both debit and credit are non-zero.', $line_no);
			}
			if (!$dr_nz && !$cr_nz)
			{
				$line_errors[] = sprintf('Row %d: one of debit or credit must be non-zero.', $line_no);
			}

			$line = [
				'line_no'           => $line_no,
				'account_code'      => trim((string) ($row[$col_index['account_code']] ?? '')),
				'debit'             => $debit,
				'credit'            => $credit,
				'subledger_user_id' => isset($col_index['subledger_user_id']) ? (int) ($row[$col_index['subledger_user_id']] ?? 0) : 0,
				'memo'              => isset($col_index['memo']) ? trim((string) ($row[$col_index['memo']] ?? '')) : '',
				'reference_type'    => isset($col_index['reference_type']) && trim((string) $row[$col_index['reference_type']]) !== ''
					? trim((string) $row[$col_index['reference_type']])
					: 'manual',
				'reference_source'  => isset($col_index['reference_source']) ? trim((string) $row[$col_index['reference_source']]) : '',
				'reference_id'      => isset($col_index['reference_id']) ? (int) ($row[$col_index['reference_id']] ?? 0) : 0,
				'errors'            => $line_errors,
				'account'           => null,
			];

			// First-row-wins for the per-entry header fields.
			if ($entries[$entry_ref]['date'] === 0)
			{
				$entry_date_raw = trim((string) ($row[$col_index['entry_date']] ?? ''));
				$ts = $this->parse_date($entry_date_raw);
				if ($ts === null)
				{
					$entries[$entry_ref]['errors'][] = sprintf('Entry "%s" (row %d): invalid entry_date "%s".', $entry_ref, $line_no, $entry_date_raw);
				}
				else
				{
					$entries[$entry_ref]['date'] = $ts;
				}
				$entries[$entry_ref]['description'] = trim((string) ($row[$col_index['description']] ?? ''));
			}

			$entries[$entry_ref]['lines'][] = $line;
		}

		if (empty($entries))
		{
			$result['global_errors'][] = 'No data rows in CSV.';
			return $result;
		}

		// Batch-resolve account codes and subledger user_ids — keeps the
		// importer at O(1) DB queries regardless of row count.
		$codes = [];
		$user_ids = [];
		foreach ($entries as $entry)
		{
			foreach ($entry['lines'] as $line)
			{
				if ($line['account_code'] !== '')
				{
					$codes[$line['account_code']] = true;
				}
				if ($line['subledger_user_id'] > 0)
				{
					$user_ids[$line['subledger_user_id']] = true;
				}
			}
		}
		$accounts_by_code = $this->load_accounts_by_code(array_keys($codes));
		$valid_user_ids   = $this->load_existing_user_ids(array_keys($user_ids));

		// Per-entry validation: balance, line count, account checks, currency, subledger.
		foreach ($entries as $entry_ref => &$entry)
		{
			$sum_dr = '0.00';
			$sum_cr = '0.00';
			$currencies = [];

			foreach ($entry['lines'] as &$line)
			{
				$code = $line['account_code'];
				if ($code === '')
				{
					$line['errors'][] = sprintf('Row %d: missing account_code.', $line['line_no']);
				}
				else if (!isset($accounts_by_code[$code]))
				{
					$line['errors'][] = sprintf('Row %d: unknown account_code "%s".', $line['line_no'], $code);
				}
				else
				{
					$account = $accounts_by_code[$code];
					$line['account'] = $account;
					if ((int) $account['is_active'] !== 1)
					{
						$line['errors'][] = sprintf('Row %d: account "%s" is inactive.', $line['line_no'], $code);
					}
					$currencies[$account['currency_code']] = true;

					if ((string) $account['subledger_type'] !== '')
					{
						if ($line['subledger_user_id'] <= 0)
						{
							$line['errors'][] = sprintf('Row %d: account "%s" requires subledger_user_id.', $line['line_no'], $code);
						}
						else if (!isset($valid_user_ids[$line['subledger_user_id']]))
						{
							$line['errors'][] = sprintf('Row %d: subledger_user_id %d does not exist.', $line['line_no'], $line['subledger_user_id']);
						}
					}
				}

				$sum_dr = bcadd($sum_dr, $line['debit'],  2);
				$sum_cr = bcadd($sum_cr, $line['credit'], 2);
			}
			unset($line);

			if (count($entry['lines']) < 2)
			{
				$entry['errors'][] = sprintf('Entry "%s": needs at least 2 lines.', $entry_ref);
			}
			if (bccomp($sum_dr, $sum_cr, 2) !== 0)
			{
				$entry['errors'][] = sprintf('Entry "%s": unbalanced (debits %s vs credits %s).', $entry_ref, $sum_dr, $sum_cr);
			}
			if (count($currencies) > 1)
			{
				$entry['errors'][] = sprintf('Entry "%s": mixes currencies (%s).', $entry_ref, implode(', ', array_keys($currencies)));
			}

			$entry['totals'] = ['debit' => $sum_dr, 'credit' => $sum_cr];
		}
		unset($entry);

		$result['entries'] = $entries;
		return $result;
	}

	/**
	 * True iff the validation result contains no errors at any level — the
	 * caller can use this as the gate for showing the Confirm button.
	 */
	public function is_clean(array $parsed): bool
	{
		if (!empty($parsed['global_errors']))
		{
			return false;
		}
		foreach ($parsed['entries'] as $entry)
		{
			if (!empty($entry['errors']))
			{
				return false;
			}
			foreach ($entry['lines'] as $line)
			{
				if (!empty($line['errors']))
				{
					return false;
				}
			}
		}
		return !empty($parsed['entries']);
	}

	protected function parse_csv(string $content): array
	{
		// Strip UTF-8 BOM so the first header cell isn't "\xef\xbb\xbfentry_ref".
		if (strncmp($content, "\xEF\xBB\xBF", 3) === 0)
		{
			$content = substr($content, 3);
		}

		$stream = fopen('php://temp', 'r+');
		if ($stream === false)
		{
			return [];
		}
		fwrite($stream, $content);
		rewind($stream);

		$rows = [];
		while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false)
		{
			if (count($row) === 1 && ($row[0] === null || trim((string) $row[0]) === ''))
			{
				continue;
			}
			$rows[] = $row;
		}
		fclose($stream);
		return $rows;
	}

	protected function parse_decimal(string $raw): ?string
	{
		if (!preg_match('/^-?\d+(\.\d+)?$/', $raw))
		{
			return null;
		}
		return bcadd($raw, '0', 2);
	}

	protected function parse_date(string $raw): ?int
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw))
		{
			return null;
		}
		$ts = strtotime($raw . ' UTC');
		return $ts !== false ? $ts : null;
	}

	/**
	 * @param string[] $codes
	 * @return array<string, array<string, mixed>>
	 */
	protected function load_accounts_by_code(array $codes): array
	{
		if (empty($codes))
		{
			return [];
		}
		$escaped = array_map(fn ($c) => "'" . $this->db->sql_escape((string) $c) . "'", $codes);
		$sql = 'SELECT account_id, account_code, account_name, account_type, currency_code, subledger_type, is_active
		        FROM ' . $this->accounts_table . '
		        WHERE account_code IN (' . implode(', ', $escaped) . ')';
		$result = $this->db->sql_query($sql);
		$map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[(string) $row['account_code']] = $row;
		}
		$this->db->sql_freeresult($result);
		return $map;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, true>
	 */
	protected function load_existing_user_ids(array $ids): array
	{
		if (empty($ids))
		{
			return [];
		}
		$ids = array_map('intval', $ids);
		$sql = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE ' . $this->db->sql_in_set('user_id', $ids);
		$result = $this->db->sql_query($sql);
		$map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[(int) $row['user_id']] = true;
		}
		$this->db->sql_freeresult($result);
		return $map;
	}
}
