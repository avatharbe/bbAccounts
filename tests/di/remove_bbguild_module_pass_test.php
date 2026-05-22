<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\tests\di;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use avathar\bbaccounts\di\pass\remove_bbguild_module_pass;

class remove_bbguild_module_pass_test extends TestCase
{
	private const SERVICE_ID   = 'avathar.bbaccounts.portal.module.balance';
	private const BBGUILD_FQCN = 'avathar\\bbguild\\portal\\modules\\module_base';

	public function test_service_preserved_when_bbguild_present(): void
	{
		if (!class_exists(self::BBGUILD_FQCN))
		{
			$this->markTestSkipped('bbGuild is not autoload-discoverable in this environment.');
		}

		$container = new ContainerBuilder();
		$container->setDefinition(self::SERVICE_ID, new Definition('stdClass'));

		(new remove_bbguild_module_pass())->process($container);

		$this->assertTrue(
			$container->hasDefinition(self::SERVICE_ID),
			'service should be preserved when bbGuild base class is loadable'
		);
	}

	public function test_no_crash_when_definition_missing(): void
	{
		$container = new ContainerBuilder();

		(new remove_bbguild_module_pass())->process($container);

		$this->assertFalse(
			$container->hasDefinition(self::SERVICE_ID),
			'no definition added, none removed — idempotent'
		);
	}

	public function test_service_removed_when_bbguild_absent(): void
	{
		$this->markTestSkipped(
			'Cannot simulate bbGuild-absent in this dev install: bbGuild files exist on disk and the test bootstrap autoloader resolves the class. This branch is exercised in production when bbGuild is not installed.'
		);
	}
}
