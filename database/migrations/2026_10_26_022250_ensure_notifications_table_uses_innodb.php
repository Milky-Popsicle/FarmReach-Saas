<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure databases that created the notifications table before it declared
     * InnoDB are converted as well. Fresh databases already create it as InnoDB.
     */
    public function up(): void
    {
        $connection = DB::connection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasTable('notifications')) {
            return;
        }

        $database = $connection->getDatabaseName();

        if (! is_string($database) || $database === '') {
            throw new RuntimeException('Cannot inspect notifications without an active database name.');
        }

        $notifications = collect($schema->getTables($database))
            ->filter(fn (array $table): bool => $table['schema'] === $database)
            ->firstWhere('name', 'notifications');

        if ($notifications !== null && strcasecmp((string) $notifications['engine'], 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE `notifications` ENGINE=InnoDB');
        }
    }

    /**
     * Keep the table on InnoDB; reverting to the server default could put it
     * back on MyISAM.
     */
    public function down(): void
    {
        //
    }
};
