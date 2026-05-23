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
}
