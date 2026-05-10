<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public const ANONYMOUS_USER_ID = 1;

	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\controller\helper */
	protected $helper;
	/** @var \avathar\bbaccounts\service\balance_summary */
	protected $balance_summary;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var string */
	protected $lines_table;

	public function __construct(
		\phpbb\language\language $language,
		\phpbb\auth\auth $auth,
		\phpbb\user $user,
		\phpbb\template\template $template,
		\phpbb\controller\helper $helper,
		\avathar\bbaccounts\service\balance_summary $balance_summary,
		\phpbb\db\driver\driver_interface $db,
		string $lines_table
	)
	{
		$this->language = $language;
		$this->auth = $auth;
		$this->user = $user;
		$this->template = $template;
		$this->helper = $helper;
		$this->balance_summary = $balance_summary;
		$this->db = $db;
		$this->lines_table = $lines_table;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'              => 'on_user_setup',
			'core.delete_user_after'       => 'on_user_delete',
			'core.memberlist_view_profile' => 'on_memberlist_view_profile',
			'core.page_header'             => 'on_page_header',
			'core.permissions'             => 'on_permissions',
		];
	}

	/**
	 * Register bbAccounts permissions with phpBB's permission MASK UI.
	 * Without this, the perms exist in `phpbb_acl_options` (added by
	 * the migration) but the ACP permission screen has no entry to
	 * grant them — the role/group/user tabs show no row at all.
	 *
	 * Both perms land in the stock `misc` category. Lang keys are
	 * defined in language/en/permissions_bbaccounts.php.
	 */
	public function on_permissions($event): void
	{
		$permissions = $event['permissions'];
		$permissions['a_accounts']      = ['lang' => 'ACL_A_ACCOUNTS',      'cat' => 'misc'];
		$permissions['u_accounts_view'] = ['lang' => 'ACL_U_ACCOUNTS_VIEW', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}

	public function on_user_setup($event): void
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'avathar/bbaccounts',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function on_user_delete($event): void
	{
		$user_ids = (array) $event['user_ids'];
		if (empty($user_ids))
		{
			return;
		}
		$user_ids = array_map('intval', $user_ids);

		$sql = 'UPDATE ' . $this->lines_table . '
		        SET subledger_user_id = ' . self::ANONYMOUS_USER_ID . '
		        WHERE ' . $this->db->sql_in_set('subledger_user_id', $user_ids);
		$this->db->sql_query($sql);
	}

	/**
	 * Inject a per-pool balance badge into the memberlist profile view.
	 *
	 * Visibility: own profile for any logged-in user (matches the UCP
	 * "My Wallet" trust model — your data is yours to see); other
	 * profiles only when the viewer has `u_accounts_view`. Anonymous
	 * viewers never see the badge.
	 */
	public function on_memberlist_view_profile($event): void
	{
		$member = (array) $event['member'];
		$target_user_id = (int) ($member['user_id'] ?? 0);
		if ($target_user_id <= 0 || $target_user_id === self::ANONYMOUS_USER_ID)
		{
			return;
		}

		$viewer_id = (int) $this->user->data['user_id'];
		$is_anonymous = $viewer_id <= 0 || $viewer_id === self::ANONYMOUS_USER_ID;
		if ($is_anonymous)
		{
			return;
		}
		$is_self  = $viewer_id === $target_user_id;
		$can_view = $is_self || $this->auth->acl_get('u_accounts_view');
		if (!$can_view)
		{
			return;
		}

		$summary = $this->balance_summary->get_pool_balances($target_user_id);
		if (empty($summary))
		{
			return;
		}

		foreach ($summary as $row)
		{
			$this->template->assign_block_vars('bbaccounts_pool_balances', [
				'CURRENCY'    => $row['currency_code'],
				'BALANCE'     => $row['balance'],
				'IS_ABNORMAL' => $row['is_abnormal'],
			]);
		}

		$this->template->assign_var('S_BBACCOUNTS_PROFILE_BADGE', true);
	}

	/**
	 * Inject a "bbAccounts Reports" entry into the standard phpBB
	 * navbar for users with `u_accounts_view`. Lands them on the FE
	 * Reports page (the read-only mirror of ACP Reports). Hidden for
	 * everyone else — assignment is gated on the auth check, so the
	 * template event partial degrades to nothing without it.
	 */
	public function on_page_header(): void
	{
		if (!$this->auth->acl_get('u_accounts_view'))
		{
			return;
		}
		$this->template->assign_vars([
			'S_BBACCOUNTS_FE_REPORTS' => true,
			'U_BBACCOUNTS_FE_REPORTS' => $this->helper->route('avathar_bbaccounts_reports', ['report' => 'trial_balance']),
		]);
	}
}
