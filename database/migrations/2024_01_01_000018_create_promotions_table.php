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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('type')->default("percentage");
            $table->decimal('value', 15, 2);
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->decimal('maximum_discount', 15, 2)->nullable();
            $table->string('usage_limit')->nullable();
            $table->string('usage_limit_per_customer')->nullable();
            $table->string('used_count')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('is_active')->default(1);
            $table->string('applies_to')->default("all");
            $table->text('conditions')->nullable();
            $table->string('created_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique('slug', 'promotions_slug_unique');
            $table->index('slug', 'promotions_slug_index');
            $table->index(['is_active', 'start_date', 'end_date'], 'promotions_is_active_start_date_end_date_index');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
