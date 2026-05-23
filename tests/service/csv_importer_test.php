<?php
/**
 * bbAccounts — CSV importer service tests.
 *
 * Covers the parse + validate surface (parse_and_validate / is_clean) and
 * the transactional rollback contract that the ACP controller relies on.
 * Schema and accounts come from the same fixture the ledger tests use, so
 * the sample inputs reference real account_codes (1010, 2100, 5050,
 * 2200 GOLD, 9999 inactive) — see tests/fixtures/accounts.xml.
 */

namespace avathar\bbaccounts\tests\service;

class csv_importer_test extends \avathar\bbaccounts\tests\database_test_case
{
	/** @var \avathar\bbaccounts\service\csv_importer */
	protected $importer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->importer = new \avathar\bbaccounts\service\csv_importer(
			$this->db,
			$this->accounts_table
		);
	}

	public function test_balanced_multi_entry_parses_clean(): void
	{
		// Both entries use non-subledger accounts so the test stays self-
		// contained (no users fixture required).
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "A,2026-05-01,First entry,1010,100.00,0\n"
			. "A,2026-05-01,First entry,5050,0,100.00\n"
			. "B,2026-05-02,Second entry,1010,50.00,0\n"
			. "B,2026-05-02,Second entry,5050,0,50.00\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertSame([], $parsed['global_errors']);
		self::assertCount(2, $parsed['entries']);
		self::assertTrue($this->importer->is_clean($parsed));
		self::assertSame('100.00', $parsed['entries']['A']['totals']['debit']);
		self::assertSame('100.00', $parsed['entries']['A']['totals']['credit']);
	}

	public function test_unbalanced_entry_rejected(): void
	{
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "X,2026-05-01,Bad,1010,100.00,0\n"
			. "X,2026-05-01,Bad,5050,0,90.00\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		self::assertNotEmpty($parsed['entries']['X']['errors']);
		self::assertStringContainsString('unbalanced', $parsed['entries']['X']['errors'][0]);
	}

	public function test_missing_account_code_rejected(): void
	{
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "X,2026-05-01,Bad,9000,100.00,0\n"
			. "X,2026-05-01,Bad,5050,0,100.00\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		$found = false;
		foreach ($parsed['entries']['X']['lines'] as $line)
		{
			foreach ($line['errors'] as $msg)
			{
				if (str_contains($msg, 'unknown account_code'))
				{
					$found = true;
				}
			}
		}
		self::assertTrue($found, 'expected an unknown-account_code error on the offending line');
	}

	public function test_inactive_account_rejected(): void
	{
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "X,2026-05-01,Bad,9999,100.00,0\n"
			. "X,2026-05-01,Bad,5050,0,100.00\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		$found = false;
		foreach ($parsed['entries']['X']['lines'] as $line)
		{
			foreach ($line['errors'] as $msg)
			{
				if (str_contains($msg, 'inactive'))
				{
					$found = true;
				}
			}
		}
		self::assertTrue($found, 'expected inactive-account error on the offending line');
	}

	public function test_mixed_currencies_in_one_entry_rejected(): void
	{
		// 1010 is POINTS, 6500 is GOLD — both non-subledger so the test
		// hits the currency-mix branch without crossing the user lookup.
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "X,2026-05-01,Mixed,1010,100.00,0\n"
			. "X,2026-05-01,Mixed,6500,0,100.00\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		$found = false;
		foreach ($parsed['entries']['X']['errors'] as $msg)
		{
			if (str_contains($msg, 'mixes currencies'))
			{
				$found = true;
			}
		}
		self::assertTrue($found, 'expected mixed-currencies error on the entry');
	}

	public function test_subledger_account_missing_user_id_rejected(): void
	{
		$csv = "entry_ref,entry_date,description,account_code,debit,credit,subledger_user_id\n"
			. "X,2026-05-01,Sub,1010,100.00,0,0\n"
			. "X,2026-05-01,Sub,2100,0,100.00,0\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		$found = false;
		foreach ($parsed['entries']['X']['lines'] as $line)
		{
			foreach ($line['errors'] as $msg)
			{
				if (str_contains($msg, 'requires subledger_user_id'))
				{
					$found = true;
				}
			}
		}
		self::assertTrue($found, 'expected subledger-user-id-required error on the line');
	}

	public function test_character_subledger_with_player_id_parses_clean(): void
	{
		// Account 7100 has subledger_type='character' (see fixtures/accounts.xml).
		// Non-subledger account 1010 paired with character account 7100 (player_id=42).
		$csv = "entry_ref,entry_date,description,account_code,debit,credit,subledger_player_id\n"
			. "C,2026-05-23,Char award,1010,50.00,0,0\n"
			. "C,2026-05-23,Char award,7100,0,50.00,42\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertSame([], $parsed['global_errors']);
		self::assertTrue($this->importer->is_clean($parsed), 'CSV with subledger_player_id should validate clean');
	}

	public function test_character_account_missing_player_id_rejected(): void
	{
		$csv = "entry_ref,entry_date,description,account_code,debit,credit,subledger_player_id\n"
			. "C,2026-05-23,Bad,1010,50.00,0,0\n"
			. "C,2026-05-23,Bad,7100,0,50.00,0\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		$found = false;
		foreach ($parsed['entries']['C']['lines'] as $line)
		{
			foreach ($line['errors'] as $msg)
			{
				if (str_contains($msg, 'requires subledger_player_id'))
				{
					$found = true;
				}
			}
		}
		self::assertTrue($found, 'expected subledger-player-id-required error on the line');
	}

	public function test_customer_account_with_player_id_rejected(): void
	{
		// Account 2100 is customer-subledger; supplying subledger_player_id on
		// it must be rejected by the mutual-exclusion rule.
		$csv = "entry_ref,entry_date,description,account_code,debit,credit,subledger_user_id,subledger_player_id\n"
			. "C,2026-05-23,Bad,1010,50.00,0,0,0\n"
			. "C,2026-05-23,Bad,2100,0,50.00,0,42\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertFalse($this->importer->is_clean($parsed));
		$found = false;
		foreach ($parsed['entries']['C']['lines'] as $line)
		{
			foreach ($line['errors'] as $msg)
			{
				if (str_contains($msg, 'accepts subledger_user_id, not subledger_player_id'))
				{
					$found = true;
				}
			}
		}
		self::assertTrue($found, 'expected mutual-exclusion error on the customer line');
	}

	public function test_csv_omitting_player_id_column_still_works(): void
	{
		// Backward compatibility: CSV without the new optional column imports cleanly.
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "B,2026-05-23,Back-compat,1010,10.00,0\n"
			. "B,2026-05-23,Back-compat,5050,0,10.00\n";

		$parsed = $this->importer->parse_and_validate($csv);
		self::assertSame([], $parsed['global_errors']);
		self::assertTrue($this->importer->is_clean($parsed));
	}

	public function test_malformed_csv_missing_required_column_rejected(): void
	{
		// Header lacks 'credit' — global error short-circuits parsing.
		$csv = "entry_ref,entry_date,description,account_code,debit\n"
			. "X,2026-05-01,Bad,1010,100.00\n";

		$parsed = $this->importer->parse_and_validate($csv);

		self::assertNotEmpty($parsed['global_errors']);
		self::assertSame([], $parsed['entries']);
		self::assertFalse($this->importer->is_clean($parsed));
		self::assertStringContainsString('credit', $parsed['global_errors'][0]);
	}

	/**
	 * Mid-file failure: the first entry is valid and posts; the second
	 * entry is then forced to throw at create_entry() time. The whole
	 * import must roll back, leaving zero rows in the journal table.
	 *
	 * Drives the ledger directly inside sql_transaction to mirror the
	 * controller's process_csv_import_commit() flow at the service layer.
	 */
	public function test_mid_file_create_entry_failure_rolls_back(): void
	{
		// Probe whether the storage engine actually rolls back. CI runs a
		// MyISAM matrix job; rollback is a no-op there, so we'd assert
		// nothing meaningful. Skip cleanly on non-transactional engines.
		$this->db->sql_transaction('begin');
		$this->db->sql_query('INSERT INTO ' . $this->journal_table . ' ' . $this->db->sql_build_array('INSERT', [
			'entry_date'       => 0,
			'description'      => 'tx-probe',
			'reference_type'   => 'manual',
			'reference_source' => '',
			'reference_id'     => 0,
			'created_by'       => 0,
			'created_at'       => 0,
			'reversal_of'      => 0,
		]));
		$this->db->sql_transaction('rollback');
		$probe = (int) $this->db->sql_fetchfield('c', false, $this->db->sql_query(
			'SELECT COUNT(*) AS c FROM ' . $this->journal_table . " WHERE description = 'tx-probe'"
		));
		if ($probe > 0)
		{
			$this->db->sql_query('DELETE FROM ' . $this->journal_table . " WHERE description = 'tx-probe'");
			self::markTestSkipped('storage engine does not roll back transactions (MyISAM matrix entry)');
		}

		$pre = (int) $this->db->sql_fetchfield('c', false, $this->db->sql_query(
			'SELECT COUNT(*) AS c FROM ' . $this->journal_table
		));

		$entries = [
			[
				'date'        => (int) strtotime('2026-05-01 UTC'),
				'description' => 'Good',
				'lines' => [
					['account_id' => 1, 'debit' => '50.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'memo' => ''],
					['account_id' => 3, 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 0, 'memo' => ''],
				],
			],
			// Forced bad entry — unbalanced, will throw inside the loop.
			[
				'date'        => (int) strtotime('2026-05-02 UTC'),
				'description' => 'Bad',
				'lines' => [
					['account_id' => 1, 'debit' => '100.00', 'credit' => '0.00', 'subledger_user_id' => 0, 'memo' => ''],
					['account_id' => 3, 'debit' => '0.00',   'credit' => '90.00', 'subledger_user_id' => 0, 'memo' => ''],
				],
			],
		];

		$threw = false;
		$this->db->sql_transaction('begin');
		try
		{
			foreach ($entries as $entry)
			{
				$this->ledger->create_entry($entry['date'], $entry['description'], $entry['lines']);
			}
			$this->db->sql_transaction('commit');
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			$threw = true;
		}

		$post = (int) $this->db->sql_fetchfield('c', false, $this->db->sql_query(
			'SELECT COUNT(*) AS c FROM ' . $this->journal_table
		));

		self::assertTrue($threw, 'second entry was expected to throw');
		self::assertSame($pre, $post, 'rollback must leave journal row count unchanged');
	}
}
