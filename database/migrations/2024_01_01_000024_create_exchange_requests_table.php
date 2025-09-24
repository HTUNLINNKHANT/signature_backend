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
        Schema::create('exchange_requests', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->date('date_of_purchase');
            $table->string('email_address');
            $table->string('phone_number');
            $table->string('product_to_exchange_name');
            $table->string('size');
            $table->string('item_color');
            $table->string('reason_for_exchange');
            $table->string('replacement_request_item');
            $table->string('replacement_request_name');
            $table->text('additional_comments')->nullable();
            $table->string('status')->default("pending");
            $table->text('admin_notes')->nullable();
            $table->string('processed_by')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('email_address', 'exchange_requests_email_address_index');
            $table->index('order_number', 'exchange_requests_order_number_index');
            $table->index(['status', 'created_at'], 'exchange_requests_status_created_at_index');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_requests');
    }
};
