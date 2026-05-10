<?php
/**
 * bbAccounts — Event listener tests.
 *
 * Extends the shared database_test_case so we get a connected $this->db
 * and $this->lines_table against the bbaccounts schema.
 */

namespace avathar\bbaccounts\tests\event;

class listener_test extends \avathar\bbaccounts\tests\database_test_case
{
	public function test_listener_subscribes_to_user_setup_and_delete_user_after(): void
	{
		$events = \avathar\bbaccounts\event\listener::getSubscribedEvents();
		$this->assertArrayHasKey('core.user_setup',              $events);
		$this->assertArrayHasKey('core.delete_user_after',       $events);
		$this->assertArrayHasKey('core.memberlist_view_profile', $events);
		$this->assertArrayHasKey('core.page_header',             $events);
		$this->assertArrayHasKey('core.permissions',             $events);
	}

	public function test_user_delete_anonymizes_subledger_user_id(): void
	{
		// Create a journal entry with subledger lines under user 42.
		$journal_id = $this->ledger->create_entry(
			1700100000,
			'Pre-delete entry',
			[
				['account_id' => 3, 'debit' => '10.00', 'credit' => '0.00',  'subledger_user_id' => 0,  'memo' => ''],
				['account_id' => 2, 'debit' => '0.00',  'credit' => '10.00', 'subledger_user_id' => 42, 'memo' => ''],
			]
		);

		// Find that line's id so we can verify it.
		$sql = "SELECT line_id FROM {$this->lines_table} WHERE journal_id = {$journal_id} AND subledger_user_id = 42";
		$line_id = (int) $this->db->sql_fetchfield('line_id', false, $this->db->sql_query($sql));
		$this->assertGreaterThan(0, $line_id);

		// Capture account-level balances before user deletion. The spec's
		// claim is "Lines remain; balances remain; GL is unaffected" — the
		// anonymisation must only rewrite subledger_user_id, never touch
		// debit/credit amounts.
		$wallet_balance_before  = $this->ledger->get_account_balance(2);
		$expense_balance_before = $this->ledger->get_account_balance(3);

		// Build no-op stubs for the constructor deps the on_user_delete path
		// doesn't actually use. The listener writes only via $this->db and
		// $this->lines_table here.
		$language        = $this->getMockBuilder('\phpbb\language\language')->disableOriginalConstructor()->getMock();
		$auth            = $this->getMockBuilder('\phpbb\auth\auth')->disableOriginalConstructor()->getMock();
		$user            = $this->getMockBuilder('\phpbb\user')->disableOriginalConstructor()->getMock();
		$template        = $this->getMockBuilder('\phpbb\template\template')->disableOriginalConstructor()->getMock();
		$helper          = $this->getMockBuilder('\phpbb\controller\helper')->disableOriginalConstructor()->getMock();
		$balance_summary = $this->getMockBuilder('\avathar\bbaccounts\service\balance_summary')->disableOriginalConstructor()->getMock();

		$listener = new \avathar\bbaccounts\event\listener(
			$language,
			$auth,
			$user,
			$template,
			$helper,
			$balance_summary,
			$this->db,
			$this->lines_table
		);

		$event = new \phpbb\event\data([
			'mode'            => 'remove',
			'user_ids'        => [42],
			'retain_username' => true,
		]);
		$listener->on_user_delete($event);

		// Line's subledger_user_id should now be 1 (ANONYMOUS).
		$sql = "SELECT subledger_user_id FROM {$this->lines_table} WHERE line_id = {$line_id}";
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$this->assertSame('1', $row['subledger_user_id']);

		// GL invariant: account balances must be unchanged by anonymisation.
		$this->assertSame($wallet_balance_before,  $this->ledger->get_account_balance(2), 'wallet account balance changed');
		$this->assertSame($expense_balance_before, $this->ledger->get_account_balance(3), 'expense account balance changed');
	}
}
