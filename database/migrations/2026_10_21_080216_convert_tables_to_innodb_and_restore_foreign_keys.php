<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The legacy database was imported with MyISAM tables, which silently
     * ignored every foreign key and did not support transactions.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn (object $row): string => (string) array_values((array) $row)[0]);

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->replaceForeignKey('products', 'user_id', 'users', 'RESTRICT');
            $this->replaceForeignKey('products', 'category_id', 'categories', 'SET NULL');
            $this->replaceForeignKey('orders', 'buyer_id', 'users', 'RESTRICT');
            $this->replaceForeignKey('orders', 'farmer_id', 'users', 'RESTRICT');
            $this->replaceForeignKey('order_items', 'order_id', 'orders', 'CASCADE');
            $this->replaceForeignKey('order_items', 'product_id', 'products', 'RESTRICT');
            $this->replaceForeignKey('payments', 'order_id', 'orders', 'CASCADE');
            $this->replaceForeignKey('recipe_ingredients', 'recipe_id', 'recipes', 'CASCADE');
            $this->replaceForeignKey('recipe_ingredients', 'category_id', 'categories', 'SET NULL');
            $this->replaceForeignKey('announcements', 'user_id', 'users', 'CASCADE');
            $this->replaceForeignKey('activity_logs', 'user_id', 'users', 'SET NULL');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Keep the tables on InnoDB when rolling back, because reverting to MyISAM
     * would remove transaction support and silently disable the constraints.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->foreignKeys() as [$table, $column]) {
                $constraint = "{$table}_{$column}_foreign";

                if ($this->hasForeignKey($table, $constraint)) {
                    DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * The Phase 2 migration owns the four order-history constraints and
     * removes them itself when that migration is rolled back.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['products', 'category_id'],
            ['order_items', 'order_id'],
            ['payments', 'order_id'],
            ['recipe_ingredients', 'recipe_id'],
            ['recipe_ingredients', 'category_id'],
            ['announcements', 'user_id'],
            ['activity_logs', 'user_id'],
        ];
    }

    private function replaceForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $onDelete
    ): void {
        $constraint = "{$table}_{$column}_foreign";

        if ($this->hasForeignKey($table, $constraint)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` ".
            "FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`id`) ON DELETE {$onDelete}"
        );
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.REFERENTIAL_CONSTRAINTS '.
            'WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [DB::getDatabaseName(), $table, $constraint]
        )->aggregate > 0;
    }
};
