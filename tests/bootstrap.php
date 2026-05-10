<?php
/**
 * bbAccounts — Local PHPUnit bootstrap.
 *
 * Used only when running tests locally (`php vendor/bin/phpunit -c
 * ext/avathar/bbaccounts/phpunit.xml.dist`). CI runs through the phpBB
 * extension test-framework which uses phpBB's own bootstrap and provides
 * the real phpbb_database_test_case + phpbb_functional_test_case base
 * classes from phpBB source.
 *
 * The local phpBB install ships only the runtime, not the test framework,
 * so this file shims those base classes with markTestSkipped — the DB and
 * functional tests skip cleanly locally and only run for real in CI.
 */

define('IN_PHPBB', true);

$phpbb_root_path = realpath(__DIR__ . '/../../../../') . '/';
$phpEx = 'php';

require_once $phpbb_root_path . 'vendor/autoload.php';
require_once $phpbb_root_path . 'includes/functions.php';

spl_autoload_register(function ($class) use ($phpbb_root_path)
{
	if (strpos($class, 'phpbb\\') !== 0)
	{
		return false;
	}
	$file = $phpbb_root_path . str_replace('\\', '/', $class) . '.php';
	if (file_exists($file))
	{
		require_once $file;
		return true;
	}
	return false;
});

spl_autoload_register(function ($class) use ($phpbb_root_path)
{
	$parts = explode('\\', $class);
	if (count($parts) < 3)
	{
		return false;
	}
	$vendor    = $parts[0];
	$extension = $parts[1];
	$extPath   = $phpbb_root_path . 'ext/' . $vendor . '/' . $extension . '/';
	if (!is_dir($extPath))
	{
		return false;
	}
	$relative = implode('/', array_slice($parts, 2));
	$file     = $extPath . $relative . '.php';
	if (file_exists($file))
	{
		require_once $file;
		return true;
	}
	return false;
});

if (!defined('ANONYMOUS'))
{
	define('ANONYMOUS', 1);
}
if (!defined('PHPBB_VERSION'))
{
	define('PHPBB_VERSION', '3.3.13');
}

if (!class_exists('phpbb_database_test_case'))
{
	abstract class phpbb_database_test_case extends \PHPUnit\Framework\TestCase
	{
		protected function setUp(): void
		{
			$this->markTestSkipped('DB tests require the full phpBB test infrastructure (run in CI).');
		}
	}
}

if (!class_exists('phpbb_functional_test_case'))
{
	abstract class phpbb_functional_test_case extends \PHPUnit\Framework\TestCase
	{
		protected function setUp(): void
		{
			$this->markTestSkipped('Functional tests require the full phpBB test infrastructure (run in CI).');
		}
	}
}
