<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\portal\module;

/**
 * bbGuild portal module: own-balance summary.
 *
 * Registered against bbGuild's portal-module tagged-service contract
 * (`bbguild.portal.module`). Renders one row per pool (currency_code)
 * for the logged-in user, sourced from the bbAccounts balance_summary
 * service so the rendering is identical (and shares the cache) with
 * the post-profile badge from #82.
 *
 * Visibility: any logged-in user sees their own balances. Anonymous
 * viewers get a short "log in" placeholder — the module always emits
 * *something* because portal blocks aren't conditionally hidden by
 * bbGuild today; an empty block would just look broken.
 *
 * Leaderboard mode (top-N balances on a configured account, admin-
 * picked per portal block instance) is tracked separately — it adds
 * an ACP config form and a per-module_id storage path the own-balance
 * mode doesn't need.
 */
class bbaccounts_balance extends \avathar\bbguild\portal\modules\module_base
{
	/** @var int Bitmask: center (4) + right (8) — module fits either column */
	protected int $columns = 12;

	/** @var string */
	protected string $name = 'BBACCOUNTS_PORTAL_BALANCE';

	/** @var string */
	protected string $image_src = '';

	/** @var array{vendor: string, file: string} */
	protected $language = ['vendor' => 'avathar/bbaccounts', 'file' => 'portal_bbaccounts'];

	/** @var bool */
	protected bool $multiple_includes = false;

	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \avathar\bbaccounts\service\balance_summary */
	protected $balance_summary;

	public function __construct(
		\phpbb\user $user,
		\phpbb\template\template $template,
		\avathar\bbaccounts\service\balance_summary $balance_summary
	)
	{
		$this->user = $user;
		$this->template = $template;
		$this->balance_summary = $balance_summary;
	}

	public function get_template_center(int $module_id)
	{
		$this->prepare_template();
		return '@avathar_bbaccounts/portal/bbaccounts_balance.html';
	}

	public function get_template_side(int $module_id)
	{
		$this->prepare_template();
		return '@avathar_bbaccounts/portal/bbaccounts_balance.html';
	}

	/**
	 * Common template prep for both column flavours.
	 */
	protected function prepare_template(): void
	{
		$user_id = (int) $this->user->data['user_id'];
		$is_anonymous = $user_id <= 0 || $user_id === 1;

		if ($is_anonymous)
		{
			$this->template->assign_vars([
				'S_BBACCOUNTS_PORTAL_AUTHED'   => false,
				'S_BBACCOUNTS_PORTAL_HAS_DATA' => false,
			]);
			return;
		}

		$summary = $this->balance_summary->get_pool_balances($user_id);
		foreach ($summary as $row)
		{
			$this->template->assign_block_vars('bbaccounts_portal_pools', [
				'CURRENCY'    => $row['currency_code'],
				'BALANCE'     => $row['balance'],
				'IS_ABNORMAL' => $row['is_abnormal'],
			]);
		}

		$this->template->assign_vars([
			'S_BBACCOUNTS_PORTAL_AUTHED'   => true,
			'S_BBACCOUNTS_PORTAL_HAS_DATA' => !empty($summary),
		]);
	}
}
