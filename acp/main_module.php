<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\acp;

class main_module
{
	public $u_action;
	public $page_title;
	public $tpl_name;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \avathar\bbaccounts\controller\acp_controller $controller */
		$controller = $phpbb_container->get('avathar.bbaccounts.controller.acp');
		$controller->set_action($this->u_action);

		switch ($mode)
		{
			case 'currencies':
				$this->tpl_name   = 'acp_bbaccounts_currencies';
				$this->page_title = 'ACP_BBACCOUNTS_CURRENCIES';
				$controller->display_currencies();
				break;
			case 'accounts':
				$this->tpl_name   = 'acp_bbaccounts_accounts';
				$this->page_title = 'ACP_BBACCOUNTS_ACCOUNTS';
				$controller->display_accounts();
				break;
			case 'journal':
				$this->tpl_name   = 'acp_bbaccounts_journal';
				$this->page_title = 'ACP_BBACCOUNTS_JOURNAL';
				$controller->display_journal();
				break;
			case 'reports':
				$this->tpl_name   = 'acp_bbaccounts_reports';
				$this->page_title = 'ACP_BBACCOUNTS_REPORTS';
				$controller->display_reports();
				break;
			default:
				trigger_error('NO_MODE', E_USER_ERROR);
		}
	}
}
