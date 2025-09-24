<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->foreignId('user_id');
            $table->string('status')->default("pending");
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency')->default("USD");
            $table->text('billing_address')->nullable();
            $table->text('shipping_address');
            $table->string('payment_status')->default("pending");
            $table->string('payment_method')->nullable();
            $table->string('payment_screenshot')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->foreignId('township_delivery_id')->nullable();
            $table->foreignId('shipping_address_id')->nullable();
            $table->string('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->index(['user_id', 'status'], 'orders_user_id_status_index');
            $table->index('status', 'orders_status_index');
            $table->index('payment_status', 'orders_payment_status_index');
            $table->unique('order_number', 'orders_order_number_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('township_delivery_id')->references('id')->on('township_delivery_prices');
            $table->foreign('shipping_address_id')->references('id')->on('addresses');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
