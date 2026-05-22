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

	/**
	 * The "bbGuild absent" branch cannot be simulated in-process when bbGuild's
	 * files exist on disk (the test bootstrap autoloader resolves the class).
	 * Run in a separate process with a stripped-down include path so the
	 * autoloader cannot find the bbGuild namespace.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_service_removed_when_bbguild_absent(): void
	{
		// Re-register only a minimal autoloader that intentionally cannot
		// resolve the bbGuild namespace. We do NOT re-register the broad
		// bootstrap autoloader from tests/bootstrap.php.
		foreach (spl_autoload_functions() ?: [] as $fn)
		{
			spl_autoload_unregister($fn);
		}
		spl_autoload_register(function (string $class) {
			// Resolve only Symfony DI + avathar\bbaccounts\di\pass\* — nothing else.
			$prefix = __DIR__ . '/../../';
			if (strpos($class, 'avathar\\bbaccounts\\di\\pass\\') === 0)
			{
				$rel = substr($class, strlen('avathar\\bbaccounts\\'));
				$file = $prefix . str_replace('\\', '/', $rel) . '.php';
				if (file_exists($file))
				{
					require $file;
				}
			}
		});
		// composer autoloader for Symfony was already loaded by bootstrap.php
		// and remains active for the Symfony classes (autoload not needed for
		// already-loaded classes).

		if (class_exists(self::BBGUILD_FQCN))
		{
			$this->markTestSkipped('bbGuild class is still resolvable after autoloader strip — cannot test absent branch in this PHP build.');
		}

		$container = new ContainerBuilder();
		$container->setDefinition(self::SERVICE_ID, new Definition('stdClass'));

		(new remove_bbguild_module_pass())->process($container);

		$this->assertFalse(
			$container->hasDefinition(self::SERVICE_ID),
			'service should be removed when bbGuild base class is not loadable'
		);
	}
}
