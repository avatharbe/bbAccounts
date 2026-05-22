<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\controller;

class acp_controller
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
	/** @var \avathar\bbaccounts\service\csv_importer */
	protected $csv_importer;
	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\config\config */
	protected $config;
	/** @var \phpbb\pagination */
	protected $pagination;
	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var string */
	protected $accounts_table;
	/** @var string */
	protected $journal_table;
	/** @var string */
	protected $lines_table;
	/** @var string */
	protected $currencies_table;

	/** @var string */
	protected $u_action = '';

	public function __construct(
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\avathar\bbaccounts\service\ledger $ledger,
		\avathar\bbaccounts\service\csv_importer $csv_importer,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\phpbb\pagination $pagination,
		\phpbb\user_loader $user_loader,
		string $accounts_table,
		string $journal_table,
		string $lines_table,
		string $currencies_table
	)
	{
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->ledger = $ledger;
		$this->csv_importer = $csv_importer;
		$this->cache = $cache;
		$this->db = $db;
		$this->config = $config;
		$this->pagination = $pagination;
		$this->user_loader = $user_loader;
		$this->accounts_table = $accounts_table;
		$this->journal_table = $journal_table;
		$this->lines_table = $lines_table;
		$this->currencies_table = $currencies_table;
	}

	public function set_action(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	public function display_currencies(): void
	{
		$this->language->add_lang('info_acp_bbaccounts', 'avathar/bbaccounts');
		add_form_key('bbaccounts_currency');

		$action = $this->request->variable('action', '');
		$code   = $this->request->variable('code', '');

		if (in_array($action, ['add', 'edit'], true) && $this->request->is_set_post('submit'))
		{
			$this->save_currency($code, $action);
		}
		else if ($action === 'disable' && $code !== '')
		{
			$this->toggle_currency_active($code, 0);
		}
		else if ($action === 'enable' && $code !== '')
		{
			$this->toggle_currency_active($code, 1);
		}

		if (in_array($action, ['add', 'edit'], true))
		{
			$this->render_currency_form($code, $action);
			return;
		}

		$this->render_currencies_list();
	}

	protected function render_currencies_list(): void
	{
		$counts = [];
		$sql = 'SELECT currency_code, COUNT(*) AS c
		        FROM ' . $this->accounts_table . '
		        GROUP BY currency_code';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$counts[(string) $row['currency_code']] = (int) $row['c'];
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT currency_code, currency_name, is_active
		        FROM ' . $this->currencies_table . '
		        ORDER BY currency_code';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$code = (string) $row['currency_code'];
			$this->template->assign_block_vars('currencies', [
				'CODE'          => $code,
				'NAME'          => $row['currency_name'],
				'ACCOUNT_COUNT' => $counts[$code] ?? 0,
				'IS_ACTIVE'     => (int) $row['is_active'],
				'U_EDIT'        => $this->u_action . '&amp;action=edit&amp;code='   . urlencode($code),
				'U_TOGGLE'      => $this->u_action . '&amp;action=' . ((int) $row['is_active'] ? 'disable' : 'enable') . '&amp;code=' . urlencode($code),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'U_ACTION' => $this->u_action,
			'U_ADD'    => $this->u_action . '&amp;action=add',
		]);
	}

	protected function render_currency_form(string $code, string $action): void
	{
		$row = ['currency_code' => '', 'currency_name' => '', 'is_active' => 1];
		$is_locked = false;

		if ($action === 'edit' && $code !== '')
		{
			$sql = 'SELECT * FROM ' . $this->currencies_table . " WHERE currency_code = '" . $this->db->sql_escape($code) . "'";
			$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
			if (!$row)
			{
				trigger_error($this->language->lang('BBACCOUNTS_CURRENCY_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$is_locked = $this->currency_in_use($code);
		}

		$this->template->assign_vars([
			'S_CURRENCY_FORM' => true,
			'S_IS_EDIT'       => $action === 'edit',
			'S_IS_LOCKED'     => $is_locked,
			'CURRENCY_CODE'   => $row['currency_code'],
			'CURRENCY_NAME'   => $row['currency_name'],
			'IS_ACTIVE'       => (int) $row['is_active'],
			'U_BACK'          => $this->u_action,
			'U_ACTION'        => $this->u_action . '&amp;action=' . $action . '&amp;code=' . urlencode((string) $row['currency_code']),
		]);
	}

	protected function save_currency(string $code, string $action): void
	{
		if (!check_form_key('bbaccounts_currency'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$name      = $this->request->variable('currency_name', '', true);
		$is_active = $this->request->variable('is_active', 0) ? 1 : 0;

		if ($action === 'add')
		{
			$new_code = strtoupper(trim($this->request->variable('currency_code', '')));
			if ($new_code === '' || !preg_match('/^[A-Z0-9]{1,8}$/', $new_code))
			{
				trigger_error($this->language->lang('BBACCOUNTS_CURRENCY_BAD_CODE') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			// Reject duplicate
			$sql = 'SELECT 1 FROM ' . $this->currencies_table . " WHERE currency_code = '" . $this->db->sql_escape($new_code) . "'";
			if ($this->db->sql_fetchfield('1', false, $this->db->sql_query_limit($sql, 1)))
			{
				trigger_error($this->language->lang('BBACCOUNTS_CURRENCY_DUPLICATE') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$sql = 'INSERT INTO ' . $this->currencies_table . ' ' . $this->db->sql_build_array('INSERT', [
				'currency_code' => $new_code,
				'currency_name' => $name,
				'is_active'     => $is_active,
			]);
			$this->db->sql_query($sql);
		}
		else
		{
			$set = $this->db->sql_build_array('UPDATE', [
				'currency_name' => $name,
				'is_active'     => $is_active,
			]);
			$sql = 'UPDATE ' . $this->currencies_table . ' SET ' . $set . " WHERE currency_code = '" . $this->db->sql_escape($code) . "'";
			$this->db->sql_query($sql);
		}

		trigger_error($this->language->lang('BBACCOUNTS_CURRENCY_SAVED') . adm_back_link($this->u_action));
	}

	protected function toggle_currency_active(string $code, int $is_active): void
	{
		$sql = 'UPDATE ' . $this->currencies_table . ' SET is_active = ' . $is_active
			 . " WHERE currency_code = '" . $this->db->sql_escape($code) . "'";
		$this->db->sql_query($sql);
		trigger_error($this->language->lang('BBACCOUNTS_CURRENCY_SAVED') . adm_back_link($this->u_action));
	}

	protected function currency_in_use(string $code): bool
	{
		$sql = 'SELECT 1 FROM ' . $this->accounts_table . " WHERE currency_code = '" . $this->db->sql_escape($code) . "'";
		return (bool) $this->db->sql_fetchfield('1', false, $this->db->sql_query_limit($sql, 1));
	}

	/**
	 * @return array<string, array{currency_code: string, currency_name: string, is_active: int}>
	 */
	protected function load_currencies_map(bool $active_only = false): array
	{
		$where = $active_only ? ' WHERE is_active = 1' : '';
		$sql = 'SELECT currency_code, currency_name, is_active
		        FROM ' . $this->currencies_table . $where . '
		        ORDER BY currency_code';
		$result = $this->db->sql_query($sql);
		$map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[(string) $row['currency_code']] = $row;
		}
		$this->db->sql_freeresult($result);
		return $map;
	}

	public function display_accounts(): void
	{
		$this->language->add_lang('info_acp_bbaccounts', 'avathar/bbaccounts');
		add_form_key('bbaccounts_account');

		$action = $this->request->variable('action', '');
		$account_id = $this->request->variable('account_id', 0);

		if (in_array($action, ['add', 'edit'], true) && $this->request->is_set_post('submit'))
		{
			$this->save_account($account_id, $action);
		}
		else if ($action === 'disable' && $account_id > 0)
		{
			$this->toggle_active($account_id, 0);
		}
		else if ($action === 'enable' && $account_id > 0)
		{
			$this->toggle_active($account_id, 1);
		}

		if (in_array($action, ['add', 'edit'], true))
		{
			$this->render_account_form($account_id, $action);
			return;
		}

		$this->render_accounts_list();
	}

	protected function render_accounts_list(): void
	{
		$per_page = max(1, (int) $this->config['bbaccounts_per_page']);
		$start = max(0, $this->request->variable('start', 0));

		$sql = 'SELECT COUNT(*) AS c FROM ' . $this->accounts_table;
		$total = (int) $this->db->sql_fetchfield('c', false, $this->db->sql_query($sql));

		// Single batched aggregate so the per-row balance column is O(1) DB
		// queries regardless of account count. get_trial_balance() does the
		// heavy lifting; we flatten the currency-grouped result into a
		// signed-balance map keyed by account_id.
		$balance_map = [];
		foreach ($this->ledger->get_trial_balance(0) as $rows)
		{
			foreach ($rows as $row)
			{
				$dr = (string) $row['debit_total'];
				$cr = (string) $row['credit_total'];
				$balance_map[(int) $row['account_id']] = in_array($row['account_type'], ['asset', 'expense'], true)
					? bcsub($dr, $cr, 2)
					: bcsub($cr, $dr, 2);
			}
		}

		$sql = 'SELECT account_id, account_code, account_name, account_type, parent_id, currency_code, subledger_type, is_active
		        FROM ' . $this->accounts_table . '
		        ORDER BY currency_code, account_code';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$balance = $balance_map[(int) $row['account_id']] ?? '0.00';
			$this->template->assign_block_vars('accounts', [
				'ACCOUNT_ID'   => $row['account_id'],
				'CODE'         => $row['account_code'],
				'NAME'         => $row['account_name'],
				'TYPE'         => $this->language->lang('BBACCOUNTS_TYPE_' . strtoupper($row['account_type'])),
				'CURRENCY'     => $row['currency_code'],
				'SUBLEDGER'    => $row['subledger_type'] === '' ? '-' : $row['subledger_type'],
				'BALANCE'      => $balance,
				'IS_ABNORMAL'  => bccomp($balance, '0', 2) < 0,
				'IS_ACTIVE'    => (int) $row['is_active'],
				'U_EDIT'       => $this->u_action . '&amp;action=edit&amp;account_id=' . (int) $row['account_id'],
				'U_TOGGLE'     => $this->u_action . '&amp;action=' . ((int) $row['is_active'] ? 'disable' : 'enable') . '&amp;account_id=' . (int) $row['account_id'],
			]);
		}
		$this->db->sql_freeresult($result);

		$this->pagination->generate_template_pagination(
			$this->u_action,
			'pagination',
			'start',
			$total,
			$per_page,
			$start
		);

		$this->template->assign_vars([
			'U_ACTION'    => $this->u_action,
			'U_ADD'       => $this->u_action . '&amp;action=add',
			'TOTAL'       => $this->language->lang('BBACCOUNTS_ACCOUNTS_COUNT', $total),
		]);
	}

	protected function render_account_form(int $account_id, string $action): void
	{
		$row = [
			'account_code'   => '',
			'account_name'   => '',
			'account_type'   => 'asset',
			'parent_id'      => 0,
			'currency_code'  => 'POINTS',
			'subledger_type' => '',
			'is_active'      => 1,
		];
		$is_locked = false;

		if ($action === 'edit' && $account_id > 0)
		{
			$sql = 'SELECT * FROM ' . $this->accounts_table . ' WHERE account_id = ' . $account_id;
			$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
			if (!$row)
			{
				trigger_error($this->language->lang('BBACCOUNTS_ACCOUNT_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			// Lock immutable fields if there is any journal activity
			$sql = 'SELECT 1 FROM ' . $this->lines_table . ' WHERE account_id = ' . $account_id;
			$is_locked = (bool) $this->db->sql_fetchfield('1', false, $this->db->sql_query_limit($sql, 1));
		}

		// Parent dropdown: top-level + same-currency only
		$parents = [];
		$sql = 'SELECT account_id, account_code, account_name FROM ' . $this->accounts_table . " WHERE parent_id = 0 AND currency_code = '" . $this->db->sql_escape($row['currency_code']) . "' ORDER BY account_code";
		$result = $this->db->sql_query($sql);
		while ($r = $this->db->sql_fetchrow($result))
		{
			$parents[] = $r;
		}
		$this->db->sql_freeresult($result);
		foreach ($parents as $p)
		{
			$this->template->assign_block_vars('parents', [
				'ID'       => $p['account_id'],
				'NAME'     => $p['account_code'] . ' ' . $p['account_name'],
				'SELECTED' => (int) $p['account_id'] === (int) $row['parent_id'],
			]);
		}

		// Currency dropdown sourced from the managed currencies table.
		// On edit we always include the row's own currency, even if it has
		// since been deactivated, so the form can re-save without surprise.
		$currencies = $this->load_currencies_map(false);
		foreach ($currencies as $cur_code => $cur)
		{
			$is_active = (int) $cur['is_active'] === 1;
			if (!$is_active && $cur_code !== $row['currency_code'])
			{
				continue;
			}
			$this->template->assign_block_vars('currencies', [
				'CODE'     => $cur_code,
				'NAME'     => $cur['currency_name'],
				'SELECTED' => $cur_code === $row['currency_code'],
			]);
		}

		$this->template->assign_vars([
			'S_ACTION_FORM'  => true,
			'S_IS_LOCKED'    => $is_locked,
			'S_IS_EDIT'      => $action === 'edit',
			'ACCOUNT_ID'     => (int) ($row['account_id'] ?? 0),
			'ACCOUNT_CODE'   => $row['account_code'],
			'ACCOUNT_NAME'   => $row['account_name'],
			'ACCOUNT_TYPE'   => $row['account_type'],
			'CURRENCY'       => $row['currency_code'],
			'SUBLEDGER_TYPE' => $row['subledger_type'],
			'IS_ACTIVE'      => (int) $row['is_active'],
			'U_BACK'         => $this->u_action,
			'U_ACTION'       => $this->u_action . '&amp;action=' . $action . '&amp;account_id=' . $account_id,
		]);
	}

	protected function save_account(int $account_id, string $action): void
	{
		if (!check_form_key('bbaccounts_account'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$data = [
			'account_name' => $this->request->variable('account_name', '', true),
			'parent_id'    => $this->request->variable('parent_id', 0),
			'is_active'    => $this->request->variable('is_active', 0) ? 1 : 0,
		];

		if ($action === 'add')
		{
			$data['account_code']   = $this->request->variable('account_code', '', true);
			$data['account_type']   = $this->validate_enum($this->request->variable('account_type', 'asset', true), \avathar\bbaccounts\service\ledger::VALID_ACCOUNT_TYPES);
			$data['currency_code']  = $this->request->variable('currency_code', 'POINTS', true);
			$data['subledger_type'] = $this->validate_enum($this->request->variable('subledger_type', '', true), \avathar\bbaccounts\service\ledger::VALID_SUBLEDGER_TYPES);

			$sql = 'INSERT INTO ' . $this->accounts_table . ' ' . $this->db->sql_build_array('INSERT', $data);
			$this->db->sql_query($sql);
		}
		else
		{
			// Allow editing immutable fields only if no journal activity
			$sql = 'SELECT 1 FROM ' . $this->lines_table . ' WHERE account_id = ' . $account_id;
			$locked = (bool) $this->db->sql_fetchfield('1', false, $this->db->sql_query_limit($sql, 1));
			if (!$locked)
			{
				$data['account_code']   = $this->request->variable('account_code', '', true);
				$data['account_type']   = $this->validate_enum($this->request->variable('account_type', 'asset', true), \avathar\bbaccounts\service\ledger::VALID_ACCOUNT_TYPES);
				$data['currency_code']  = $this->request->variable('currency_code', 'POINTS', true);
				$data['subledger_type'] = $this->validate_enum($this->request->variable('subledger_type', '', true), \avathar\bbaccounts\service\ledger::VALID_SUBLEDGER_TYPES);
			}

			$sql = 'UPDATE ' . $this->accounts_table . ' SET ' . $this->db->sql_build_array('UPDATE', $data) . ' WHERE account_id = ' . $account_id;
			$this->db->sql_query($sql);
		}

		trigger_error($this->language->lang('BBACCOUNTS_ACCOUNT_SAVED') . adm_back_link($this->u_action));
	}

	protected function toggle_active(int $account_id, int $is_active): void
	{
		$sql = 'UPDATE ' . $this->accounts_table . ' SET is_active = ' . $is_active . ' WHERE account_id = ' . $account_id;
		$this->db->sql_query($sql);
		trigger_error($this->language->lang('BBACCOUNTS_ACCOUNT_DISABLED') . adm_back_link($this->u_action));
	}

	public function display_journal(): void
	{
		$this->language->add_lang('info_acp_bbaccounts', 'avathar/bbaccounts');
		add_form_key('bbaccounts_journal');

		$action = $this->request->variable('action', '');

		if ($action === 'create' && $this->request->is_set_post('submit'))
		{
			$this->save_journal_entry();
			return;
		}
		if ($action === 'reverse')
		{
			$this->handle_reverse();
			return;
		}
		if ($action === 'create')
		{
			$this->render_journal_form();
			return;
		}
		if ($action === 'import')
		{
			$this->render_csv_import_form();
			return;
		}

		$this->render_journal_list();
	}

	protected function render_journal_list(): void
	{
		$per_page = max(1, (int) $this->config['bbaccounts_per_page']);
		$start = max(0, $this->request->variable('start', 0));

		$list = $this->ledger->get_journal_list($start, $per_page);

		// Resolve subledger users for the page in one batch — distinct
		// per (entry, user) pair, ANONYMOUS skipped since they're a
		// post-deletion artefact rather than a meaningful subledger row.
		$users_by_entry = [];
		$page_ids = array_column($list['rows'], 'journal_id');
		if (!empty($page_ids))
		{
			$sql = 'SELECT DISTINCT l.journal_id, l.subledger_user_id
			        FROM ' . $this->lines_table . ' l
			        WHERE ' . $this->db->sql_in_set('l.journal_id', array_map('intval', $page_ids)) . '
			          AND l.subledger_user_id > 1';
			$result = $this->db->sql_query($sql);
			$all_user_ids = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$jid = (int) $row['journal_id'];
				$uid = (int) $row['subledger_user_id'];
				$users_by_entry[$jid][] = $uid;
				$all_user_ids[$uid] = true;
			}
			$this->db->sql_freeresult($result);

			if (!empty($all_user_ids))
			{
				$this->user_loader->load_users(array_keys($all_user_ids));
			}
		}

		foreach ($list['rows'] as $row)
		{
			$jid = (int) $row['journal_id'];
			$this->template->assign_block_vars('entries', [
				'JOURNAL_ID'        => $jid,
				'ENTRY_DATE'        => date('Y-m-d', (int) $row['entry_date']),
				'DESCRIPTION'       => $row['description'],
				'REFERENCE_TYPE'    => $row['reference_type'],
				'REFERENCE_SOURCE'  => $row['reference_source'],
				'REFERENCE_ID'      => (int) $row['reference_id'],
				'IS_REVERSED'       => (int) $row['is_reversed'],
				'IS_REVERSAL'       => (int) $row['reversal_of'] !== 0,
				'REVERSAL_OF'       => (int) $row['reversal_of'],
				'U_REVERSE'         => $this->u_action . '&amp;action=reverse&amp;journal_id=' . $jid,
			]);

			foreach ($users_by_entry[$jid] ?? [] as $uid)
			{
				$this->template->assign_block_vars('entries.users', [
					'USER_ID'       => $uid,
					'USERNAME_FULL' => $this->user_loader->get_username($uid, 'full'),
				]);
			}
		}

		$this->pagination->generate_template_pagination(
			$this->u_action,
			'pagination',
			'start',
			$list['total'],
			$per_page,
			$start
		);

		$this->template->assign_vars([
			'U_NEW'      => $this->u_action . '&amp;action=create',
			'U_IMPORT'   => $this->u_action . '&amp;action=import',
			'TOTAL'      => $this->language->lang('BBACCOUNTS_JOURNAL_COUNT', $list['total']),
		]);
	}

	protected function render_journal_form(): void
	{
		$accounts = [];
		$sql = 'SELECT account_id, account_code, account_name, currency_code, subledger_type
		        FROM ' . $this->accounts_table . '
		        WHERE is_active = 1
		        ORDER BY currency_code, account_code';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$accounts[] = [
				'id'            => (int) $row['account_id'],
				'label'         => $row['account_code'] . ' ' . $row['account_name'] . ' (' . $row['currency_code'] . ')',
				'currency'      => $row['currency_code'],
				'is_subledger'  => $row['subledger_type'] !== '',
			];
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'S_CREATE_FORM'    => true,
			'S_ACCOUNTS_JSON'  => json_encode($accounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
			'U_BACK'           => $this->u_action,
			'U_ACTION'         => $this->u_action . '&amp;action=create',
			'TODAY_ISO'        => date('Y-m-d'),
		]);
	}

	/**
	 * CSV import dispatcher: upload form → parsed preview → confirm-commit.
	 * Confirm replays a cached parsed payload by token rather than asking
	 * the admin to upload the same file twice.
	 */
	protected function render_csv_import_form(): void
	{
		add_form_key('bbaccounts_csv_import');

		$this->template->assign_vars([
			'S_IMPORT_FORM' => true,
			'U_BACK'        => $this->u_action,
			'U_ACTION'      => $this->u_action . '&amp;action=import',
		]);

		// Confirm POST: replay the cached payload from the parse pass and
		// commit through ledger::create_entry() inside one transaction. On
		// any failure the whole import rolls back and the preview re-
		// renders with the failure annotated.
		if ($this->request->is_set_post('submit_confirm'))
		{
			if (!check_form_key('bbaccounts_csv_import'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$token = $this->request->variable('confirm_token', '');
			$cache_key = '_bbaccounts_csv_' . $token;
			$cached = $token !== '' ? $this->cache->get($cache_key) : false;
			if (!is_array($cached))
			{
				$this->template->assign_var('IMPORT_ERROR', $this->language->lang('BBACCOUNTS_IMPORT_TOKEN_EXPIRED'));
				return;
			}
			$this->process_csv_import_commit($cached, $cache_key);
			return;
		}

		if (!$this->request->is_set_post('submit'))
		{
			return;
		}
		if (!check_form_key('bbaccounts_csv_import'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$file = $this->request->file('csv_file');
		if (empty($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
		{
			$this->template->assign_var('IMPORT_ERROR', $this->language->lang('BBACCOUNTS_IMPORT_FILE_REQUIRED'));
			return;
		}

		$content = (string) @file_get_contents($file['tmp_name']);
		if ($content === '')
		{
			$this->template->assign_var('IMPORT_ERROR', $this->language->lang('BBACCOUNTS_IMPORT_FILE_REQUIRED'));
			return;
		}

		$parsed = $this->csv_importer->parse_and_validate($content);
		$this->render_csv_import_preview($parsed, (string) ($file['name'] ?? ''));
	}

	/**
	 * Render the parsed import as a per-entry preview, then cache the
	 * payload so Confirm can replay it without a second upload. Token
	 * + 10-minute TTL — long enough for an admin to review and click
	 * Confirm, short enough that stale uploads don't accumulate.
	 */
	protected function render_csv_import_preview(array $parsed, string $filename): void
	{
		$is_clean = $this->csv_importer->is_clean($parsed);

		$entry_count = count($parsed['entries']);
		$line_count = 0;
		$row_error_count = 0;
		$entry_error_count = 0;
		foreach ($parsed['entries'] as $entry)
		{
			$line_count += count($entry['lines']);
			$entry_error_count += count($entry['errors']);
			foreach ($entry['lines'] as $line)
			{
				$row_error_count += count($line['errors']);
			}
		}

		foreach ($parsed['global_errors'] as $message)
		{
			$this->template->assign_block_vars('import_global_errors', ['MESSAGE' => $message]);
		}

		foreach ($parsed['entries'] as $entry_ref => $entry)
		{
			$has_errors = !empty($entry['errors']);
			foreach ($entry['lines'] as $line)
			{
				if (!empty($line['errors']))
				{
					$has_errors = true;
					break;
				}
			}

			$this->template->assign_block_vars('import_entries', [
				'ENTRY_REF'    => $entry_ref,
				'ENTRY_DATE'   => $entry['date'] > 0 ? date('Y-m-d', $entry['date']) : '',
				'DESCRIPTION'  => $entry['description'],
				'TOTAL_DR'     => $entry['totals']['debit'],
				'TOTAL_CR'     => $entry['totals']['credit'],
				'BALANCED'     => bccomp($entry['totals']['debit'], $entry['totals']['credit'], 2) === 0,
				'HAS_ERRORS'   => $has_errors,
				'LINE_COUNT'   => count($entry['lines']),
			]);

			foreach ($entry['lines'] as $line)
			{
				$account = $line['account'];
				$this->template->assign_block_vars('import_entries.lines', [
					'LINE_NO'           => $line['line_no'],
					'ACCOUNT_CODE'      => $line['account_code'],
					'ACCOUNT_NAME'      => $account ? (string) $account['account_name'] : '',
					'CURRENCY'          => $account ? (string) $account['currency_code'] : '',
					'DEBIT'             => $line['debit'],
					'CREDIT'            => $line['credit'],
					'SUBLEDGER_USER_ID' => $line['subledger_user_id'] > 0 ? $line['subledger_user_id'] : '',
					'MEMO'              => $line['memo'],
					'HAS_ERRORS'        => !empty($line['errors']),
				]);
				foreach ($line['errors'] as $message)
				{
					$this->template->assign_block_vars('import_entries.lines.errors', ['MESSAGE' => $message]);
				}
			}

			foreach ($entry['errors'] as $message)
			{
				$this->template->assign_block_vars('import_entries.errors', ['MESSAGE' => $message]);
			}
		}

		$confirm_token = '';
		if ($is_clean)
		{
			$confirm_token = bin2hex(random_bytes(16));
			$this->cache->put('_bbaccounts_csv_' . $confirm_token, $parsed, 600);
		}

		$this->template->assign_vars([
			'S_IMPORT_FILE_RECEIVED' => true,
			'S_IMPORT_HAS_PREVIEW'   => $entry_count > 0,
			'S_IMPORT_CLEAN'         => $is_clean,
			'IMPORT_FILENAME'        => $filename,
			'IMPORT_ENTRY_COUNT'     => $entry_count,
			'IMPORT_LINE_COUNT'      => $line_count,
			'IMPORT_ROW_ERRORS'      => $row_error_count,
			'IMPORT_ENTRY_ERRORS'    => $entry_error_count,
			'IMPORT_GLOBAL_ERRORS'   => count($parsed['global_errors']),
			'IMPORT_CONFIRM_TOKEN'   => $confirm_token,
			'U_IMPORT_CONFIRM'       => $this->u_action . '&amp;action=import',
		]);
	}

	/**
	 * Commit a previously-parsed CSV import inside a single DB transaction.
	 *
	 * Each entry posts through ledger::create_entry() so all existing
	 * service-layer invariants apply (balance, immutability, account
	 * active/locked checks, currency consistency). If any entry throws,
	 * the whole import rolls back — the importer never produces a partial
	 * commit. On clean run the cache token is destroyed so a refresh of
	 * the result page can't double-post.
	 */
	protected function process_csv_import_commit(array $parsed, string $cache_key): void
	{
		// Pre-flight: re-verify cleanliness. The cache should already be
		// clean (the preview step only caches when is_clean()), but this
		// guards against a tampered token or schema change between parse
		// + commit.
		if (!$this->csv_importer->is_clean($parsed))
		{
			$this->cache->destroy($cache_key);
			$this->render_csv_import_preview($parsed, '');
			$this->template->assign_var('IMPORT_ERROR', $this->language->lang('BBACCOUNTS_IMPORT_NOT_CLEAN'));
			return;
		}

		$created_by = (int) $this->user->data['user_id'];
		$created_ids = [];
		$failure_ref = '';
		$failure_msg = '';

		$this->db->sql_transaction('begin');
		try
		{
			foreach ($parsed['entries'] as $entry_ref => $entry)
			{
				$lines = [];
				foreach ($entry['lines'] as $line)
				{
					$account = $line['account'];
					$lines[] = [
						'account_id'        => (int) ($account['account_id'] ?? 0),
						'debit'             => (string) $line['debit'],
						'credit'            => (string) $line['credit'],
						'subledger_user_id' => (int) $line['subledger_user_id'],
						'memo'              => (string) $line['memo'],
					];
				}

				// Per-entry reference fields: first row of the entry wins,
				// matching the spec's "first row wins" rule for header-like
				// per-entry columns.
				$first = $entry['lines'][0];
				$ref_type   = (string) $first['reference_type'];
				$ref_id     = (int) $first['reference_id'];
				$ref_source = (string) $first['reference_source'];

				$journal_id = $this->ledger->create_entry(
					(int) $entry['date'],
					(string) $entry['description'],
					$lines,
					$ref_type,
					$ref_id,
					$ref_source,
					$created_by
				);
				$created_ids[(string) $entry_ref] = (int) $journal_id;
			}
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			$failure_msg = $e->getMessage();
			// The entry that failed is the next one after the last
			// successful create — we can identify it by counting.
			$idx = 0;
			foreach ($parsed['entries'] as $entry_ref => $entry)
			{
				if ($idx === count($created_ids))
				{
					$failure_ref = (string) $entry_ref;
					break;
				}
				$idx++;
			}

			$this->render_csv_import_preview($parsed, '');
			$this->template->assign_vars([
				'IMPORT_ERROR'           => sprintf($this->language->lang('BBACCOUNTS_IMPORT_COMMIT_FAILED'), $failure_ref, $failure_msg),
				'S_IMPORT_COMMIT_FAILED' => true,
			]);
			return;
		}

		$this->db->sql_transaction('commit');
		$this->cache->destroy($cache_key);

		$this->template->assign_vars([
			'S_IMPORT_RESULT'       => true,
			'IMPORT_RESULT_COUNT'   => count($created_ids),
			'U_BACK'                => $this->u_action,
			'U_JOURNAL_LIST'        => $this->u_action,
		]);
		foreach ($created_ids as $entry_ref => $journal_id)
		{
			$this->template->assign_block_vars('import_results', [
				'ENTRY_REF'  => (string) $entry_ref,
				'JOURNAL_ID' => $journal_id,
			]);
		}
	}

	protected function save_journal_entry(): void
	{
		if (!check_form_key('bbaccounts_journal'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$entry_date_iso   = $this->request->variable('entry_date', date('Y-m-d'));
		$entry_date       = (int) strtotime($entry_date_iso . ' UTC');
		$description      = $this->request->variable('description', '', true);
		$reference_type   = $this->request->variable('reference_type', 'manual');
		$reference_source = $this->request->variable('reference_source', '');
		$reference_id     = $this->request->variable('reference_id', 0);

		$accounts_post = $this->request->variable('line_account', [0]);
		$debits_post   = $this->request->variable('line_debit',   ['']);
		$credits_post  = $this->request->variable('line_credit',  ['']);
		$subs_post     = $this->request->variable('line_user',    [0]);
		$memos_post    = $this->request->variable('line_memo',    [''], true);

		$lines = [];
		foreach ($accounts_post as $i => $aid)
		{
			if ((int) $aid === 0)
			{
				continue;
			}
			$lines[] = [
				'account_id'        => (int) $aid,
				'debit'             => $debits_post[$i] !== '' ? (string) $debits_post[$i] : '0.00',
				'credit'            => $credits_post[$i] !== '' ? (string) $credits_post[$i] : '0.00',
				'subledger_user_id' => (int) ($subs_post[$i] ?? 0),
				'memo'              => $memos_post[$i] ?? '',
			];
		}

		try
		{
			$journal_id = $this->ledger->create_entry(
				$entry_date,
				$description,
				$lines,
				$reference_type,
				$reference_id,
				$reference_source,
				(int) $this->user->data['user_id']
			);
			trigger_error(sprintf($this->language->lang('BBACCOUNTS_ENTRY_SAVED'), $journal_id) . adm_back_link($this->u_action));
		}
		catch (\Throwable $e)
		{
			trigger_error($e->getMessage() . adm_back_link($this->u_action), E_USER_WARNING);
		}
	}

	protected function handle_reverse(): void
	{
		// confirm_box flow: this method runs twice. First call (GET from the
		// "Reverse" link in the journal list) lands on the else branch, which
		// shows the confirmation page and hashes the hidden fields below into
		// the form. Second call (POST from "Yes") arrives as a fresh request
		// with those hidden fields replayed by phpBB's confirm_box machinery,
		// so $request->variable('journal_id') reads the *original* id back —
		// don't refactor this into a single read at the top of the method.
		$journal_id = $this->request->variable('journal_id', 0);
		if ($journal_id === 0)
		{
			trigger_error($this->language->lang('BBACCOUNTS_MISSING_JOURNAL_ID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if (confirm_box(true))
		{
			try
			{
				$reverse_id = $this->ledger->reverse_entry($journal_id, '', (int) $this->user->data['user_id']);
				trigger_error(sprintf($this->language->lang('BBACCOUNTS_ENTRY_REVERSED'), $reverse_id) . adm_back_link($this->u_action));
			}
			catch (\Throwable $e)
			{
				trigger_error($e->getMessage() . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}
		else
		{
			confirm_box(false, sprintf($this->language->lang('BBACCOUNTS_CONFIRM_REVERSE'), $journal_id), build_hidden_fields([
				'action'     => 'reverse',
				'journal_id' => $journal_id,
			]));
		}
	}

	public function display_reports(): void
	{
		$this->language->add_lang('info_acp_bbaccounts', 'avathar/bbaccounts');

		$report = $this->request->variable('report', 'trial_balance');

		$this->template->assign_vars([
			'U_ACTION'                  => $this->u_action,
			'U_REPORT_TRIAL_BALANCE'    => $this->u_action . '&amp;report=trial_balance',
			'U_REPORT_ACCOUNT_LEDGER'   => $this->u_action . '&amp;report=account_ledger',
			'U_REPORT_SUBLEDGER'        => $this->u_action . '&amp;report=subledger',
			'U_REPORT_BALANCE_LOOKUP'   => $this->u_action . '&amp;report=balance_lookup',
			'U_REPORT_USER_BALANCE'     => $this->u_action . '&amp;report=user_balance_lookup',
			'S_REPORT'                  => $report,
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
	}

	protected function display_trial_balance(): void
	{
		$as_of_iso = $this->request->variable('as_of', date('Y-m-d'));
		$as_of = (int) strtotime($as_of_iso . ' 23:59:59 UTC');
		$currency_filter = $this->request->variable('currency', '');

		$tb = $this->ledger->get_trial_balance($as_of, $currency_filter);

		// Standard accounting order: A → L → E → R → X. Any non-standard
		// account_type encountered (shouldn't happen — account_type is
		// constrained at the form layer) is appended after.
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

			// Pre-compute per-type subtotals + the two group totals so each
			// type block can know whether it ends a group (BS or P&L) and
			// carry the right group label/DR/CR for the template to emit
			// directly under the type's own subtotal row.
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
			'AS_OF_ISO'       => $as_of_iso,
			'CURRENCY_FILTER' => $currency_filter,
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

		// Account dropdown: every account, active or not, grouped by currency
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
			'AL_ACCOUNT_ID'    => $account_id,
			'AL_FROM_ISO'      => $from_iso,
			'AL_TO_ISO'        => $to_iso,
			'AL_PER_PAGE'      => $per_page,
			'S_AL_HAS_RESULTS' => false,
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
		$page_url = static fn (int $p): string => 'report=account_ledger'
			. '&amp;account_id=' . $account_id
			. '&amp;from='       . urlencode($from_iso)
			. '&amp;to='         . urlencode($to_iso)
			. '&amp;per_page='   . $per_page
			. '&amp;page='       . $p;

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
			'U_AL_PREV'        => $this->u_action . '&amp;' . $page_url($page - 1),
			'U_AL_NEXT'        => $this->u_action . '&amp;' . $page_url($page + 1),
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
			'SL_USER_ID'       => $user_id,
			'SL_FROM_ISO'      => $from_iso,
			'SL_TO_ISO'        => $to_iso,
			'SL_PER_PAGE'      => $per_page,
			'S_SL_HAS_RESULTS' => false,
		]);

		if ($user_id <= 0)
		{
			return;
		}

		// Resolve username for header display; keep going even if anonymous/unknown
		// because the journal lines may still carry the id if the user was deleted.
		$sql = 'SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = ' . $user_id;
		$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
		$username = $row ? (string) $row['username'] : $this->language->lang('BBACCOUNTS_SL_USER_DELETED');

		// Header strip: per-account opening / period dr / period cr / closing
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

		// Lines body
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
		$page_url = static fn (int $p): string => 'report=subledger'
			. '&amp;user_id=' . $user_id
			. '&amp;from='    . urlencode($from_iso)
			. '&amp;to='      . urlencode($to_iso)
			. '&amp;per_page='. $per_page
			. '&amp;page='    . $p;

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
			'U_SL_PREV'        => $this->u_action . '&amp;' . $page_url($page - 1),
			'U_SL_NEXT'        => $this->u_action . '&amp;' . $page_url($page + 1),
		]);
	}

	protected function display_balance_lookup(): void
	{
		add_form_key('bbaccounts_balance_lookup');

		// Account dropdown grouped by currency. Inactive accounts stay
		// selectable so historic balances can still be inspected.
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
			'U_BAL_LOOKUP_ACTION' => $this->u_action . '&amp;report=balance_lookup',
			'BAL_AS_OF_ISO'       => $as_of_iso,
		]);

		if (!$this->request->is_set_post('submit'))
		{
			return;
		}
		if (!check_form_key('bbaccounts_balance_lookup'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
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
		add_form_key('bbaccounts_user_balance');

		// Build the subledger-only account dropdown, grouped by currency.
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
			'U_LOOKUP_ACTION' => $this->u_action . '&amp;report=user_balance_lookup',
			'USERNAME'        => $username,
			'AS_OF_ISO'       => $as_of_iso,
		]);

		if (!$this->request->is_set_post('submit'))
		{
			return;
		}
		if (!check_form_key('bbaccounts_user_balance'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		// Validate inputs.
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

		// Resolve username → user_id. phpBB convention: ANONYMOUS return == not found.
		$user_id = $this->user_loader->load_user_by_username($username);
		if ((int) $user_id === ANONYMOUS)
		{
			$this->template->assign_var('LOOKUP_ERROR', sprintf($this->language->lang('BBACCOUNTS_USER_BALANCE_UNKNOWN_USER'), $username));
			return;
		}

		// Clamp as_of to end-of-day UTC, matching trial-balance semantics.
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

	/**
	 * Whitelist a posted enum value against a closed set; abort the ACP
	 * request if the input is outside it. The HTML <select> only constrains
	 * the browser path — a hand-crafted POST bypasses it.
	 */
	protected function validate_enum(string $value, array $allowed): string
	{
		if (!in_array($value, $allowed, true))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}
		return $value;
	}
}
