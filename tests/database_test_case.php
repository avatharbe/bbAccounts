<?php
/**
 * bbAccounts — base for ledger-service DB tests.
 *
 * Extends phpBB's standard phpbb_database_test_case so the test framework
 * builds the bbaccounts schema from migrations, loads the fixture, and
 * provides a connected $this->db via new_dbal().
 */

namespace avathar\bbaccounts\tests;

abstract class database_test_case extends \phpbb_database_test_case
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \avathar\bbaccounts\service\ledger */
	protected $ledger;

	protected $accounts_table   = 'phpbb_bbaccounts_accounts';
	protected $journal_table    = 'phpbb_bbaccounts_journal';
	protected $lines_table      = 'phpbb_bbaccounts_journal_lines';
	protected $currencies_table = 'phpbb_bbaccounts_currencies';

	protected static function setup_extensions()
	{
		return ['avathar/bbaccounts'];
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/accounts.xml');
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = $this->new_dbal();
		$this->ledger = new \avathar\bbaccounts\service\ledger(
			$this->db,
			$this->accounts_table,
			$this->journal_table,
			$this->lines_table,
			$this->currencies_table
		);
	}
}
