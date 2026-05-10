<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\ucp;

class main_module
{
	public $u_action;
	public $page_title;
	public $tpl_name;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \avathar\bbaccounts\controller\ucp_controller $controller */
		$controller = $phpbb_container->get('avathar.bbaccounts.controller.ucp');
		$controller->set_action($this->u_action);

		switch ($mode)
		{
			case 'wallet':
				$this->tpl_name   = 'ucp_bbaccounts_wallet';
				$this->page_title = 'UCP_BBACCOUNTS_WALLET';
				$controller->display_wallet();
				break;
			case 'statement':
				$this->tpl_name   = 'ucp_bbaccounts_statement';
				$this->page_title = 'UCP_BBACCOUNTS_STATEMENT';
				$controller->display_statement();
				break;
			default:
				trigger_error('NO_MODE', E_USER_ERROR);
		}
	}
}
