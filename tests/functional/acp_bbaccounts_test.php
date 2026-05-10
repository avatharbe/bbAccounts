<?php
/**
 * bbAccounts — ACP functional smoke tests.
 *
 * @group functional
 */

namespace avathar\bbaccounts\tests\functional;

class acp_bbaccounts_test extends \phpbb_functional_test_case
{
	protected static function setup_extensions()
	{
		return ['avathar/bbaccounts'];
	}

	public function test_acp_accounts_mode_loads(): void
	{
		$this->login();
		$this->admin_login();
		$crawler = self::request('GET', 'adm/index.php?i=-avathar-bbaccounts-acp-main_module&mode=accounts&sid=' . $this->sid);
		self::assert_response_status_code(200);
		$this->assertStringContainsString('Chart of Accounts', $crawler->filter('#main h1')->text());
	}

	public function test_acp_journal_mode_loads(): void
	{
		$this->login();
		$this->admin_login();
		$crawler = self::request('GET', 'adm/index.php?i=-avathar-bbaccounts-acp-main_module&mode=journal&sid=' . $this->sid);
		self::assert_response_status_code(200);
		$this->assertStringContainsString('Journal', $crawler->filter('#main h1')->text());
	}

	public function test_acp_reports_mode_loads(): void
	{
		$this->login();
		$this->admin_login();
		$crawler = self::request('GET', 'adm/index.php?i=-avathar-bbaccounts-acp-main_module&mode=reports&sid=' . $this->sid);
		self::assert_response_status_code(200);
		$this->assertStringContainsString('Reports', $crawler->filter('#main h1')->text());
	}

	/**
	 * End-to-end CSV import smoke test: upload → preview → confirm →
	 * result page. The CSV uses only non-subledger seeded accounts so
	 * the test stays self-contained — no extra users need to exist.
	 *
	 * Asserts the journal list grew by exactly the entry count posted.
	 */
	public function test_csv_import_round_trip(): void
	{
		$this->login();
		$this->admin_login();

		$journal_url = 'adm/index.php?i=-avathar-bbaccounts-acp-main_module&mode=journal&sid=' . $this->sid;
		$crawler = self::request('GET', $journal_url);
		$pre_count = $crawler->filter('table.zebra-table tbody tr')->count();

		// Build CSV against the seeded chart: 1010 (Cash on Hand) and
		// 5000 (Expenses). Both POINTS, both non-subledger.
		$csv = "entry_ref,entry_date,description,account_code,debit,credit\n"
			. "FS-T1,2026-05-01,Smoke test 1,1010,10.00,0\n"
			. "FS-T1,2026-05-01,Smoke test 1,5000,0,10.00\n"
			. "FS-T2,2026-05-02,Smoke test 2,1010,20.00,0\n"
			. "FS-T2,2026-05-02,Smoke test 2,5000,0,20.00\n"
			. "FS-T3,2026-05-03,Smoke test 3,1010,30.00,0\n"
			. "FS-T3,2026-05-03,Smoke test 3,5000,0,30.00\n";

		$tmp = tempnam(sys_get_temp_dir(), 'bbcsv_') . '.csv';
		file_put_contents($tmp, $csv);

		try
		{
			$import_url = 'adm/index.php?i=-avathar-bbaccounts-acp-main_module&mode=journal&action=import&sid=' . $this->sid;
			$crawler = self::request('GET', $import_url);
			self::assert_response_status_code(200);

			$form = $crawler->selectButton('submit')->form();
			$form['csv_file']->upload($tmp);
			$crawler = self::submit($form);

			$this->assertStringContainsString('Ready to import', $crawler->filter('#main')->text());

			$confirm_form = $crawler->selectButton('submit_confirm')->form();
			$crawler = self::submit($confirm_form);

			$this->assertStringContainsString('Import complete', $crawler->filter('#main')->text());
			$this->assertSame(3, $crawler->filter('table.zebra-table tbody tr')->count(), 'result page should list 3 created journal_ids');

			$crawler = self::request('GET', $journal_url);
			$post_count = $crawler->filter('table.zebra-table tbody tr')->count();
			$this->assertSame($pre_count + 3, $post_count, 'journal list should grow by exactly the imported entry count');
		}
		finally
		{
			@unlink($tmp);
		}
	}
}
