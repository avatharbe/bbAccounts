<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\controller;

/**
 * User-side controller for the UCP "My Wallet" + "My Statement"
 * surfaces. Read-only. Every query is keyed to the logged-in user; the
 * UCP module auth (`ext_avathar/bbaccounts`) gates page reachability
 * to any authenticated user, so this controller doesn't re-check —
 * phpBB has already gated by the time `main()` dispatches here.
 */
class ucp_controller
{
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\request\request */
	protected $request;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\user */
	protected $user;
	/** @var \avathar\bbaccounts\service\ledger */
	protected $ledger;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var string */
	protected $accounts_table;
	/** @var string */
	protected $root_path;
	/** @var string */
	protected $php_ext;

	/** @var string */
	protected $u_action = '';

	public function __construct(
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\avathar\bbaccounts\service\ledger $ledger,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\pagination $pagination,
		string $accounts_table,
		string $root_path,
		string $php_ext
	)
	{
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->ledger = $ledger;
		$this->db = $db;
		$this->pagination = $pagination;
		$this->accounts_table = $accounts_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Build a URL targeting our UCP statement mode with paging /
	 * filter params. Only used by the pagination helper — every other
	 * URL on the UCP surfaces is either implicit (form action defaults
	 * to current URL) or supplied by phpBB (`$this->u_action`).
	 *
	 * `append_sid()` needs the full path + extension
	 * (`./ucp.php`); passing just `'ucp'` gives a relative URL with no
	 * extension, which the browser 404s.
	 */
	protected function statement_url(array $extra = []): string
	{
		$params = 'i=-avathar-bbaccounts-ucp-main_module&amp;mode=statement';
		foreach ($extra as $key => $value)
		{
			$params .= '&amp;' . $key . '=' . urlencode((string) $value);
		}
		return append_sid("{$this->root_path}ucp.{$this->php_ext}", $params);
	}

	public function set_action(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	public function display_wallet(): void
	{
		$this->language->add_lang(['info_ucp_bbaccounts', 'info_acp_bbaccounts'], 'avathar/bbaccounts');

		$user_id = (int) $this->user->data['user_id'];
		$balances = $this->ledger->get_subledger_account_balances($user_id);

		foreach ($balances as $account)
		{
			$is_abnormal = bccomp((string) $account['closing'], '0', 2) < 0;
			$this->template->assign_block_vars('balances', [
				'CODE'        => $account['account_code'],
				'NAME'        => $account['account_name'],
				'CURRENCY'    => $account['currency_code'],
				'BALANCE'     => $account['closing'],
				'IS_ABNORMAL' => $is_abnormal,
			]);
		}

		$this->template->assign_vars([
			'S_HAS_BALANCES'   => !empty($balances),
			'U_ACTION'         => $this->u_action,
			'S_BBACCOUNTS_PAGE' => true,
		]);
	}

	/**
	 * Paginated own-subledger statement. Mirrors the ACP statement view
	 * (controller/acp_controller.php::display_subledger_statement) but
	 * keys on the logged-in user_id rather than a free-form picker, and
	 * omits the user-id resolution / username header.
	 */
	public function display_statement(): void
	{
		$this->language->add_lang(['info_ucp_bbaccounts', 'info_acp_bbaccounts'], 'avathar/bbaccounts');

		$user_id  = (int) $this->user->data['user_id'];
		$from_iso = $this->request->variable('from', '');
		$to_iso   = $this->request->variable('to', '');
		// phpBB convention: pagination URLs carry a 0-based `start` offset,
		// not a 1-based page number. We derive the 1-based page only for
		// the ledger service call (which expects page-number) and for the
		// "Page X / Y" indicator. Reading `page` from the request would
		// double-fail because phpBB's generate_template_pagination uses
		// the same URL param for the offset — clicking next would set
		// page=25 (meaning offset 25), which read as a 1-based page would
		// land us at offset 600.
		$start    = max(0, $this->request->variable('start', 0));
		$per_page = $this->request->variable('per_page', 25);
		if (!in_array($per_page, [10, 25, 50, 100], true))
		{
			$per_page = 25;
		}
		$page = (int) floor($start / $per_page) + 1;

		$from = $from_iso !== '' ? (int) strtotime($from_iso . ' UTC') : 0;
		$to   = $to_iso   !== '' ? (int) strtotime($to_iso . ' 23:59:59 UTC') : 0;

		$summary = $this->ledger->get_subledger_account_balances($user_id, $from, $to);
		foreach ($summary as $row)
		{
			$this->template->assign_block_vars('sl_summary', [
				'CODE'     => $row['account_code'],
				'NAME'     => $row['account_name'],
				'CURRENCY' => $row['currency_code'],
				'OPENING'  => $row['opening'],
				'DEBIT'    => $row['period_debit'],
				'CREDIT'   => $row['period_credit'],
				'CLOSING'  => $row['closing'],
			]);
		}

		$result = $this->ledger->get_subledger_statement($user_id, $from, $to, $page, $per_page);
		$accounts_map = $this->load_accounts_map();

		foreach ($result['rows'] as $row)
		{
			$aid = (int) $row['account_id'];
			$account = $accounts_map[$aid] ?? null;
			$this->template->assign_block_vars('sl_lines', [
				'ENTRY_DATE'       => date('Y-m-d', (int) $row['entry_date']),
				'JOURNAL_ID'       => (int) $row['journal_id'],
				'DESCRIPTION'      => $row['description'],
				'ACCOUNT_CODE'     => $account ? $account['account_code'] : (string) $aid,
				'ACCOUNT_NAME'     => $account ? $account['account_name'] : '',
				'DEBIT'            => $row['debit'],
				'CREDIT'           => $row['credit'],
				'MEMO'             => $row['memo'],
				'REFERENCE_TYPE'   => $row['reference_type'],
				'REFERENCE_SOURCE' => $row['reference_source'],
				'REFERENCE_ID'     => (int) $row['reference_id'],
				'IS_REVERSAL'      => (int) $row['reversal_of'] !== 0,
			]);
		}

		$total_pages = (int) max(1, (int) ceil($result['total'] / $per_page));
		$base_url = $this->statement_url([
			'from'     => $from_iso,
			'to'       => $to_iso,
			'per_page' => $per_page,
		]);

		$this->pagination->generate_template_pagination(
			$base_url,
			'pagination',
			'start',
			$result['total'],
			$per_page,
			$start
		);

		$this->template->assign_vars([
			'S_HAS_SUMMARY'           => !empty($summary),
			'S_HAS_RESULTS'           => !empty($result['rows']),
			'SL_FROM_ISO'             => $from_iso,
			'SL_TO_ISO'               => $to_iso,
			'SL_PER_PAGE'             => $per_page,
			'SL_TOTAL_DR'             => $result['total_debit'],
			'SL_TOTAL_CR'             => $result['total_credit'],
			'SL_TOTAL_ROWS'           => $result['total'],
			'SL_PAGE'                 => $page,
			'SL_TOTAL_PAGES'          => $total_pages,
			'U_ACTION'                => $this->u_action,
			'S_BBACCOUNTS_PAGE'       => true,
			'S_BBACCOUNTS_DATEPICKER' => true,
		]);
	}

	/**
	 * Index of every account in the chart, keyed by account_id, used to
	 * label per-line account info on the statement view.
	 */
	protected function load_accounts_map(): array
	{
		$sql = 'SELECT account_id, account_code, account_name, account_type, currency_code, is_active
		        FROM ' . $this->accounts_table . '
		        ORDER BY currency_code, account_code';
		$result = $this->db->sql_query($sql);
		$map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[(int) $row['account_id']] = $row;
		}
		$this->db->sql_freeresult($result);
		return $map;
	}
}
