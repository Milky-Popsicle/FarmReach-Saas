<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * MySQL-only verification for the legacy MyISAM-to-InnoDB conversion.
 *
 * This test owns a disposable database and creates its schema directly. It
 * never runs migrations through Artisan, never touches the migrations table,
 * and never uses production data or a production backup. Tests that create
 * MyISAM tables skip when the server does not support MyISAM.
 *
 * Run only this class against the exact disposable database:
 *
 * RUN_MYSQL_LEGACY_CONVERSION_TESTS=1 \
 * DB_CONNECTION=mysql \
 * DB_HOST=127.0.0.1 \
 * DB_PORT=3306 \
 * DB_DATABASE=farmreach_batch1_myisam_migration_test_20261031 \
 * DB_USERNAME=... \
 * php artisan test --compact tests/Feature/MySqlLegacyMyIsamConversionTest.php
 *
 * Do not run the whole suite with MySQL overrides. Other feature tests do not
 * have this test's disposable-database guard.
 */
class MySqlLegacyMyIsamConversionTest extends TestCase
{
    /**
     * The only database this test is allowed to modify.
     */
    private const TARGET_DATABASE = 'farmreach_batch1_myisam_migration_test_20261031';

    private const RUN_ENV = 'RUN_MYSQL_LEGACY_CONVERSION_TESTS';

    private const CONVERSION_MIGRATION =
        '2026_10_21_080216_convert_tables_to_innodb_and_restore_foreign_keys.php';

    private const NOTIFICATIONS_MIGRATION =
        '2026_10_26_022250_ensure_notifications_table_uses_innodb.php';

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
        'farmreach_batch1_migration_test_20261031',
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
     * @var array<int, string>
     */
    private const LEGACY_TABLES = [
        'users',
        'categories',
        'products',
        'orders',
        'order_items',
        'payments',
        'recipes',
        'recipe_ingredients',
        'announcements',
        'activity_logs',
    ];

    /**
     * Tables this test may create and remove.
     *
     * @var array<int, string>
     */
    private const OWNED_TABLES = [
        'users',
        'categories',
        'products',
        'orders',
        'order_items',
        'payments',
        'recipes',
        'recipe_ingredients',
        'announcements',
        'activity_logs',
        'notifications',
    ];

    /**
     * Children must be dropped before the tables they reference.
     *
     * @var array<int, string>
     */
    private const DROP_ORDER = [
        'notifications',
        'activity_logs',
        'announcements',
        'recipe_ingredients',
        'recipes',
        'payments',
        'order_items',
        'orders',
        'products',
        'categories',
        'users',
    ];

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

    private bool $guarded = false;

    /**
     * Prevent LazilyRefreshDatabase from migrating the MySQL target.
     */
    public function refreshDatabase(): void {}

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireDisposableMySql();

        // Allow tables left by a previous failed run, but never drop a table
        // this test does not explicitly own.
        $this->assertNoUnexpectedTables();

        $this->guarded = true;

