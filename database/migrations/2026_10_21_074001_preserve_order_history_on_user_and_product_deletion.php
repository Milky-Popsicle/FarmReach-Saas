<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL/MariaDB is intentionally handled by the next migration.
     *
     * Legacy copies of this database can contain MyISAM tables whose foreign
     * keys were never enforced. Dropping those constraints here would fail
     * before the conversion migration had a chance to repair the schema.
     */
    public function up(): void
    {
        if ($this->usesMySql()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['farmer_id']);
            $table->foreign('farmer_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    /**
     * MySQL/MariaDB rollback is owned by the conversion migration.
     */
    public function down(): void
    {
        if ($this->usesMySql()) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['farmer_id']);
            $table->foreign('farmer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function usesMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
