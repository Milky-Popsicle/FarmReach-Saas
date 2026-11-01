<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function protectedForeignKeys(): array
    {
        return [
            ['products', 'user_id', 'users', 'RESTRICT'],
            ['products', 'category_id', 'categories', 'SET NULL'],
            ['orders', 'buyer_id', 'users', 'RESTRICT'],
            ['orders', 'farmer_id', 'users', 'RESTRICT'],
            ['order_items', 'order_id', 'orders', 'CASCADE'],
            ['order_items', 'product_id', 'products', 'RESTRICT'],
            ['payments', 'order_id', 'orders', 'CASCADE'],
            ['recipe_ingredients', 'recipe_id', 'recipes', 'CASCADE'],
            ['recipe_ingredients', 'category_id', 'categories', 'SET NULL'],
            ['announcements', 'user_id', 'users', 'CASCADE'],
            ['activity_logs', 'user_id', 'users', 'SET NULL'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function originalForeignKeys(): array
    {
        return [
            ['products', 'user_id', 'users', 'CASCADE'],
            ['products', 'category_id', 'categories', 'SET NULL'],
            ['orders', 'buyer_id', 'users', 'CASCADE'],
            ['orders', 'farmer_id', 'users', 'CASCADE'],
            ['order_items', 'order_id', 'orders', 'CASCADE'],
            ['order_items', 'product_id', 'products', 'CASCADE'],
            ['payments', 'order_id', 'orders', 'CASCADE'],
            ['recipe_ingredients', 'recipe_id', 'recipes', 'CASCADE'],
            ['recipe_ingredients', 'category_id', 'categories', 'SET NULL'],
            ['announcements', 'user_id', 'users', 'CASCADE'],
            ['activity_logs', 'user_id', 'users', 'SET NULL'],
        ];
    }

    /**
     * Convert legacy MyISAM tables and restore the foreign keys that MyISAM
     * silently ignored.
     *
     * MySQL DDL is not transactional. Validate the data before making any
     * schema change, convert only tables that still need it, and make the
     * constraint replacement restartable so a failed attempt can be rerun.
     */
    public function up(): void
    {
        if (! $this->usesMySql()) {
            return;
        }

        $foreignKeys = $this->protectedForeignKeys();

        $this->guardAgainstOrphanedRows($foreignKeys);
        $this->convertNonInnoDbTables();
        $this->assertAllTablesUseInnoDb();
        $this->replaceForeignKeys($foreignKeys);
    }

    /**
     * Restore the original delete rules but keep every table on InnoDB.
     *
     * Do not roll this migration back on a production database that contains
     * order history; restore a backup or create a forward-fix migration instead.
     */
    public function down(): void
    {
        if (! $this->usesMySql()) {
            return;
        }

        $foreignKeys = $this->originalForeignKeys();

        $this->guardAgainstOrphanedRows($foreignKeys);
        $this->replaceForeignKeys($foreignKeys);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $foreignKeys
     */
    private function guardAgainstOrphanedRows(array $foreignKeys): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        $violations = [];

        foreach ($foreignKeys as [$table, $column, $referencedTable]) {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, $column)) {
                $violations[] = "{$table}.{$column} is missing";

                continue;
            }

            if (! $schema->hasTable($referencedTable) || ! $schema->hasColumn($referencedTable, 'id')) {
                $violations[] = "{$referencedTable}.id is missing for {$table}.{$column}";

                continue;
            }

            $orphanCount = DB::table($table.' as child')
                ->leftJoin($referencedTable.' as parent', 'child.'.$column, '=', 'parent.id')
                ->whereNotNull('child.'.$column)
                ->whereNull('parent.id')
                ->count();

            if ($orphanCount > 0) {
                $violations[] = "{$orphanCount} orphaned {$table}.{$column} row(s) reference missing {$referencedTable}.id values";
            }
        }

        if ($violations !== []) {
            throw new RuntimeException(
                'Cannot add foreign keys because the existing data is not valid: '.implode('; ', $violations).
                '. Clean or explicitly remap these rows, take a backup, and rerun the migration.'
            );
        }
    }

    private function convertNonInnoDbTables(): void
    {
        $tables = $this->activeDatabaseTables()
            ->reject(fn (array $table): bool => $this->isInnoDb($table['engine'] ?? null))
            ->pluck('name')
            ->sort()
            ->values();

        foreach ($tables as $table) {
            DB::statement('ALTER TABLE '.$this->quoteIdentifier($table).' ENGINE=InnoDB');
        }
    }

    private function assertAllTablesUseInnoDb(): void
    {
        $remaining = $this->activeDatabaseTables()
            ->reject(fn (array $table): bool => $this->isInnoDb($table['engine'] ?? null))
            ->pluck('name')
            ->sort()
            ->values();

        if ($remaining->isNotEmpty()) {
            throw new RuntimeException(
                'These tables could not be converted to InnoDB: '.$remaining->implode(', ').'.'
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function activeDatabaseTables(): \Illuminate\Support\Collection
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        if (! is_string($database) || $database === '') {
            throw new RuntimeException('Cannot inspect schema without an active database name.');
        }

        return collect($connection->getSchemaBuilder()->getTables($database))
            ->filter(fn (array $table): bool => $table['schema'] === $database)
            ->values();
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $foreignKeys
     */
    private function replaceForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as [$table, $column, $referencedTable, $onDelete]) {
            $existing = $this->foreignKeysForColumn($table, $column);

            if (count($existing) > 1) {
                throw new RuntimeException(
                    "Multiple foreign keys found on {$table}.{$column}; resolve them manually before rerunning the migration."
                );
            }

            if ($existing !== []) {
                $foreignKey = $existing[0];

                if (count($foreignKey['columns']) !== 1) {
                    throw new RuntimeException(
                        "A composite foreign key covers {$table}.{$column}; resolve it manually before rerunning the migration."
                    );
                }

                if ($this->matches($foreignKey, $column, $referencedTable, $onDelete)) {
                    continue;
                }

                if (! is_string($foreignKey['name']) || $foreignKey['name'] === '') {
                    throw new RuntimeException(
                        "The foreign key on {$table}.{$column} has no name and cannot be replaced safely."
                    );
                }

                $this->dropForeignKey($table, $foreignKey['name']);
            }

            $this->addForeignKey($table, $column, $referencedTable, $onDelete);
        }
    }

    /**
     * @return array<int, array{name: string|null, columns: list<string>, foreign_schema: string|null, foreign_table: string, foreign_columns: list<string>, on_update: string|null, on_delete: string|null}>
     */
    private function foreignKeysForColumn(string $table, string $column): array
    {
        return array_values(array_filter(
            DB::connection()->getSchemaBuilder()->getForeignKeys($table),
            fn (array $foreignKey): bool => in_array(
                strtolower($column),
                array_map('strtolower', $foreignKey['columns']),
                true
            )
        ));
    }

    /**
     * @param  array{name: string|null, columns: list<string>, foreign_schema: string|null, foreign_table: string, foreign_columns: list<string>, on_update: string|null, on_delete: string|null}  $foreignKey
     */
    private function matches(array $foreignKey, string $column, string $referencedTable, string $onDelete): bool
    {
        return count($foreignKey['columns']) === 1
            && strcasecmp($foreignKey['columns'][0], $column) === 0
            && strcasecmp($foreignKey['foreign_table'], $referencedTable) === 0
            && count($foreignKey['foreign_columns']) === 1
            && strcasecmp($foreignKey['foreign_columns'][0], 'id') === 0
            && strcasecmp((string) $foreignKey['on_delete'], $onDelete) === 0;
    }

    private function dropForeignKey(string $table, string $name): void
    {
        DB::statement(
            'ALTER TABLE '.$this->quoteIdentifier($table).' DROP FOREIGN KEY '.$this->quoteIdentifier($name)
        );
    }

    private function addForeignKey(string $table, string $column, string $referencedTable, string $onDelete): void
    {
        $constraint = "{$table}_{$column}_foreign";

        DB::statement(
            'ALTER TABLE '.$this->quoteIdentifier($table).
            ' ADD CONSTRAINT '.$this->quoteIdentifier($constraint).
            ' FOREIGN KEY ('.$this->quoteIdentifier($column).') REFERENCES '.
            $this->quoteIdentifier($referencedTable).' ('.$this->quoteIdentifier('id').') ON DELETE '.$onDelete
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function isInnoDb(?string $engine): bool
    {
        return is_string($engine) && strcasecmp($engine, 'InnoDB') === 0;
    }

    private function usesMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
