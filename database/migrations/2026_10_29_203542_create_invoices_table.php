<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_platform_id')->nullable()->constrained();
            $table->string('invoice_number', 50)->unique();
            $table->date('invoice_date');
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30);
            $table->string('delivery_phone', 30);
            $table->text('delivery_address');
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->enum('payment_type', ['paid', 'cod']);
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->enum('delivery_status', ['pending', 'preparing', 'ready_for_dispatch', 'on_the_way', 'delivered', 'cancelled', 'failed_delivery', 'returned'])->default('pending');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
