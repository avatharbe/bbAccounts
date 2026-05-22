<?php
/**
 * bbAccounts — phpBB Extension
 * @copyright (c) 2026 Andreas Vandenberghe (Sajaki)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace avathar\bbaccounts\di\pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Remove the bbGuild portal module service when bbGuild is not installed.
 *
 * The portal module class extends bbGuild's `module_base`. If bbGuild is
 * absent, the class file fatal-errors on autoload. Symfony DI is lazy and
 * normally never autoloads the class in that case, but any future change
 * that triggers reflection on the service (constructor type checks, CLI
 * service introspection, another service injecting it) would crash.
 *
 * Auto-discovered by phpBB's `\phpbb\di\container_builder::register_ext_compiler_pass()`
 * via the `di/pass/*_pass.php` convention.
 */
class remove_bbguild_module_pass implements CompilerPassInterface
{
	public function process(ContainerBuilder $container): void
	{
			if (!class_exists('avathar\\bbguild\\portal\\modules\\module_base'))
		{
			if ($container->hasDefinition('avathar.bbaccounts.portal.module.balance'))
			{
				$container->removeDefinition('avathar.bbaccounts.portal.module.balance');
			}
		}
	}
}
