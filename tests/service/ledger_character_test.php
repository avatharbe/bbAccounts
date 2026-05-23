<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\tests\service;

class ledger_character_test extends \avathar\bbaccounts\tests\bbaccounts_test_case
{
	protected function ledger(): \avathar\bbaccounts\service\ledger
	{
		global $phpbb_container;
		return $phpbb_container->get('avathar.bbaccounts.service.ledger');
	}

	public function test_create_account_accepts_character_subledger_type(): void
	{
		$id = $this->ledger()->create_account(
			'7000',
			'DKP Pool 1 Wallets',
			'liability',
			'POINTS',
			0,
			'character'
		);

		$this->assertGreaterThan(0, $id);
	}

	/**
	 * Seed: one of each account type used in the validation tests below.
	 *
	 * @return array{cash:int, wallets_c:int, wallets_p:int, exp:int}
	 */
	protected function seed_accounts(): array
	{
		return [
			'cash'      => $this->ledger()->create_account('1000', 'Cash',           'asset',     'POINTS', 0, ''),
			'wallets_c' => $this->ledger()->create_account('2100', 'User Wallets',   'liability', 'POINTS', 0, 'customer'),
			'wallets_p' => $this->ledger()->create_account('7100', 'Player Wallets', 'liability', 'POINTS', 0, 'character'),
			'exp'       => $this->ledger()->create_account('5000', 'Expenses',       'expense',   'POINTS', 0, ''),
		];
	}

	public function test_create_entry_accepts_character_account_with_player_id(): void
	{
		$a = $this->seed_accounts();
		$journal_id = $this->ledger()->create_entry(
			time(),
			'raid award',
			[
				['account_id' => $a['exp'],       'debit' => '50.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'subledger_player_id' => 0,  'memo' => ''],
				['account_id' => $a['wallets_p'], 'debit' => '0.00',  'credit' => '50.00', 'subledger_user_id' => 0, 'subledger_player_id' => 42, 'memo' => ''],
			]
		);
		$this->assertGreaterThan(0, $journal_id);
	}

	public function test_create_entry_rejects_character_account_missing_player_id(): void
	{
		$a = $this->seed_accounts();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('requires a subledger_player_id');
		$this->ledger()->create_entry(
			time(),
			'bad',
			[
				['account_id' => $a['exp'],       'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'subledger_player_id' => 0, 'memo' => ''],
				['account_id' => $a['wallets_p'], 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 0, 'subledger_player_id' => 0, 'memo' => ''],
			]
		);
	}

	public function test_create_entry_rejects_character_account_with_user_id(): void
	{
		$a = $this->seed_accounts();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('accepts subledger_player_id, not subledger_user_id');
		$this->ledger()->create_entry(
			time(),
			'bad',
			[
				['account_id' => $a['exp'],       'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0,  'subledger_player_id' => 0,  'memo' => ''],
				['account_id' => $a['wallets_p'], 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 99, 'subledger_player_id' => 0,  'memo' => ''],
			]
		);
	}

	public function test_create_entry_rejects_customer_account_with_player_id(): void
	{
		$a = $this->seed_accounts();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('accepts subledger_user_id, not subledger_player_id');
		$this->ledger()->create_entry(
			time(),
			'bad',
			[
				['account_id' => $a['exp'],       'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'subledger_player_id' => 0,  'memo' => ''],
				['account_id' => $a['wallets_c'], 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 0, 'subledger_player_id' => 42, 'memo' => ''],
			]
		);
	}

	public function test_create_entry_rejects_non_subledger_account_with_player_id(): void
	{
		$a = $this->seed_accounts();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('does not accept');
		$this->ledger()->create_entry(
			time(),
			'bad',
			[
				['account_id' => $a['cash'], 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0, 'subledger_player_id' => 42, 'memo' => ''],
				['account_id' => $a['exp'],  'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 0, 'subledger_player_id' => 0,  'memo' => ''],
			]
		);
	}
}
