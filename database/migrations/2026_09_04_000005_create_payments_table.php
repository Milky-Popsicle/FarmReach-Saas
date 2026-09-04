<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('payment_method', ['cod', 'bank_transfer', 'gcash'])->default('cod');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
