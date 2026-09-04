<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('farmer_id')->constrained('users')->onDelete('cascade');
            $table->enum('order_type', ['standard', 'bulk'])->default('standard');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->enum('delivery_method', ['deliver', 'pickup'])->default('deliver');
            $table->text('delivery_address')->nullable();
            $table->decimal('shipping_fee', 8, 2)->default(0.00);
            $table->enum('status', ['pending', 'confirmed', 'delivered', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
