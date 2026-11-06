<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MySQL-only checks for the schema produced by the migration batch.
 *
 * The default PHPUnit suite uses in-memory SQLite, which cannot verify table
 * engines or MySQL foreign-key metadata. This test is therefore skipped unless
 * it is explicitly enabled and pointed at the exact disposable, already-migrated
 * MySQL/MariaDB database:
 *
 * RUN_MYSQL_SCHEMA_TESTS=1 DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
 * DB_DATABASE=farmreach_batch1_migration_test_20261031 DB_USERNAME=... \
 *     php artisan test --compact tests/Feature/MySqlSchemaIntegrityTest.php
 *
 * For the legacy MyISAM path, restore a production backup into a disposable
 * MySQL 8.4 instance, run `php artisan migrate --force` on the feature branch,
 * and then run this test. Never run it against production.
 */
class MySqlSchemaIntegrityTest extends TestCase
{
    /**
     * The only database this test is allowed to inspect.
     */
    private const TARGET_DATABASE = 'farmreach_batch1_migration_test_20261031';

    private const RUN_ENV = 'RUN_MYSQL_SCHEMA_TESTS';

    /**
     * @var array<int, string>
     */
    private const PROTECTED_DATABASES = [
        'farmreach_core',
        'farmreach_fk_test',
        'farmreach_migration_test',
        'farmreach_schema_test',
        'farmreach_schema_sandbox',
        'webfarm',
        'farmreach_batch1_verify_20261029',
        'farmreach_batch1_myisam_migration_test_20261031',
    ];

    /**
     * @var array<int, string>
     */
    private const LOCAL_HOSTS = [
        '127.0.0.1',
        'localhost',
        '::1',
    ];

    /**
     * This class inspects an existing schema and must never refresh or migrate
     * the database it points at.
     */
    public function refreshDatabase(): void {}

    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const EXPECTED_FOREIGN_KEYS = [
        ['products', 'user_id', 'users', 'restrict'],
        ['products', 'category_id', 'categories', 'set null'],
        ['orders', 'buyer_id', 'users', 'restrict'],
        ['orders', 'farmer_id', 'users', 'restrict'],
        ['order_items', 'order_id', 'orders', 'cascade'],
        ['order_items', 'product_id', 'products', 'restrict'],
        ['payments', 'order_id', 'orders', 'cascade'],
        ['recipe_ingredients', 'recipe_id', 'recipes', 'cascade'],
        ['recipe_ingredients', 'category_id', 'categories', 'set null'],
        ['announcements', 'user_id', 'users', 'cascade'],
        ['activity_logs', 'user_id', 'users', 'set null'],
    ];

    public function test_all_application_tables_use_innodb(): void
    {
        $this->requireDisposableMySql();

        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $tables = collect($connection->getSchemaBuilder()->getTables($database))
            ->filter(fn (array $table): bool => $table['schema'] === $database)
            ->values();

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $this->assertSame(
                0,
                strcasecmp((string) $table['engine'], 'InnoDB'),
                "{$table['name']} is not using InnoDB."
            );
        }
    }

    public function test_notifications_table_uses_innodb(): void
    {
        $this->requireDisposableMySql();

        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $notifications = collect($connection->getSchemaBuilder()->getTables($database))
            ->filter(fn (array $table): bool => $table['schema'] === $database)
            ->firstWhere('name', 'notifications');

        $this->assertNotNull($notifications, 'The notifications table does not exist.');
        $this->assertSame(0, strcasecmp((string) $notifications['engine'], 'InnoDB'));
    }

    public function test_expected_foreign_keys_have_the_intended_delete_rules(): void
    {
        $this->requireDisposableMySql();

        $schema = DB::connection()->getSchemaBuilder();

        foreach (self::EXPECTED_FOREIGN_KEYS as [$table, $column, $referencedTable, $onDelete]) {
            $foreignKeys = collect($schema->getForeignKeys($table))
                ->filter(fn (array $foreignKey): bool => in_array(
                    strtolower($column),
                    array_map('strtolower', $foreignKey['columns']),
                    true
                ))
                ->values();

            $this->assertCount(1, $foreignKeys, "Expected one foreign key on {$table}.{$column}.");

            $foreignKey = $foreignKeys->first();

            $this->assertSame(
                0,
                strcasecmp($foreignKey['foreign_table'], $referencedTable),
                "{$table}.{$column} references the wrong table."
            );
            $this->assertSame(
                ['id'],
                array_map('strtolower', $foreignKey['foreign_columns']),
                "{$table}.{$column} references the wrong column."
            );
            $this->assertSame(
                $onDelete,
                strtolower((string) $foreignKey['on_delete']),
                "{$table}.{$column} has the wrong delete rule."
            );
        }
    }

    public function test_expected_foreign_keys_have_no_orphaned_rows(): void
    {
        $this->requireDisposableMySql();

        foreach (self::EXPECTED_FOREIGN_KEYS as [$table, $column, $referencedTable]) {
            $orphanCount = DB::table($table.' as child')
                ->leftJoin($referencedTable.' as parent', 'child.'.$column, '=', 'parent.id')
                ->whereNotNull('child.'.$column)
                ->whereNull('parent.id')
                ->count();

            $this->assertSame(
                0,
                $orphanCount,
                "Found {$orphanCount} orphaned {$table}.{$column} row(s)."
            );
        }
    }

    /**
     * Reject every connection except the exact local disposable database.
     */
    private function requireDisposableMySql(): void
    {
        if (! filter_var(env(self::RUN_ENV, false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped(
                'Set '.self::RUN_ENV.'=1 to run MySQL schema checks.'
            );
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('This test requires a MySQL or MariaDB connection.');
        }

        $connection = DB::connection();
        $configuredDatabase = (string) $connection->getConfig('database');

        // Check the configuration before opening a PDO connection. This
        // prevents DB_URL or a remote host from being contacted at all.
        if (filled($connection->getConfig('url'))) {
            $this->fail(
                'Refusing to run when DB_URL is set; use explicit local DB_* variables.'
            );
        }

        $host = strtolower((string) $connection->getConfig('host'));

        if (! in_array($host, self::LOCAL_HOSTS, true)) {
            $this->fail(
                'Refusing to run against non-local MySQL host ['.$host.'].'
            );
        }

        if (in_array(strtolower($configuredDatabase), self::PROTECTED_DATABASES, true)) {
            $this->fail(
                'Refusing to run against protected database ['.$configuredDatabase.'].'
            );
        }

        if ($configuredDatabase !== self::TARGET_DATABASE) {
            $this->fail(
                'Refusing to run: expected database '.self::TARGET_DATABASE.
                ', configured ['.$configuredDatabase.'].'
            );
        }

        $actualDatabase = (string) DB::selectOne('select database() as name')->name;

        if (in_array(strtolower($actualDatabase), self::PROTECTED_DATABASES, true)) {
            $this->fail(
                'Refusing to run against protected database ['.$actualDatabase.'].'
            );
        }

        if ($actualDatabase !== self::TARGET_DATABASE) {
            $this->fail(
                'Refusing to run: expected database '.self::TARGET_DATABASE.
                ', connected ['.$actualDatabase.'].'
            );
        }
    }
}
