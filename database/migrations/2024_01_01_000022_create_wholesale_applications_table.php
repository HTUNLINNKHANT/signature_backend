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
        Schema::create('wholesale_applications', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('business_type')->default("retailer");
            $table->string('full_name');
            $table->string('email');
            $table->string('phone_number');
            $table->string('country_region');
            $table->string('city');
            $table->string('state_province')->nullable();
            $table->text('product_interests');
            $table->text('additional_information')->nullable();
            $table->text('shipping_address');
            $table->string('status')->default("pending");
            $table->text('admin_notes')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('email', 'wholesale_applications_email_index');
            $table->index(['status', 'created_at'], 'wholesale_applications_status_created_at_index');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_applications');
    }
};
