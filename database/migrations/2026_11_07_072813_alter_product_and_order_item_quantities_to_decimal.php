<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('quantity', 13, 3)->default(0)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('quantity', 13, 3)->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['products', 'order_items'] as $table) {
            if (DB::table($table)->whereRaw('quantity <> ROUND(quantity, 0)')->exists()) {
                throw new RuntimeException(
                    "Cannot roll back fractional quantities in {$table}. Convert all quantities to whole numbers before retrying."
                );
            }

            if (DB::table($table)
                ->whereRaw('quantity < ? OR quantity > ?', [-2_147_483_648, 2_147_483_647])
                ->exists()) {
                throw new RuntimeException(
                    "Cannot roll back quantities outside the original signed integer range in {$table}. Reduce them before retrying."
                );
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
        });
    }
};