        $this->dropOwnedTables();
        $this->assertDatabaseIsEmpty();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->guarded) {
                $this->dropOwnedTables();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_legacy_myisam_tables_are_converted_to_innodb_and_preserve_rows(): void
    {
        $this->createLegacySchema('MyISAM');
        $this->seedRepresentativeData();

        $this->assertAllActiveTablesUseEngine('MyISAM');
        $this->assertNoForeignKeys();

        $before = $this->snapshotTables(self::LEGACY_TABLES);

        $this->runMigration(self::CONVERSION_MIGRATION);

        $this->assertAllActiveTablesUseEngine('InnoDB');
        $this->assertSame($before, $this->snapshotTables(self::LEGACY_TABLES));
        $this->assertMigrationHistoryNotCreated();
    }

    public function test_legacy_conversion_restores_all_expected_foreign_keys_and_delete_rules(): void
    {
        $this->createLegacySchema('MyISAM');
        $this->seedRepresentativeData();

        $this->runMigration(self::CONVERSION_MIGRATION);

        $this->assertAllActiveTablesUseEngine('InnoDB');
        $this->assertExpectedForeignKeys();
        $this->assertNoOrphans();
        $this->assertMigrationHistoryNotCreated();
    }

    public function test_orphaned_rows_abort_before_any_engine_change(): void
    {
        $this->createLegacySchema('MyISAM');
        $this->seedRepresentativeData();
        $this->seedOrphanedProduct();

        $before = $this->snapshotTables(self::LEGACY_TABLES);

        try {
            $this->runMigration(self::CONVERSION_MIGRATION);

            $this->fail('Expected the conversion migration to abort on orphaned rows.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'orphaned products.user_id',
                $exception->getMessage()
            );
        }

        $this->assertAllActiveTablesUseEngine('MyISAM');
        $this->assertNoForeignKeys();
        $this->assertSame($before, $this->snapshotTables(self::LEGACY_TABLES));
        $this->assertMigrationHistoryNotCreated();
    }

    public function test_migration_is_idempotent_on_an_already_innodb_schema(): void
    {
        $this->createLegacySchema('InnoDB');
        $this->seedRepresentativeData();

        $this->assertAllActiveTablesUseEngine('InnoDB');
        $this->assertNoForeignKeys();

        $before = $this->snapshotTables(self::LEGACY_TABLES);

        $this->runMigration(self::CONVERSION_MIGRATION);
        $this->assertExpectedForeignKeys();

        $this->runMigration(self::CONVERSION_MIGRATION);

        $this->assertAllActiveTablesUseEngine('InnoDB');
        $this->assertExpectedForeignKeys();
        $this->assertNoOrphans();
        $this->assertSame($before, $this->snapshotTables(self::LEGACY_TABLES));
        $this->assertMigrationHistoryNotCreated();
    }

    public function test_notifications_migration_converts_legacy_myisam_table(): void
    {
        $this->createNotifications('MyISAM');
        $this->seedNotification();

        $this->assertAllActiveTablesUseEngine('MyISAM');

        $before = $this->snapshotTables(['notifications']);

        $this->runMigration(self::NOTIFICATIONS_MIGRATION);

        $this->assertAllActiveTablesUseEngine('InnoDB');
        $this->assertSame($before, $this->snapshotTables(['notifications']));
        $this->assertMigrationHistoryNotCreated();
    }

    public function test_notifications_migration_is_noop_for_an_already_innodb_table(): void
    {
        $this->createNotifications('InnoDB');
        $this->seedNotification();

        $this->assertAllActiveTablesUseEngine('InnoDB');

        $before = $this->snapshotTables(['notifications']);

        $this->runMigration(self::NOTIFICATIONS_MIGRATION);

        $this->assertAllActiveTablesUseEngine('InnoDB');
        $this->assertSame($before, $this->snapshotTables(['notifications']));
        $this->assertMigrationHistoryNotCreated();
    }

    public function test_notifications_migration_is_noop_when_table_is_absent(): void
    {
        $this->assertFalse(Schema::hasTable('notifications'));

        $this->runMigration(self::NOTIFICATIONS_MIGRATION);

        $this->assertFalse(Schema::hasTable('notifications'));
        $this->assertSame([], $this->activeTableNames());
        $this->assertMigrationHistoryNotCreated();
    }

    /**
     * Reject every connection except the exact local disposable database.
     */
    private function requireDisposableMySql(): void
    {
        if (! filter_var(env(self::RUN_ENV, false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped(
                'Set '.self::RUN_ENV.'=1 to run MySQL legacy conversion checks.'
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

    /**
     * Read-only check for MyISAM availability.
     */
    private function requireMyIsamEngine(): void
    {
        try {
            $engine = DB::selectOne(
                'select SUPPORT as support from information_schema.ENGINES where ENGINE = ?',
                ['MyISAM']
            );
        } catch (\Throwable $exception) {
            $this->markTestSkipped(
                'Unable to determine MyISAM availability from information_schema.ENGINES: '.
                $exception->getMessage()
            );
        }

        if ($engine === null
            || ! in_array(strtoupper((string) $engine->support), ['YES', 'DEFAULT'], true)) {
            $this->markTestSkipped(
                'MyISAM is not available on this MySQL/MariaDB server; '.
                'the legacy conversion scenario cannot be exercised.'
            );
        }
    }

    /**
     * Allow only tables this test owns, including leftovers from a failed run.
     */
    private function assertNoUnexpectedTables(): void
    {
        $unexpected = array_values(
            array_diff($this->activeTableNames(), self::OWNED_TABLES)
        );

        $this->assertSame(
            [],
            $unexpected,
            'The target database contains tables this test does not own: '.
            implode(', ', $unexpected).'.'
        );
    }

    private function assertDatabaseIsEmpty(): void
    {
        $this->assertSame(
            [],
            $this->activeTableNames(),
            'The target database must be empty after test cleanup.'
        );
    }

    /**
     * Drop only explicitly owned tables, children before parents.
     */
    private function dropOwnedTables(): void
    {
        foreach (self::DROP_ORDER as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * @return array<int, string>
     */
    private function activeTableNames(): array
    {
        $database = $this->activeDatabaseName();

        return collect(DB::connection()->getSchemaBuilder()->getTables($database))
            ->filter(fn (array $table): bool => $table['schema'] === $database)
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function activeTablesByName(): array
    {
        $database = $this->activeDatabaseName();

        return collect(DB::connection()->getSchemaBuilder()->getTables($database))
            ->filter(fn (array $table): bool => $table['schema'] === $database)
            ->keyBy(fn (array $table): string => strtolower($table['name']))
            ->all();
    }

    private function activeDatabaseName(): string
    {
        $database = DB::connection()->getDatabaseName();

        if (! is_string($database) || $database === '') {
            throw new RuntimeException('Cannot inspect schema without an active database name.');
        }

        return $database;
    }

    private function assertAllActiveTablesUseEngine(string $engine): void
    {
        $tables = $this->activeTablesByName();

        $this->assertNotEmpty($tables, 'No tables were found in the target database.');

        foreach ($tables as $name => $table) {
            $this->assertSame(
                0,
                strcasecmp((string) ($table['engine'] ?? ''), $engine),
                "{$name} is not using {$engine}."
            );
        }
    }

    private function assertExpectedForeignKeys(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        foreach (self::EXPECTED_FOREIGN_KEYS as [$table, $column, $referencedTable, $onDelete]) {
            $foreignKeys = collect($schema->getForeignKeys($table))
                ->filter(fn (array $foreignKey): bool => in_array(
                    strtolower($column),
                    array_map('strtolower', $foreignKey['columns']),
                    true
                ))
                ->values();

            $this->assertCount(
                1,
                $foreignKeys,
                "Expected exactly one foreign key on {$table}.{$column}."
            );

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
            // 080216 creates constraints as "{table}_{column}_foreign".
            $this->assertSame(
                "{$table}_{$column}_foreign",
                $foreignKey['name'],
                "{$table}.{$column} has an unexpected constraint name."
            );
        }
    }

    private function assertNoForeignKeys(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        foreach (array_unique(array_column(self::EXPECTED_FOREIGN_KEYS, 0)) as $table) {
            $this->assertSame(
                [],
                $schema->getForeignKeys($table),
                "Expected no foreign keys on {$table}."
            );
        }
    }

    private function assertNoOrphans(): void
    {
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
     * @param  array<int, string>  $tables
     * @return array<string, string>
     */
    private function snapshotTables(array $tables): array
    {
        $snapshot = [];

        foreach ($tables as $table) {
            $snapshot[$table] = json_encode(
                DB::table($table)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $row): array => (array) $row)
                    ->all(),
                JSON_THROW_ON_ERROR
            );
        }

        return $snapshot;
    }

    private function assertMigrationHistoryNotCreated(): void
    {
        $this->assertFalse(
            Schema::hasTable('migrations'),
            'Direct migration invocation must not create or modify the migrations table.'
        );
    }

    private function runMigration(string $filename): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/'.$filename);

        $migration->up();
    }

    /**
     * Build the minimum representative legacy schema without foreign keys.
     */
    private function createLegacySchema(string $engine): void
    {
        if (strcasecmp($engine, 'MyISAM') === 0) {
            $this->requireMyIsamEngine();
        }

        Schema::create('users', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->string('name');
        });

        Schema::create('categories', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->string('name');
        });

        Schema::create('products', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('name');
        });

        Schema::create('orders', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('buyer_id')->index();
            $table->unsignedBigInteger('farmer_id')->index();
            $table->string('order_number');
        });

        Schema::create('order_items', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->integer('quantity');
        });

        Schema::create('payments', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->decimal('amount', 10, 2);
        });

        Schema::create('recipes', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->string('title');
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('recipe_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('name');
        });

        Schema::create('announcements', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title');
        });

        Schema::create('activity_logs', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action');
        });
    }

    private function createNotifications(string $engine): void
    {
        if (strcasecmp($engine, 'MyISAM') === 0) {
            $this->requireMyIsamEngine();
        }

        Schema::create('notifications', function (Blueprint $table) use ($engine): void {
            $table->engine = $engine;

            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_read_index'
            );
        });
    }

    private function seedRepresentativeData(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Legacy Farmer'],
            ['id' => 2, 'name' => 'Legacy Buyer'],
        ]);

        DB::table('categories')->insert([
            ['id' => 1, 'name' => 'Legacy Category'],
        ]);

        DB::table('products')->insert([
            ['id' => 1, 'user_id' => 1, 'category_id' => 1, 'name' => 'Legacy Product'],
            ['id' => 2, 'user_id' => 2, 'category_id' => null, 'name' => 'Legacy Product Without Category'],
        ]);

        DB::table('orders')->insert([
            ['id' => 1, 'buyer_id' => 2, 'farmer_id' => 1, 'order_number' => 'LEGACY-0001'],
        ]);

        DB::table('order_items')->insert([
            ['id' => 1, 'order_id' => 1, 'product_id' => 1, 'quantity' => 2],
        ]);

        DB::table('payments')->insert([
            ['id' => 1, 'order_id' => 1, 'amount' => '10.00'],
        ]);

        DB::table('recipes')->insert([
            ['id' => 1, 'title' => 'Legacy Recipe'],
        ]);

        DB::table('recipe_ingredients')->insert([
            ['id' => 1, 'recipe_id' => 1, 'category_id' => 1, 'name' => 'Legacy Ingredient'],
            ['id' => 2, 'recipe_id' => 1, 'category_id' => null, 'name' => 'Legacy Ingredient Without Category'],
        ]);

        DB::table('announcements')->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'Legacy Announcement'],
        ]);

        DB::table('activity_logs')->insert([
            ['id' => 1, 'user_id' => 1, 'action' => 'legacy.action'],
            ['id' => 2, 'user_id' => null, 'action' => 'legacy.system'],
        ]);
    }

    private function seedOrphanedProduct(): void
    {
        DB::table('products')->insert([
            'id' => 3,
            'user_id' => 999,
            'category_id' => 1,
            'name' => 'Orphaned Product',
        ]);
    }

    private function seedNotification(): void
    {
        DB::table('notifications')->insert([
            'id' => '00000000-0000-0000-0000-000000000001',
            'type' => 'App\\Notifications\\LegacyNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => 1,
            'data' => json_encode(['message' => 'legacy notification'], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => '2026-10-31 00:00:00',
            'updated_at' => '2026-10-31 00:00:00',
        ]);
    }
}
