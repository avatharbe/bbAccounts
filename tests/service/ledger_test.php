<?php
/**
 * bbAccounts — Ledger service tests.
 *
 * Extends the shared database_test_case base which sets up $this->ledger
 * and $this->db against the bbaccounts schema seeded from the fixture.
 */

namespace avathar\bbaccounts\tests\service;

class ledger_test extends \avathar\bbaccounts\tests\database_test_case
{
	public function test_create_entry_rejects_unbalanced(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('unbalanced');

		$this->ledger->create_entry(
			time(),
			'Bad entry',
			[
				['account_id' => 3, 'debit' => '100.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',   'credit' => '90.00', 'subledger_user_id' => 7, 'memo' => ''],
			]
		);
	}

	public function test_create_entry_rejects_too_few_lines(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('at least two lines');

		$this->ledger->create_entry(time(), 'Solo', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_line_with_both_debit_and_credit(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('both debit and credit');

		$this->ledger->create_entry(time(), 'Mixed line', [
			['account_id' => 3, 'debit' => '50.00', 'credit' => '50.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_line_with_neither(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('positive debit or a positive credit');

		$this->ledger->create_entry(time(), 'Empty line', [
			['account_id' => 3, 'debit' => '0.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '0.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_inactive_account(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('inactive');

		$this->ledger->create_entry(time(), 'Inactive ref', [
			['account_id' => 5, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_mixed_pool(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('mixed-pool');

		$this->ledger->create_entry(time(), 'Mixed pool', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 4, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_subledger_account_without_user_id(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('requires a subledger_user_id');

		$this->ledger->create_entry(time(), 'Missing sub', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 0, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_non_subledger_account_with_user_id(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('does not accept a subledger_user_id');

		$this->ledger->create_entry(time(), 'Stray sub', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 7, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_unknown_reference_type(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Unknown reference_type 'foo'");

		$this->ledger->create_entry(
			time(),
			'Bogus type',
			[
				['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
			],
			'foo'
		);
	}

	public function test_create_entry_accepts_each_valid_reference_type(): void
	{
		foreach (\avathar\bbaccounts\service\ledger::VALID_REFERENCE_TYPES as $type)
		{
			$journal_id = $this->ledger->create_entry(
				1700050000,
				"Type {$type}",
				[
					['account_id' => 3, 'debit' => '1.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
					['account_id' => 2, 'debit' => '0.00', 'credit' => '1.00', 'subledger_user_id' => 7, 'memo' => ''],
				],
				$type
			);
			$this->assertGreaterThan(0, $journal_id);
		}
	}

	public function test_create_entry_persists_balanced_entry(): void
	{
		$journal_id = $this->ledger->create_entry(
			1700000000,
			'Admin grant to user 7',
			[
				['account_id' => 3, 'debit' => '500.00', 'credit' => '0.00',   'subledger_user_id' => 0, 'memo' => 'grant'],
				['account_id' => 2, 'debit' => '0.00',   'credit' => '500.00', 'subledger_user_id' => 7, 'memo' => 'wallet'],
			],
			'manual',
			0,
			'bbaccounts.manual'
		);

		$this->assertGreaterThan(0, $journal_id);

		$sql = "SELECT entry_date, description, reference_type, reference_source, reversal_of
                FROM {$this->journal_table}
                WHERE journal_id = " . $journal_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$this->assertSame('1700000000',            $row['entry_date']);
		$this->assertSame('Admin grant to user 7', $row['description']);
		$this->assertSame('manual',                $row['reference_type']);
		$this->assertSame('bbaccounts.manual',     $row['reference_source']);
		$this->assertSame('0',                     $row['reversal_of']);

		$sql = "SELECT account_id, debit, credit, subledger_user_id
                FROM {$this->lines_table}
                WHERE journal_id = " . $journal_id . "
                ORDER BY line_id";
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($r = $this->db->sql_fetchrow($result))
		{
			$rows[] = $r;
		}
		$this->db->sql_freeresult($result);

		$this->assertCount(2, $rows);
		$this->assertSame(['account_id' => '3', 'debit' => '500.00', 'credit' => '0.00',   'subledger_user_id' => '0'], $rows[0]);
		$this->assertSame(['account_id' => '2', 'debit' => '0.00',   'credit' => '500.00', 'subledger_user_id' => '7'], $rows[1]);
	}

	public function test_create_entry_persists_created_by(): void
	{
		$journal_id = $this->ledger->create_entry(
			1700000050,
			'Audited grant',
			[
				['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
			],
			'manual',
			0,
			'',
			42
		);

		$sql = "SELECT created_by FROM {$this->journal_table} WHERE journal_id = " . $journal_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$this->assertSame('42', $row['created_by']);
	}

	public function test_reverse_entry_persists_created_by(): void
	{
		$original_id = $this->ledger->create_entry(
			1700000060,
			'To be reversed',
			[
				['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
			],
			'manual',
			0,
			'',
			42
		);

		$reverse_id = $this->ledger->reverse_entry($original_id, '', 99);

		$sql = "SELECT created_by FROM {$this->journal_table} WHERE journal_id = " . $reverse_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$this->assertSame('99', $row['created_by']);
	}

	public function test_create_entry_accepts_four_line_balanced_entry(): void
	{
		$journal_id = $this->ledger->create_entry(
			1700000100,
			'Two grants split',
			[
				['account_id' => 3, 'debit' => '100.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 3, 'debit' => '50.00',  'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',   'credit' => '90.00', 'subledger_user_id' => 7, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',   'credit' => '60.00', 'subledger_user_id' => 8, 'memo' => ''],
			]
		);
		$this->assertGreaterThan(0, $journal_id);

		$sql = "SELECT COUNT(*) AS c FROM {$this->lines_table} WHERE journal_id = " . $journal_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$this->assertSame('4', $row['c']);
	}

	public function test_get_journal_list_marks_reversed_entries(): void
	{
		// Original entry — should NOT come back with is_reversed = 1.
		$original_id = $this->ledger->create_entry(
			1700020000,
			'Original',
			[
				['account_id' => 3, 'debit' => '1.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00', 'credit' => '1.00', 'subledger_user_id' => 7, 'memo' => ''],
			]
		);

		// Untouched control row.
		$untouched_id = $this->ledger->create_entry(
			1700020100,
			'Untouched',
			[
				['account_id' => 3, 'debit' => '2.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00', 'credit' => '2.00', 'subledger_user_id' => 7, 'memo' => ''],
			]
		);

		$reverse_id = $this->ledger->reverse_entry($original_id);

		$list = $this->ledger->get_journal_list(0, 25);
		$by_id = [];
		foreach ($list['rows'] as $row)
		{
			$by_id[(int) $row['journal_id']] = (int) $row['is_reversed'];
		}

		// is_reversed must be a real 1/0 across every DBAL phpBB supports.
		// On PostgreSQL a bare EXISTS returns 't'/'f' which (int)-coerces
		// to 0 for both — this assertion catches that regression (issue #28).
		$this->assertSame(1, $by_id[$original_id], 'original entry should be flagged is_reversed=1');
		$this->assertSame(0, $by_id[$untouched_id], 'untouched entry should be flagged is_reversed=0');
		$this->assertSame(0, $by_id[$reverse_id], 'reversal entry itself is not reversed');
	}

	public function test_reverse_entry_creates_mirrored_entry(): void
	{
		$original_id = $this->ledger->create_entry(
			1700000200,
			'Original',
			[
				['account_id' => 3, 'debit' => '40.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',  'credit' => '40.00', 'subledger_user_id' => 7, 'memo' => ''],
			]
		);

		$reverse_id = $this->ledger->reverse_entry($original_id, 'Reversal of #' . $original_id);
		$this->assertGreaterThan($original_id, $reverse_id);

		$sql = "SELECT reversal_of, reference_type, entry_date, description
                FROM {$this->journal_table}
                WHERE journal_id = " . $reverse_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$this->assertSame((string) $original_id, $row['reversal_of']);
		$this->assertSame('auto',                $row['reference_type']);
		$this->assertSame('1700000200',          $row['entry_date']);
		$this->assertSame('Reversal of #' . $original_id, $row['description']);

		$sql = "SELECT account_id, debit, credit, subledger_user_id
                FROM {$this->lines_table}
                WHERE journal_id = " . $reverse_id . "
                ORDER BY line_id";
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($r = $this->db->sql_fetchrow($result))
		{
			$rows[] = $r;
		}
		$this->db->sql_freeresult($result);

		$this->assertCount(2, $rows);
		$this->assertSame(['account_id' => '3', 'debit' => '0.00',  'credit' => '40.00', 'subledger_user_id' => '0'], $rows[0]);
		$this->assertSame(['account_id' => '2', 'debit' => '40.00', 'credit' => '0.00',  'subledger_user_id' => '7'], $rows[1]);
	}

	public function test_reverse_entry_rejects_unknown_journal_id(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('not found');
		$this->ledger->reverse_entry(99999);
	}

	public function test_reverse_entry_rejects_reversal_of_a_reversal(): void
	{
		$original_id = $this->ledger->create_entry(
			1700000300,
			'Original',
			[
				['account_id' => 3, 'debit' => '5.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
				['account_id' => 2, 'debit' => '0.00', 'credit' => '5.00', 'subledger_user_id' => 7, 'memo' => ''],
			]
		);
		$reverse_id = $this->ledger->reverse_entry($original_id);

		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('reversal of a reversal');
		$this->ledger->reverse_entry($reverse_id);
	}

	public function test_get_account_balance_for_expense_account_is_debit_minus_credit(): void
	{
		// Account 3 is type=expense (debit-normal). After a debit of 100, balance should be 100.
		$this->ledger->create_entry(1700001000, 'A', [
			['account_id' => 3, 'debit' => '100.00', 'credit' => '0.00',   'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',   'credit' => '100.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->assertSame('100.00', $this->ledger->get_account_balance(3));
	}

	public function test_get_account_balance_for_liability_is_credit_minus_debit(): void
	{
		// Account 2 is type=liability (credit-normal). After a credit of 50, balance should be 50.
		$this->ledger->create_entry(1700001100, 'A', [
			['account_id' => 3, 'debit' => '50.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->assertSame('50.00', $this->ledger->get_account_balance(2));
	}

	public function test_get_subledger_balance_filters_by_user(): void
	{
		$this->ledger->create_entry(1700001200, 'Grant 7', [
			['account_id' => 3, 'debit' => '20.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '20.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700001300, 'Grant 8', [
			['account_id' => 3, 'debit' => '30.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '30.00', 'subledger_user_id' => 8, 'memo' => ''],
		]);
		$this->assertSame('20.00', $this->ledger->get_subledger_balance(2, 7));
		$this->assertSame('30.00', $this->ledger->get_subledger_balance(2, 8));
	}

	public function test_get_subledger_balance_for_unknown_user_returns_zero(): void
	{
		// Lookup safety net for the user-balance UI (#64): if an admin enters
		// a username that resolves to a real user_id with no activity in this
		// account, the lookup must return a clean '0.00' rather than fall
		// through to a DB error or NULL coercion.
		$this->ledger->create_entry(1700001400, 'Grant 7 only', [
			['account_id' => 3, 'debit' => '50.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->assertSame('0.00', $this->ledger->get_subledger_balance(2, 99999));
	}

	public function test_get_account_balance_filters_by_as_of_against_entry_date(): void
	{
		$this->ledger->create_entry(1700002000, 'Old', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700009000, 'New', [
			['account_id' => 3, 'debit' => '7.00',  'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '7.00',  'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->assertSame('10.00', $this->ledger->get_account_balance(3, 1700005000));
		$this->assertSame('17.00', $this->ledger->get_account_balance(3, 1700009999));
		$this->assertSame('17.00', $this->ledger->get_account_balance(3, 0));
	}

	public function test_get_account_balance_includes_backdated_entry_in_prior_period(): void
	{
		// Insert "now" entry first, query historical balance — should be 0.
		$this->ledger->create_entry(1700009000, 'New', [
			['account_id' => 3, 'debit' => '7.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '7.00',  'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->assertSame('0.00', $this->ledger->get_account_balance(3, 1700005000));

		// Backdated correction lands in the prior period.
		$this->ledger->create_entry(1700001000, 'Backdated', [
			['account_id' => 3, 'debit' => '3.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '3.00',  'subledger_user_id' => 7, 'memo' => ''],
		]);

		// Re-querying with the same as_of now returns a different number, proving as_of filters entry_date.
		$this->assertSame('3.00', $this->ledger->get_account_balance(3, 1700005000));
	}

	public function test_get_trial_balance_groups_by_currency(): void
	{
		$this->ledger->create_entry(1700003000, 'Points entry', [
			['account_id' => 3, 'debit' => '15.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '15.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		// GOLD entry: a transfer between two users on the same account.
		// Same-pool, balanced, both lines on a subledger account with non-zero user_ids — all rules satisfied.
		$this->ledger->create_entry(1700003100, 'Gold transfer', [
			['account_id' => 4, 'debit' => '20.00', 'credit' => '0.00',  'subledger_user_id' => 9, 'memo' => ''],
			['account_id' => 4, 'debit' => '0.00',  'credit' => '20.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		$tb = $this->ledger->get_trial_balance();
		$this->assertArrayHasKey('POINTS', $tb);
		$this->assertArrayHasKey('GOLD',   $tb);

		$points_codes = array_column($tb['POINTS'], 'account_code');
		$this->assertContains('1010', $points_codes);
		$this->assertContains('2100', $points_codes);
		$this->assertContains('5050', $points_codes);

		foreach ($tb['POINTS'] as $row)
		{
			if ($row['account_code'] === '5050')
			{
				$this->assertSame('15.00', $row['debit_total']);
				$this->assertSame('0.00',  $row['credit_total']);
			}
			if ($row['account_code'] === '2100')
			{
				$this->assertSame('0.00',  $row['debit_total']);
				$this->assertSame('15.00', $row['credit_total']);
			}
		}
	}

	public function test_get_trial_balance_filters_by_currency(): void
	{
		$this->ledger->create_entry(1700003200, 'Points entry', [
			['account_id' => 3, 'debit' => '5.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '5.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$tb = $this->ledger->get_trial_balance(0, 'POINTS');
		$this->assertSame(['POINTS'], array_keys($tb));
	}

	public function test_get_trial_balance_each_pool_balances(): void
	{
		$this->ledger->create_entry(1700003300, 'Points entry', [
			['account_id' => 3, 'debit' => '11.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '11.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		$tb = $this->ledger->get_trial_balance();
		foreach ($tb as $currency => $rows)
		{
			$dr = '0.00';
			$cr = '0.00';
			foreach ($rows as $r)
			{
				$dr = bcadd($dr, $r['debit_total'], 2);
				$cr = bcadd($cr, $r['credit_total'], 2);
			}
			$this->assertSame(0, bccomp($dr, $cr, 2), "Pool {$currency} unbalanced: dr={$dr} cr={$cr}");
		}
	}

	public function test_get_account_ledger_returns_paginated_lines(): void
	{
		for ($i = 1; $i <= 7; $i++)
		{
			$this->ledger->create_entry(1700004000 + $i, "Entry {$i}", [
				['account_id' => 3, 'debit' => '1.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => "memo {$i}"],
				['account_id' => 2, 'debit' => '0.00', 'credit' => '1.00', 'subledger_user_id' => 7, 'memo' => ''],
			]);
		}
		$page1 = $this->ledger->get_account_ledger(3, 0, 0, 1, 5);
		$this->assertCount(5, $page1['rows']);
		$this->assertSame(7, $page1['total']);

		$page2 = $this->ledger->get_account_ledger(3, 0, 0, 2, 5);
		$this->assertCount(2, $page2['rows']);
	}

	public function test_get_account_ledger_filters_by_date_range(): void
	{
		$this->ledger->create_entry(1700004500, 'Old',  [
			['account_id' => 3, 'debit' => '2.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '2.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700005500, 'New',  [
			['account_id' => 3, 'debit' => '3.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '3.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		$result = $this->ledger->get_account_ledger(3, 1700005000, 1700006000);
		$this->assertSame(1, $result['total']);
	}

	public function test_get_subledger_statement_collects_all_user_lines(): void
	{
		$this->ledger->create_entry(1700006000, 'Wallet 7', [
			['account_id' => 3, 'debit' => '4.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '4.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700006100, 'Gold 7', [
			['account_id' => 4, 'debit' => '5.00', 'credit' => '0.00', 'subledger_user_id' => 9, 'memo' => ''],
			['account_id' => 4, 'debit' => '0.00', 'credit' => '5.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$stmt = $this->ledger->get_subledger_statement(7, 0, 0, 1, 25);
		$this->assertSame(2, $stmt['total']);
	}

	public function test_paginated_lines_returns_range_totals(): void
	{
		// Three POINTS-pool entries crediting the user wallet (account 2)
		$this->ledger->create_entry(1700007000, 'one', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700007100, 'two', [
			['account_id' => 3, 'debit' => '20.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '20.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700007200, 'three', [
			['account_id' => 3, 'debit' => '30.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '30.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		// Page 1 of 2 (per_page=2). Only 2 rows on this page, but the
		// range totals must reflect all 3 rows in the filtered set.
		$result = $this->ledger->get_account_ledger(3, 0, 0, 1, 2);
		$this->assertCount(2, $result['rows']);
		$this->assertSame(3, $result['total']);
		$this->assertSame('60.00', $result['total_debit']);
		$this->assertSame('0.00',  $result['total_credit']);
	}

	public function test_get_account_ledger_returns_zero_totals_when_empty(): void
	{
		$result = $this->ledger->get_account_ledger(3, 0, 0, 1, 25);
		$this->assertSame(0, $result['total']);
		$this->assertSame('0.00', $result['total_debit']);
		$this->assertSame('0.00', $result['total_credit']);
	}

	public function test_get_subledger_account_balances_empty_for_inactive_user(): void
	{
		$this->assertSame([], $this->ledger->get_subledger_account_balances(99));
	}

	public function test_get_subledger_account_balances_groups_by_account(): void
	{
		// User 7 has activity in account 2 (POINTS) and account 4 (GOLD)
		$this->ledger->create_entry(1700008000, 'POINTS in', [
			['account_id' => 3, 'debit' => '40.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '40.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700008100, 'GOLD swap', [
			['account_id' => 4, 'debit' => '15.00', 'credit' => '0.00',  'subledger_user_id' => 9, 'memo' => ''],
			['account_id' => 4, 'debit' => '0.00',  'credit' => '15.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		$rows = $this->ledger->get_subledger_account_balances(7);
		$this->assertCount(2, $rows);

		// Sorted by account_code: '2100' before '2200'
		$this->assertSame('2100', $rows[0]['account_code']);
		$this->assertSame('POINTS', $rows[0]['currency_code']);
		$this->assertSame('40.00', $rows[0]['period_credit']);
		$this->assertSame('40.00', $rows[0]['closing']);

		$this->assertSame('2200', $rows[1]['account_code']);
		$this->assertSame('GOLD', $rows[1]['currency_code']);
		$this->assertSame('15.00', $rows[1]['period_credit']);
		$this->assertSame('15.00', $rows[1]['closing']);
	}

	public function test_get_trial_balance_respects_as_of_cutoff(): void
	{
		// Two entries, one before and one after the cutoff. A trial balance
		// dated at the cutoff should only include the earlier one.
		$this->ledger->create_entry(1700100000, 'before', [
			['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		$this->ledger->create_entry(1700200000, 'after', [
			['account_id' => 3, 'debit' => '99.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '99.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		// Cutoff midway between the two: only the first entry counts.
		$tb = $this->ledger->get_trial_balance(1700150000);

		$totals = [];
		foreach ($tb['POINTS'] as $row)
		{
			$totals[$row['account_code']] = [
				'dr' => $row['debit_total'],
				'cr' => $row['credit_total'],
			];
		}

		$this->assertSame('10.00', $totals['5050']['dr'], 'expense account should only show pre-cutoff debit');
		$this->assertSame('0.00',  $totals['5050']['cr']);
		$this->assertSame('0.00',  $totals['2100']['dr']);
		$this->assertSame('10.00', $totals['2100']['cr'], 'wallet account should only show pre-cutoff credit');
	}

	public function test_get_trial_balance_includes_accounts_with_no_lines(): void
	{
		// No journal entries exist; every seeded account should still appear
		// with 0/0 totals in the trial balance.
		$tb = $this->ledger->get_trial_balance();

		$this->assertArrayHasKey('POINTS', $tb);
		$codes = array_column($tb['POINTS'], 'account_code');
		$this->assertContains('1010', $codes);
		$this->assertContains('5050', $codes);
		foreach ($tb['POINTS'] as $row)
		{
			$this->assertSame('0.00', $row['debit_total']);
			$this->assertSame('0.00', $row['credit_total']);
		}
	}

	public function test_create_entry_rejects_inactive_currency(): void
	{
		// Deactivate POINTS in the test currencies table; account #2 still
		// points to POINTS so the guard should kick in even though all the
		// other invariants are satisfied.
		$this->db->sql_query("UPDATE {$this->currencies_table} SET is_active = 0 WHERE currency_code = 'POINTS'");

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('currency POINTS is unknown or inactive');

		$this->ledger->create_entry(time(), 'After deactivation', [
			['account_id' => 3, 'debit' => '5.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '5.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_create_entry_rejects_unknown_currency(): void
	{
		// Account #2 references POINTS; remove POINTS from currencies entirely.
		$this->db->sql_query("DELETE FROM {$this->currencies_table} WHERE currency_code = 'POINTS'");

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('currency POINTS is unknown or inactive');

		$this->ledger->create_entry(time(), 'Unknown currency', [
			['account_id' => 3, 'debit' => '5.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '5.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
	}

	public function test_reverse_entry_works_after_currency_deactivated(): void
	{
		// Post while currency is active
		$journal_id = $this->ledger->create_entry(time(), 'Before deactivation', [
			['account_id' => 3, 'debit' => '8.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '8.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		// Deactivate the currency, then prove reversal still succeeds —
		// the audit trail must be salvageable even after a pool is retired.
		$this->db->sql_query("UPDATE {$this->currencies_table} SET is_active = 0 WHERE currency_code = 'POINTS'");

		$reverse_id = $this->ledger->reverse_entry($journal_id);
		$this->assertGreaterThan($journal_id, $reverse_id);

		// The user's wallet should have netted back to zero
		$this->assertSame('0.00', $this->ledger->get_subledger_balance(2, 7));
	}

	public function test_get_subledger_account_balances_period_window_isolates_movement(): void
	{
		// Pre-window entry establishes opening
		$this->ledger->create_entry(1700009000, 'opening', [
			['account_id' => 3, 'debit' => '100.00', 'credit' => '0.00',   'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',   'credit' => '100.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		// In-window entry is the only thing the period totals should see
		$this->ledger->create_entry(1700010000, 'in window', [
			['account_id' => 3, 'debit' => '50.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);
		// Post-window entry must not appear in period or closing-at-window
		$this->ledger->create_entry(1700011000, 'after', [
			['account_id' => 3, 'debit' => '7.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
			['account_id' => 2, 'debit' => '0.00', 'credit' => '7.00', 'subledger_user_id' => 7, 'memo' => ''],
		]);

		$rows = $this->ledger->get_subledger_account_balances(7, 1700009500, 1700010500);
		$this->assertCount(1, $rows);
		$this->assertSame('100.00', $rows[0]['opening']);
		$this->assertSame('50.00',  $rows[0]['period_credit']);
		$this->assertSame('0.00',   $rows[0]['period_debit']);
		$this->assertSame('150.00', $rows[0]['closing']);
	}

	// ------------------------------------------------------------------
	// create_account() — public API for consumer extensions to seed
	// their chart-of-accounts entries at install time, instead of
	// INSERTing directly into bbaccounts_accounts.
	// ------------------------------------------------------------------

	public function test_create_account_returns_persisted_id(): void
	{
		$id = $this->ledger->create_account('5100', 'Posting Rewards', 'expense');

		$this->assertGreaterThan(6, $id, 'New account_id must be greater than the highest fixture id (6).');

		$row = $this->fetch_account($id);
		$this->assertNotNull($row);
		$this->assertSame('5100',            $row['account_code']);
		$this->assertSame('Posting Rewards', $row['account_name']);
		$this->assertSame('expense',         $row['account_type']);
		$this->assertSame('POINTS',          $row['currency_code']);
		$this->assertSame('',                $row['subledger_type']);
		$this->assertSame(1,                 (int) $row['is_active']);
	}

	public function test_create_account_persists_subledger_and_currency_overrides(): void
	{
		$id = $this->ledger->create_account('2110', 'Bank Holdings', 'liability', 'GOLD', 0, 'customer', false);

		$row = $this->fetch_account($id);
		$this->assertSame('GOLD',     $row['currency_code']);
		$this->assertSame('customer', $row['subledger_type']);
		$this->assertSame(0,          (int) $row['is_active']);
	}

	public function test_create_account_rejects_empty_code(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('account_code');
		$this->ledger->create_account('', 'X', 'expense');
	}

	public function test_create_account_rejects_empty_name(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('account_name');
		$this->ledger->create_account('5101', '', 'expense');
	}

	public function test_create_account_rejects_invalid_account_type(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('account_type');
		$this->ledger->create_account('5102', 'Bogus', 'profit');
	}

	public function test_create_account_rejects_invalid_subledger_type(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('subledger_type');
		$this->ledger->create_account('5103', 'Bogus', 'expense', 'POINTS', 0, 'employee');
	}

	public function test_create_account_rejects_duplicate_code(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("'2100'");
		// 2100 already exists in the fixture (User Wallets).
		$this->ledger->create_account('2100', 'Duplicate', 'liability');
	}

	public function test_create_account_rejects_unknown_currency(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('NEVER');
		$this->ledger->create_account('5104', 'Bogus', 'expense', 'NEVER');
	}

	public function test_create_account_rejects_inactive_currency(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('RETIRED');
		// RETIRED is in the fixture with is_active=0.
		$this->ledger->create_account('5105', 'Bogus', 'expense', 'RETIRED');
	}

	public function test_create_account_rejects_unknown_parent_id(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('parent_id');
		$this->ledger->create_account('5106', 'Bogus', 'expense', 'POINTS', 99999);
	}

	public function test_create_account_accepts_known_parent_id(): void
	{
		// 3 = "Points Granted - Admin" expense from fixture; valid parent.
		$id = $this->ledger->create_account('5050.1', 'Sub-bucket', 'expense', 'POINTS', 3);
		$row = $this->fetch_account($id);
		$this->assertSame(3, (int) $row['parent_id']);
	}

	private function fetch_account(int $account_id): ?array
	{
		$sql = 'SELECT account_code, account_name, account_type, parent_id,
		               currency_code, subledger_type, is_active
		        FROM ' . $this->accounts_table . '
		        WHERE account_id = ' . $account_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		return $row ?: null;
	}

	// ------------------------------------------------------------------
	// list_accounts() — read-side enumeration for consumer extensions
	// that need to populate UI pickers (e.g. UltimatePoints' bbAccounts
	// mapping ACP page). Filters on type and subledger_type.
	//
	// Subledger filter is nullable so callers can distinguish "no filter
	// at all" (null) from "match accounts with empty subledger" ('').
	// ------------------------------------------------------------------

	public function test_list_accounts_returns_all_when_no_filter(): void
	{
		$rows = $this->ledger->list_accounts();
		$this->assertCount(6, $rows, 'Fixture has 6 accounts; list_accounts() with no filter should return them all (incl. inactive).');
	}

	public function test_list_accounts_orders_by_account_code(): void
	{
		$rows = $this->ledger->list_accounts();
		$codes = array_column($rows, 'account_code');
		$this->assertSame(['1010', '2100', '2200', '5050', '6500', '9999'], $codes);
	}

	public function test_list_accounts_includes_inactive_accounts(): void
	{
		// Account_id=5 (code 9999) is is_active=0 in the fixture.
		// Inactive accounts MUST still surface — admins may need to
		// re-map a role to a previously-disabled account, and reports
		// of historic balances reference inactive accounts.
		$rows = $this->ledger->list_accounts();
		$inactive = array_filter($rows, fn($r) => (int) $r['account_id'] === 5);
		$this->assertCount(1, $inactive);
		$this->assertSame(0, (int) reset($inactive)['is_active']);
	}

	public function test_list_accounts_returns_expected_columns(): void
	{
		$rows = $this->ledger->list_accounts('asset');
		$this->assertNotEmpty($rows);
		$row = $rows[0];
		foreach (['account_id', 'account_code', 'account_name', 'account_type',
		          'currency_code', 'subledger_type', 'is_active', 'parent_id'] as $col)
		{
			$this->assertArrayHasKey($col, $row, "Missing column: $col");
		}
	}

	public function test_list_accounts_filters_by_account_type(): void
	{
		$rows = $this->ledger->list_accounts('liability');
		$ids = array_map('intval', array_column($rows, 'account_id'));
		sort($ids);
		// Fixture: id=2 (User Wallets, POINTS), id=4 (Gold Wallets, GOLD).
		$this->assertSame([2, 4], $ids);
	}

	public function test_list_accounts_filters_by_subledger_customer(): void
	{
		$rows = $this->ledger->list_accounts('', 'customer');
		$ids = array_map('intval', array_column($rows, 'account_id'));
		sort($ids);
		$this->assertSame([2, 4], $ids);
	}

	public function test_list_accounts_filters_by_empty_subledger(): void
	{
		// subledger_type = '' (not null) means "match accounts with no subledger".
		$rows = $this->ledger->list_accounts('', '');
		$ids = array_map('intval', array_column($rows, 'account_id'));
		sort($ids);
		// Fixture: id=1 Cash, id=3 Points Granted, id=5 Inactive, id=6 Gold Treasury.
		$this->assertSame([1, 3, 5, 6], $ids);
	}

	public function test_list_accounts_combines_type_and_subledger_filters(): void
	{
		$rows = $this->ledger->list_accounts('liability', 'customer');
		$ids = array_map('intval', array_column($rows, 'account_id'));
		sort($ids);
		$this->assertSame([2, 4], $ids);
	}

	public function test_list_accounts_returns_empty_array_when_no_match(): void
	{
		// No equity accounts in fixture.
		$rows = $this->ledger->list_accounts('equity');
		$this->assertSame([], $rows);
	}

	public function test_list_accounts_rejects_invalid_account_type(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('account_type');
		$this->ledger->list_accounts('profit');
	}

	public function test_list_accounts_rejects_invalid_subledger_type(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('subledger_type');
		$this->ledger->list_accounts('', 'employee');
	}
}
