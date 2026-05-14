<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\controller;

/**
 * Front-end Reports controller (mod-gated).
 *
 * Read-only mirror of the ACP Reports module. Lifted-and-shifted from
 * `acp_controller`'s display methods (per #84's "lift-and-shift first;
 * refactor to a shared renderer if both controllers grow"); URL
 * generation swapped to phpBB's `controller\helper::route()` so the FE
 * sub-mode buttons + pagination links resolve to `/app.php/bbaccounts/
 * reports/...` rather than the ACP `?i=…&mode=…` shape.
 *
 * Write paths from the ACP (chart of accounts, journal create/reverse,
 * currencies, CSV import) are deliberately NOT mirrored here. End
 * users without `u_accounts_view` get a 403 from the auth gate at
 * the top of `handle()`.
 */
class main_controller
{
	/** @var \phpbb\controller\helper */
	protected $helper;
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\request\request */
	protected $request;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \avathar\bbaccounts\service\ledger */
	protected $ledger;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var string */
	protected $accounts_table;

	public function __construct(
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\avathar\bbaccounts\service\ledger $ledger,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\user_loader $user_loader,
		string $accounts_table
	)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
		$this->ledger = $ledger;
		$this->db = $db;
		$this->user_loader = $user_loader;
		$this->accounts_table = $accounts_table;
	}

	/**
	 * @param string $report Sub-mode name; constrained at the routing layer.
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handle(string $report = 'trial_balance')
	{
		if (!$this->auth->acl_get('u_accounts_view'))
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
		}

		$this->language->add_lang('info_acp_bbaccounts', 'avathar/bbaccounts');

		$this->template->assign_vars([
			'U_REPORT_TRIAL_BALANCE'  => $this->report_url('trial_balance'),
			'U_REPORT_ACCOUNT_LEDGER' => $this->report_url('account_ledger'),
			'U_REPORT_SUBLEDGER'      => $this->report_url('subledger'),
			'U_REPORT_BALANCE_LOOKUP' => $this->report_url('balance_lookup'),
			'U_REPORT_USER_BALANCE'   => $this->report_url('user_balance_lookup'),
			'S_REPORT'                => $report,
			'S_FE_REPORTS'            => true,
			'S_BBACCOUNTS_PAGE'       => true,
			'S_BBACCOUNTS_DATEPICKER' => true,
		]);

		switch ($report)
		{
			case 'account_ledger':
				$this->template->assign_var('S_REPORT_ACCOUNT_LEDGER', true);
				$this->display_account_ledger();
				break;
			case 'subledger':
				$this->template->assign_var('S_REPORT_SUBLEDGER', true);
				$this->display_subledger_statement();
				break;
			case 'balance_lookup':
				$this->template->assign_var('S_REPORT_BALANCE_LOOKUP', true);
				$this->display_balance_lookup();
				break;
			case 'user_balance_lookup':
				$this->template->assign_var('S_REPORT_USER_BALANCE', true);
				$this->display_user_balance_lookup();
				break;
			case 'trial_balance':
			default:
				$this->template->assign_var('S_REPORT_TRIAL_BALANCE', true);
				$this->display_trial_balance();
				break;
		}

		return $this->helper->render('bbaccounts_reports.html', $this->language->lang('BBACCOUNTS_REPORTS_TITLE'));
	}

	protected function report_url(string $report, array $extra = []): string
	{
		return $this->helper->route('avathar_bbaccounts_reports', array_merge(['report' => $report], $extra));
	}

	protected function display_trial_balance(): void
	{
		$as_of_iso = $this->request->variable('as_of', date('Y-m-d'));
		$as_of = (int) strtotime($as_of_iso . ' 23:59:59 UTC');
		$currency_filter = $this->request->variable('currency', '');

		$tb = $this->ledger->get_trial_balance($as_of, $currency_filter);

		$type_order = ['asset', 'liability', 'equity', 'revenue', 'expense'];

		foreach ($tb as $currency => $rows)
		{
			$by_type = [];
			foreach ($rows as $row)
			{
				$by_type[$row['account_type']][] = $row;
			}

			$render_order = $type_order;
			foreach (array_keys($by_type) as $t)
			{
				if (!in_array($t, $render_order, true))
				{
					$render_order[] = $t;
				}
			}

			$bs_types = ['asset', 'liability', 'equity'];
			$pl_types = ['revenue', 'expense'];

			$type_sub = [];
			foreach ($render_order as $type)
			{
				if (empty($by_type[$type]))
				{
					continue;
				}
				$sub_dr = '0.00';
				$sub_cr = '0.00';
				foreach ($by_type[$type] as $row)
				{
					$sub_dr = bcadd($sub_dr, $row['debit_total'],  2);
					$sub_cr = bcadd($sub_cr, $row['credit_total'], 2);
				}
				$type_sub[$type] = ['dr' => $sub_dr, 'cr' => $sub_cr];
			}

			$last_bs = null;
			foreach ($bs_types as $t)
			{
				if (isset($type_sub[$t]))
				{
					$last_bs = $t;
				}
			}
			$last_pl = null;
			foreach ($pl_types as $t)
			{
				if (isset($type_sub[$t]))
				{
					$last_pl = $t;
				}
			}

			$bs_dr = '0.00';
			$bs_cr = '0.00';
			foreach ($bs_types as $t)
			{
				if (!isset($type_sub[$t]))
				{
					continue;
				}
				$bs_dr = bcadd($bs_dr, $type_sub[$t]['dr'], 2);
				$bs_cr = bcadd($bs_cr, $type_sub[$t]['cr'], 2);
			}
			$pl_dr = '0.00';
			$pl_cr = '0.00';
			foreach ($pl_types as $t)
			{
				if (!isset($type_sub[$t]))
				{
					continue;
				}
				$pl_dr = bcadd($pl_dr, $type_sub[$t]['dr'], 2);
				$pl_cr = bcadd($pl_cr, $type_sub[$t]['cr'], 2);
			}
			$pool_dr = bcadd($bs_dr, $pl_dr, 2);
			$pool_cr = bcadd($bs_cr, $pl_cr, 2);

			$this->template->assign_block_vars('pools', [
				'CURRENCY' => $currency,
				'TOTAL_DR' => $pool_dr,
				'TOTAL_CR' => $pool_cr,
				'BALANCED' => bccomp($pool_dr, $pool_cr, 2) === 0,
			]);

			foreach ($render_order as $type)
			{
				if (empty($type_sub[$type]))
				{
					continue;
				}

				$is_last_bs = ($type === $last_bs);
				$is_last_pl = ($type === $last_pl);

				$block = [
					'TYPE_KEY'    => $type,
					'TYPE_LABEL'  => $this->language->lang('BBACCOUNTS_TYPE_' . strtoupper($type)),
					'S_GROUP_END' => $is_last_bs || $is_last_pl,
					'GROUP_LABEL' => '',
					'GROUP_DR'    => '0.00',
					'GROUP_CR'    => '0.00',
				];
				if ($is_last_bs)
				{
					$block['GROUP_LABEL'] = $this->language->lang('BBACCOUNTS_TB_GROUP_BS');
					$block['GROUP_DR']    = $bs_dr;
					$block['GROUP_CR']    = $bs_cr;
				}
				else if ($is_last_pl)
				{
					$block['GROUP_LABEL'] = $this->language->lang('BBACCOUNTS_TB_GROUP_PL');
					$block['GROUP_DR']    = $pl_dr;
					$block['GROUP_CR']    = $pl_cr;
				}
				$this->template->assign_block_vars('pools.types', $block);

				foreach ($by_type[$type] as $row)
				{
					$this->template->assign_block_vars('pools.types.rows', [
						'CODE'   => $row['account_code'],
						'NAME'   => $row['account_name'],
						'TYPE'   => $row['account_type'],
						'DEBIT'  => $row['debit_total'],
						'CREDIT' => $row['credit_total'],
					]);
				}

				$this->template->assign_block_vars('pools.types.subtotal', [
					'DR' => $type_sub[$type]['dr'],
					'CR' => $type_sub[$type]['cr'],
				]);
			}
		}

		$this->template->assign_vars([
			'U_REPORT_FORM_ACTION' => $this->report_url('trial_balance'),
			'AS_OF_ISO'            => $as_of_iso,
			'CURRENCY_FILTER'      => $currency_filter,
		]);
	}

	protected function display_account_ledger(): void
	{
		$account_id = $this->request->variable('account_id', 0);
		$from_iso   = $this->request->variable('from', '');
		$to_iso     = $this->request->variable('to', '');
		$page       = max(1, $this->request->variable('page', 1));
		$per_page   = $this->request->variable('per_page', 25);
		if (!in_array($per_page, [25, 50, 100], true))
		{
			$per_page = 25;
		}

		$from = $from_iso !== '' ? (int) strtotime($from_iso . ' UTC') : 0;
		$to   = $to_iso   !== '' ? (int) strtotime($to_iso . ' 23:59:59 UTC') : 0;

		$accounts_map = $this->load_accounts_map();
		foreach ($accounts_map as $aid => $a)
		{
			$label = $a['account_code'] . ' ' . $a['account_name'] . ' (' . $a['currency_code'] . ')';
			if ((int) $a['is_active'] === 0)
			{
				$label .= ' [' . $this->language->lang('BBACCOUNTS_INACTIVE') . ']';
			}
			$this->template->assign_block_vars('al_accounts', [
				'ID'       => $aid,
				'LABEL'    => $label,
				'SELECTED' => $aid === $account_id,
			]);
		}

		$this->template->assign_vars([
			'U_REPORT_FORM_ACTION' => $this->report_url('account_ledger'),
			'AL_ACCOUNT_ID'        => $account_id,
			'AL_FROM_ISO'          => $from_iso,
			'AL_TO_ISO'            => $to_iso,
			'AL_PER_PAGE'          => $per_page,
			'S_AL_HAS_RESULTS'     => false,
		]);

		if ($account_id <= 0 || !isset($accounts_map[$account_id]))
		{
			return;
		}

		$header = $accounts_map[$account_id];
		$result = $this->ledger->get_account_ledger($account_id, $from, $to, $page, $per_page);

		$page_dr = '0.00';
		$page_cr = '0.00';
		foreach ($result['rows'] as $row)
		{
			$page_dr = bcadd($page_dr, (string) $row['debit'],  2);
			$page_cr = bcadd($page_cr, (string) $row['credit'], 2);
			$this->template->assign_block_vars('al_lines', [
				'ENTRY_DATE'       => date('Y-m-d', (int) $row['entry_date']),
				'JOURNAL_ID'       => (int) $row['journal_id'],
				'DESCRIPTION'      => $row['description'],
				'DEBIT'            => $row['debit'],
				'CREDIT'           => $row['credit'],
				'SUBLEDGER_USER'   => (int) $row['subledger_user_id'] > 0 ? (int) $row['subledger_user_id'] : '',
				'MEMO'             => $row['memo'],
				'REFERENCE_TYPE'   => $row['reference_type'],
				'REFERENCE_SOURCE' => $row['reference_source'],
				'REFERENCE_ID'     => (int) $row['reference_id'],
				'IS_REVERSAL'      => (int) $row['reversal_of'] !== 0,
			]);
		}

		$total_pages = (int) max(1, (int) ceil($result['total'] / $per_page));
		$page_args = [
			'account_id' => $account_id,
			'from'       => $from_iso,
			'to'         => $to_iso,
			'per_page'   => $per_page,
		];

		$this->template->assign_vars([
			'S_AL_HAS_RESULTS' => true,
			'AL_HEADER_CODE'   => $header['account_code'],
			'AL_HEADER_NAME'   => $header['account_name'],
			'AL_HEADER_TYPE'   => $header['account_type'],
			'AL_HEADER_CUR'    => $header['currency_code'],
			'AL_PAGE_DR'       => $page_dr,
			'AL_PAGE_CR'       => $page_cr,
			'AL_TOTAL_DR'      => $result['total_debit'],
			'AL_TOTAL_CR'      => $result['total_credit'],
			'AL_TOTAL_ROWS'    => $result['total'],
			'AL_PAGE'          => $page,
			'AL_TOTAL_PAGES'   => $total_pages,
			'S_AL_HAS_PREV'    => $page > 1,
			'S_AL_HAS_NEXT'    => $page < $total_pages,
			'U_AL_PREV'        => $this->report_url('account_ledger', $page_args + ['page' => $page - 1]),
			'U_AL_NEXT'        => $this->report_url('account_ledger', $page_args + ['page' => $page + 1]),
		]);
	}

	protected function display_subledger_statement(): void
	{
		$user_id  = $this->request->variable('user_id', 0);
		$from_iso = $this->request->variable('from', '');
		$to_iso   = $this->request->variable('to', '');
		$page     = max(1, $this->request->variable('page', 1));
		$per_page = $this->request->variable('per_page', 25);
		if (!in_array($per_page, [25, 50, 100], true))
		{
			$per_page = 25;
		}

		$from = $from_iso !== '' ? (int) strtotime($from_iso . ' UTC') : 0;
		$to   = $to_iso   !== '' ? (int) strtotime($to_iso . ' 23:59:59 UTC') : 0;

		$this->template->assign_vars([
			'U_REPORT_FORM_ACTION' => $this->report_url('subledger'),
			'SL_USER_ID'           => $user_id,
			'SL_FROM_ISO'          => $from_iso,
			'SL_TO_ISO'            => $to_iso,
			'SL_PER_PAGE'          => $per_page,
			'S_SL_HAS_RESULTS'     => false,
		]);

		if ($user_id <= 0)
		{
			return;
		}

		$sql = 'SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = ' . $user_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$username = $row ? (string) $row['username'] : $this->language->lang('BBACCOUNTS_SL_USER_DELETED');

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
		$page_args = [
			'user_id'  => $user_id,
			'from'     => $from_iso,
			'to'       => $to_iso,
			'per_page' => $per_page,
		];

		$this->template->assign_vars([
			'S_SL_HAS_RESULTS' => true,
			'SL_USERNAME'      => $username,
			'SL_TOTAL_DR'      => $result['total_debit'],
			'SL_TOTAL_CR'      => $result['total_credit'],
			'SL_TOTAL_ROWS'    => $result['total'],
			'SL_PAGE'          => $page,
			'SL_TOTAL_PAGES'   => $total_pages,
			'S_SL_HAS_PREV'    => $page > 1,
			'S_SL_HAS_NEXT'    => $page < $total_pages,
			'U_SL_PREV'        => $this->report_url('subledger', $page_args + ['page' => $page - 1]),
			'U_SL_NEXT'        => $this->report_url('subledger', $page_args + ['page' => $page + 1]),
		]);
	}

	protected function display_balance_lookup(): void
	{
		add_form_key('bbaccounts_fe_balance_lookup');

		$sql = 'SELECT account_id, account_code, account_name, account_type, currency_code, is_active
		        FROM ' . $this->accounts_table . '
		        ORDER BY currency_code, account_code';
		$result = $this->db->sql_query($sql);
		$by_currency = [];
		$valid_account_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$aid = (int) $row['account_id'];
			$by_currency[$row['currency_code']][] = $row;
			$valid_account_ids[$aid] = $row;
		}
		$this->db->sql_freeresult($result);

		$posted_account_id = $this->request->variable('account_id', 0);
		foreach ($by_currency as $currency => $rows)
		{
			$this->template->assign_block_vars('bal_currencies', [
				'CURRENCY' => $currency,
			]);
			foreach ($rows as $row)
			{
				$label = $row['account_code'] . ' ' . $row['account_name'];
				if ((int) $row['is_active'] === 0)
				{
					$label .= ' [' . $this->language->lang('BBACCOUNTS_INACTIVE') . ']';
				}
				$this->template->assign_block_vars('bal_currencies.accounts', [
					'ID'       => (int) $row['account_id'],
					'LABEL'    => $label,
					'SELECTED' => (int) $row['account_id'] === $posted_account_id,
				]);
			}
		}

		$as_of_iso = $this->request->variable('as_of', '');

		$this->template->assign_vars([
			'U_BAL_LOOKUP_ACTION' => $this->report_url('balance_lookup'),
			'BAL_AS_OF_ISO'       => $as_of_iso,
		]);

		if (!$this->request->is_set_post('submit'))
		{
			return;
		}
		if (!check_form_key('bbaccounts_fe_balance_lookup'))
		{
			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		if (!isset($valid_account_ids[$posted_account_id]))
		{
			$this->template->assign_var('BAL_LOOKUP_ERROR', $this->language->lang('BBACCOUNTS_BALANCE_LOOKUP_ACCOUNT_REQUIRED'));
			return;
		}

		$as_of = 0;
		if ($as_of_iso !== '')
		{
			$as_of = (int) strtotime($as_of_iso . ' 23:59:59 UTC');
		}

		$account = $valid_account_ids[$posted_account_id];
		$balance = $this->ledger->get_account_balance($posted_account_id, $as_of);

		$this->template->assign_vars([
			'S_BAL_LOOKUP_RESULT' => true,
			'BAL_LOOKUP_ACCOUNT'  => $account['account_code'] . ' ' . $account['account_name'],
			'BAL_LOOKUP_TYPE'     => $this->language->lang('BBACCOUNTS_TYPE_' . strtoupper($account['account_type'])),
			'BAL_LOOKUP_CURRENCY' => $account['currency_code'],
			'BAL_LOOKUP_BALANCE'  => $balance,
			'BAL_LOOKUP_ABNORMAL' => bccomp($balance, '0', 2) < 0,
			'BAL_LOOKUP_AS_OF'    => $as_of > 0 ? date('Y-m-d', $as_of) : date('Y-m-d'),
		]);
	}

	protected function display_user_balance_lookup(): void
	{
		add_form_key('bbaccounts_fe_user_balance');

		$sql = 'SELECT account_id, account_code, account_name, currency_code
		        FROM ' . $this->accounts_table . "
		        WHERE subledger_type <> '' AND is_active = 1
		        ORDER BY currency_code, account_code";
		$result = $this->db->sql_query($sql);
		$by_currency = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$by_currency[$row['currency_code']][] = $row;
		}
		$this->db->sql_freeresult($result);

		$posted_account_id = $this->request->variable('account_id', 0);
		foreach ($by_currency as $currency => $rows)
		{
			$this->template->assign_block_vars('lookup_currencies', [
				'CURRENCY' => $currency,
			]);
			foreach ($rows as $row)
			{
				$this->template->assign_block_vars('lookup_currencies.accounts', [
					'ID'       => (int) $row['account_id'],
					'LABEL'    => $row['account_code'] . ' ' . $row['account_name'],
					'SELECTED' => (int) $row['account_id'] === $posted_account_id,
				]);
			}
		}

		$username  = $this->request->variable('username', '', true);
		$as_of_iso = $this->request->variable('as_of', '');

		$this->template->assign_vars([
			'U_LOOKUP_ACTION' => $this->report_url('user_balance_lookup'),
			'USERNAME'        => $username,
			'AS_OF_ISO'       => $as_of_iso,
		]);

		if (!$this->request->is_set_post('submit'))
		{
			return;
		}
		if (!check_form_key('bbaccounts_fe_user_balance'))
		{
			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		if ($username === '')
		{
			$this->template->assign_var('LOOKUP_ERROR', $this->language->lang('BBACCOUNTS_USER_BALANCE_USERNAME_REQUIRED'));
			return;
		}
		$valid_account_ids = [];
		foreach ($by_currency as $rows)
		{
			foreach ($rows as $row)
			{
				$valid_account_ids[(int) $row['account_id']] = $row;
			}
		}
		if (!isset($valid_account_ids[$posted_account_id]))
		{
			$this->template->assign_var('LOOKUP_ERROR', $this->language->lang('BBACCOUNTS_USER_BALANCE_ACCOUNT_REQUIRED'));
			return;
		}

		$user_id = $this->user_loader->load_user_by_username($username);
		if ((int) $user_id === ANONYMOUS)
		{
			$this->template->assign_var('LOOKUP_ERROR', sprintf($this->language->lang('BBACCOUNTS_USER_BALANCE_UNKNOWN_USER'), $username));
			return;
		}

		$as_of = 0;
		if ($as_of_iso !== '')
		{
			$as_of = (int) strtotime($as_of_iso . ' 23:59:59 UTC');
		}

		$account  = $valid_account_ids[$posted_account_id];
		$balance  = $this->ledger->get_subledger_balance($posted_account_id, (int) $user_id, $as_of);
		$user_row = $this->user_loader->get_user((int) $user_id);

		$this->template->assign_vars([
			'S_LOOKUP_RESULT'  => true,
			'LOOKUP_USERNAME'  => $user_row['username'] ?? $username,
			'LOOKUP_ACCOUNT'   => $account['account_code'] . ' ' . $account['account_name'],
			'LOOKUP_CURRENCY'  => $account['currency_code'],
			'LOOKUP_BALANCE'   => $balance,
			'LOOKUP_ABNORMAL'  => bccomp($balance, '0', 2) < 0,
			'LOOKUP_AS_OF'     => $as_of > 0 ? date('Y-m-d', $as_of) : date('Y-m-d'),
		]);
	}

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
