<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\tests\service;

class ledger_character_balance_test extends \avathar\bbaccounts\tests\bbaccounts_test_case
{
	public function test_returns_empty_for_zero_player_id(): void
	{
		$this->assertSame([], $this->ledger->get_subledger_account_balances_by_character(0));
	}

	public function test_returns_empty_for_negative_player_id(): void
	{
		$this->assertSame([], $this->ledger->get_subledger_account_balances_by_character(-5));
	}

	public function test_returns_per_account_balances_for_character(): void
	{
		$exp    = $this->ledger->create_account('5000', 'Expenses',       'expense',   'POINTS', 0, '');
		$wallet = $this->ledger->create_account('7000', 'Player Wallets', 'liability', 'POINTS', 0, 'character');

		// Award 50 DKP to player 42
		$this->ledger->create_entry(
			time(),
			'award',
			[
				['account_id' => $exp,    'debit' => '50.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'subledger_player_id' => 0,  'memo' => ''],
				['account_id' => $wallet, 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 0, 'subledger_player_id' => 42, 'memo' => ''],
			]
		);

		$balances = $this->ledger->get_subledger_account_balances_by_character(42);

		$this->assertCount(1, $balances);
		$this->assertSame($wallet, $balances[0]['account_id']);
		// Wallet is liability (credit-normal). normal_balance returns cr - dr = 50 - 0 = 50.
		$this->assertSame('50.00', $balances[0]['closing']);
		$this->assertSame('0.00',  $balances[0]['opening']);
		$this->assertSame('0.00',  $balances[0]['period_debit']);
		$this->assertSame('50.00', $balances[0]['period_credit']);
		$this->assertSame('liability', $balances[0]['account_type']);
	}

	public function test_ignores_user_subledger_lines_for_same_numeric_id(): void
	{
		$exp_c    = $this->ledger->create_account('5000', 'Expenses',     'expense',   'POINTS', 0, '');
		// 2100 is already seeded by load_fixture(); use a distinct code.
		$wallet_c = $this->ledger->create_account('2150', 'User Wallets (test)', 'liability', 'POINTS', 0, 'customer');

		// Customer entry — user_id 42, NOT player_id 42
		$this->ledger->create_entry(
			time(),
			'cust',
			[
				['account_id' => $exp_c,    'debit' => '30.00', 'credit' => '0.00',  'subledger_user_id' => 0,  'subledger_player_id' => 0, 'memo' => ''],
				['account_id' => $wallet_c, 'debit' => '0.00',  'credit' => '30.00', 'subledger_user_id' => 42, 'subledger_player_id' => 0, 'memo' => ''],
			]
		);

		// Even though user 42 has activity, querying by player_id 42 returns nothing
		$balances = $this->ledger->get_subledger_account_balances_by_character(42);
		$this->assertSame([], $balances);
	}

	public function test_get_subledger_balance_by_character_returns_zero_for_no_activity(): void
	{
		$wallet = $this->ledger->create_account('7100', 'Empty Wallet', 'liability', 'POINTS', 0, 'character');

		$balance = $this->ledger->get_subledger_balance_by_character($wallet, 99);
		$this->assertSame('0.00', $balance);
	}

	public function test_get_subledger_balance_by_character_throws_on_unknown_account(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->ledger->get_subledger_balance_by_character(999999, 1);
	}
}
