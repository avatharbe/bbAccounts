<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\tests\service;

class ledger_anonymize_player_test extends \avathar\bbaccounts\tests\bbaccounts_test_case
{
	protected function seed_expense_account(): int
	{
		return $this->ledger->create_account('5000', 'Expenses', 'expense', 'POINTS', 0, '');
	}

	protected function seed_character_account(): int
	{
		return $this->ledger->create_account('7000', 'Player Wallets', 'liability', 'POINTS', 0, 'character');
	}

	protected function seed_customer_account(): int
	{
		return $this->ledger->create_account('2100', 'User Wallets', 'liability', 'POINTS', 0, 'customer');
	}

	protected function post_character_entry(int $expense_id, int $wallet_id, int $player_id, string $amount): int
	{
		return $this->ledger->create_entry(
			time(),
			'test',
			[
				['account_id' => $expense_id, 'debit' => $amount, 'credit' => '0.00',   'subledger_user_id' => 0, 'subledger_player_id' => 0,          'memo' => ''],
				['account_id' => $wallet_id,  'debit' => '0.00',  'credit' => $amount,  'subledger_user_id' => 0, 'subledger_player_id' => $player_id, 'memo' => ''],
			]
		);
	}

	public function test_anonymize_player_subledger_rewrites_matching_rows(): void
	{
		$exp    = $this->seed_expense_account();
		$wallet = $this->seed_character_account();
		$this->post_character_entry($exp, $wallet, 42, '50.00');
		$this->post_character_entry($exp, $wallet, 99, '30.00');

		$rows = $this->ledger->anonymize_player_subledger(42);
		$this->assertSame(1, $rows);

		// Player 42 lines should now have player_id = 0
		$sql = "SELECT COUNT(*) AS c FROM {$this->lines_table} WHERE subledger_player_id = 42";
		$result = $this->db->sql_query($sql);
		$count_42 = (int) $this->db->sql_fetchfield('c');
		$this->db->sql_freeresult($result);
		$this->assertSame(0, $count_42);

		// Player 99 lines untouched
		$sql = "SELECT COUNT(*) AS c FROM {$this->lines_table} WHERE subledger_player_id = 99";
		$result = $this->db->sql_query($sql);
		$count_99 = (int) $this->db->sql_fetchfield('c');
		$this->db->sql_freeresult($result);
		$this->assertSame(1, $count_99);
	}

	public function test_anonymize_player_subledger_supports_custom_replacement(): void
	{
		$exp    = $this->seed_expense_account();
		$wallet = $this->seed_character_account();
		$this->post_character_entry($exp, $wallet, 42, '50.00');

		$this->ledger->anonymize_player_subledger(42, 7);

		$sql = "SELECT subledger_player_id FROM {$this->lines_table} WHERE subledger_player_id = 7";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		$this->assertSame('7', (string) $row['subledger_player_id']);
	}

	public function test_anonymize_player_subledger_throws_on_zero(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->ledger->anonymize_player_subledger(0);
	}

	public function test_anonymize_player_subledger_throws_on_negative(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->ledger->anonymize_player_subledger(-1);
	}

	public function test_anonymize_player_subledger_does_not_touch_user_subledger(): void
	{
		$exp    = $this->seed_expense_account();
		$wallet = $this->seed_customer_account();

		// Post a CUSTOMER entry for user 42 (NOT player 42)
		$this->ledger->create_entry(
			time(),
			'cust',
			[
				['account_id' => $exp,    'debit' => '20.00', 'credit' => '0.00',  'subledger_user_id' => 0,  'subledger_player_id' => 0, 'memo' => ''],
				['account_id' => $wallet, 'debit' => '0.00',  'credit' => '20.00', 'subledger_user_id' => 42, 'subledger_player_id' => 0, 'memo' => ''],
			]
		);

		// Anonymize player 42 — should NOT affect the user 42 customer line
		$this->ledger->anonymize_player_subledger(42);

		$sql = "SELECT COUNT(*) AS c FROM {$this->lines_table} WHERE subledger_user_id = 42";
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('c');
		$this->db->sql_freeresult($result);
		$this->assertSame(1, $count, 'customer-subledger line for user 42 must be untouched');
	}
}
