<?php
/**
 * bbAccounts — base test case for service-level tests.
 *
 * Each test gets:
 *   - $this->db           a connected phpBB mysqli driver against the live MySQL
 *                         server, but pointing at the isolated phpbb_bbatest_*
 *                         table prefix
 *   - $this->ledger       an avathar\bbaccounts\service\ledger configured against
 *                         those same test tables
 *
 * setUp() drops and recreates the three test tables and seeds a small fixture
 * (5 accounts across two pools, one inactive). Journal + journal_lines start empty.
 */

namespace avathar\bbaccounts\tests;

abstract class bbaccounts_test_case extends \PHPUnit\Framework\TestCase
{
    /** @var \phpbb\db\driver\mysqli */
    protected $db;

    /** @var \avathar\bbaccounts\service\ledger */
    protected $ledger;

    /** @var string */
    protected $accounts_table = 'phpbb_bbatest_accounts';

    /** @var string */
    protected $journal_table = 'phpbb_bbatest_journal';

    /** @var string */
    protected $lines_table = 'phpbb_bbatest_journal_lines';

    /** @var string */
    protected $currencies_table = 'phpbb_bbatest_currencies';

    protected function setUp(): void
    {
        $cfg = self::load_db_config();

        // Phase 1 supports MySQL/MariaDB only (issue #28). Skip cleanly on
        // any other driver the CI matrix may run us against (postgres, sqlite).
        if (!empty($cfg['dbms']) && stripos($cfg['dbms'], 'mysqli') === false) {
            $this->markTestSkipped('bbAccounts Phase 1 supports MySQL/MariaDB only.');
        }

        $this->db = new \phpbb\db\driver\mysqli();
        $this->db->sql_connect(
            $cfg['dbhost'],
            $cfg['dbuser'],
            $cfg['dbpasswd'],
            $cfg['dbname'],
            $cfg['dbport'],
            false,
            false
        );

        $this->reset_schema();
        $this->load_fixture();

        $this->ledger = new \avathar\bbaccounts\service\ledger(
            $this->db,
            $this->accounts_table,
            $this->journal_table,
            $this->lines_table,
            $this->currencies_table
        );
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->sql_close();
        }
    }

    /**
     * Resolve database credentials. Priority:
     *   1. PHPBB_TEST_* server vars — set by phpBB's phpunit-<db>-github.xml
     *      via <server name="..."/> entries during CI.
     *   2. tests/test_config.php at the phpBB root — older phpBB CI convention.
     *   3. The live phpBB config.php at the same root — local dev mode;
     *      pick the block whose dbname matches 'avathar_be'.
     */
    protected static function load_db_config(): array
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        // 1. CI — phpBB's phpunit-<db>-github.xml exports PHPBB_TEST_* via <server>.
        if (!empty($_SERVER['PHPBB_TEST_DBHOST'])) {
            $dbms = $_SERVER['PHPBB_TEST_DBMS'] ?? 'mysqli';
            if (strpos($dbms, '\\') === false) {
                $dbms = 'phpbb\\db\\driver\\' . $dbms;
            }
            $cfg = [
                'dbms'     => $dbms,
                'dbhost'   => $_SERVER['PHPBB_TEST_DBHOST'],
                'dbport'   => (string) ($_SERVER['PHPBB_TEST_DBPORT'] ?? ''),
                'dbname'   => $_SERVER['PHPBB_TEST_DBNAME'] ?? '',
                'dbuser'   => $_SERVER['PHPBB_TEST_DBUSER'] ?? '',
                'dbpasswd' => $_SERVER['PHPBB_TEST_DBPASSWD'] ?? '',
            ];
            return $cfg;
        }

        $phpbb_root_path = realpath(__DIR__ . '/../../../../') . '/';

        // 2. Older phpBB CI convention.
        $test_config = $phpbb_root_path . 'tests/test_config.php';
        if (is_file($test_config)) {
            $dbms = $dbhost = $dbport = $dbname = $dbuser = $dbpasswd = '';
            require $test_config;
            $cfg = [
                'dbms'     => $dbms,
                'dbhost'   => $dbhost,
                'dbport'   => (string) $dbport,
                'dbname'   => $dbname,
                'dbuser'   => $dbuser,
                'dbpasswd' => $dbpasswd,
            ];
            return $cfg;
        }

        // 3. Local dev fallback.
        $config_path = $phpbb_root_path . 'config.php';
        if (!is_file($config_path)) {
            throw new \RuntimeException(
                "bbaccounts_test_case: no DB credentials available — none of PHPBB_TEST_DBHOST, "
                . "{$phpbb_root_path}tests/test_config.php, or {$config_path} are set/present."
            );
        }
        $contents = file_get_contents($config_path);

        $extract_all = function (string $name) use ($contents): array {
            preg_match_all('/^\$' . preg_quote($name, '/') . "\s*=\s*'([^']*)'\s*;/m", $contents, $m);
            return $m[1];
        };

        $hosts  = $extract_all('dbhost');
        $users  = $extract_all('dbuser');
        $passes = $extract_all('dbpasswd');
        $names  = $extract_all('dbname');
        $ports  = $extract_all('dbport');

        // Choose the block whose dbname is 'avathar_be' (the local dev one).
        $idx = array_search('avathar_be', $names, true);
        if ($idx === false) {
            $idx = count($names) - 1; // fall back to last block
        }

        $cfg = [
            'dbms'     => 'phpbb\\db\\driver\\mysqli',
            'dbhost'   => $hosts[$idx]  ?? 'localhost',
            'dbuser'   => $users[$idx]  ?? '',
            'dbpasswd' => $passes[$idx] ?? '',
            'dbname'   => $names[$idx]  ?? '',
            'dbport'   => $ports[$idx]  ?? '',
        ];

        return $cfg;
    }

    /**
     * Drop and recreate the three bbatest_ tables. Schema mirrors
     * migrations/v1_0_0_schema.php exactly (mediumint UINT, decimal(20,2),
     * etc.) so the ledger service runs against the production-shape schema.
     */
    protected function reset_schema(): void
    {
        $this->db->sql_query("DROP TABLE IF EXISTS {$this->lines_table}");
        $this->db->sql_query("DROP TABLE IF EXISTS {$this->journal_table}");
        $this->db->sql_query("DROP TABLE IF EXISTS {$this->accounts_table}");
        $this->db->sql_query("DROP TABLE IF EXISTS {$this->currencies_table}");

        $this->db->sql_query("
            CREATE TABLE {$this->currencies_table} (
                currency_code  varchar(8)          NOT NULL DEFAULT '',
                currency_name  varchar(64)         NOT NULL DEFAULT '',
                is_active      tinyint(1) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (currency_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->sql_query("
            CREATE TABLE {$this->accounts_table} (
                account_id     mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                account_code   varchar(20)           NOT NULL DEFAULT '',
                account_name   varchar(100)          NOT NULL DEFAULT '',
                account_type   varchar(16)           NOT NULL DEFAULT '',
                parent_id      mediumint(8) unsigned NOT NULL DEFAULT 0,
                currency_code  varchar(8)            NOT NULL DEFAULT 'POINTS',
                subledger_type varchar(16)           NOT NULL DEFAULT '',
                is_active      tinyint(1) unsigned   NOT NULL DEFAULT 1,
                PRIMARY KEY (account_id),
                UNIQUE KEY account_code (account_code),
                KEY parent_id (parent_id),
                KEY currency_code (currency_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->sql_query("
            CREATE TABLE {$this->journal_table} (
                journal_id       mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                entry_date       int(11) unsigned      NOT NULL DEFAULT 0,
                description      varchar(255)          NOT NULL DEFAULT '',
                reference_type   varchar(32)           NOT NULL DEFAULT 'manual',
                reference_source varchar(64)           NOT NULL DEFAULT '',
                reference_id     mediumint(8) unsigned NOT NULL DEFAULT 0,
                created_by       mediumint(8) unsigned NOT NULL DEFAULT 0,
                created_at       int(11) unsigned      NOT NULL DEFAULT 0,
                reversal_of      mediumint(8) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (journal_id),
                KEY reversal_of (reversal_of),
                KEY ref_lookup (reference_source, reference_id),
                KEY entry_date (entry_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->sql_query("
            CREATE TABLE {$this->lines_table} (
                line_id           mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
                journal_id        mediumint(8) unsigned NOT NULL DEFAULT 0,
                account_id        mediumint(8) unsigned NOT NULL DEFAULT 0,
                debit             decimal(20,2)         NOT NULL DEFAULT '0.00',
                credit            decimal(20,2)         NOT NULL DEFAULT '0.00',
                subledger_user_id mediumint(8) unsigned NOT NULL DEFAULT 0,
                subledger_player_id mediumint(8) unsigned NOT NULL DEFAULT 0,
                memo              varchar(255)          NOT NULL DEFAULT '',
                PRIMARY KEY (line_id),
                KEY journal_id (journal_id),
                KEY account_id (account_id),
                KEY subledger_user_id (subledger_user_id),
                KEY subledger_player_id (subledger_player_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Seed currencies + accounts. Currencies are seeded first because
     * the ledger service guards against unknown currencies on entry
     * creation. Five accounts across two pools, one inactive.
     */
    protected function load_fixture(): void
    {
        $currencies = [
            ['POINTS', 'Forum points', 1],
            ['GOLD',   'Gold',         1],
        ];
        foreach ($currencies as [$code, $name, $active]) {
            $sql = "INSERT INTO {$this->currencies_table}
                    (currency_code, currency_name, is_active)
                    VALUES ('{$code}', '{$name}', {$active})";
            $this->db->sql_query($sql);
        }

        $rows = [
            [1, '1010', 'Cash on Hand',           'asset',     0, 'POINTS', '',         1],
            [2, '2100', 'User Wallets',           'liability', 0, 'POINTS', 'customer', 1],
            [3, '5050', 'Points Granted - Admin', 'expense',   0, 'POINTS', '',         1],
            [4, '2200', 'Gold Wallets',           'liability', 0, 'GOLD',   'customer', 1],
            [5, '9999', 'Inactive Account',       'expense',   0, 'POINTS', '',         0],
        ];
        foreach ($rows as [$id, $code, $name, $type, $parent, $cur, $sub, $active]) {
            $sql = "INSERT INTO {$this->accounts_table}
                    (account_id, account_code, account_name, account_type, parent_id, currency_code, subledger_type, is_active)
                    VALUES ({$id}, '{$code}', '{$name}', '{$type}', {$parent}, '{$cur}', '{$sub}', {$active})";
            $this->db->sql_query($sql);
        }
    }
}
