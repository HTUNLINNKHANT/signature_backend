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
        Schema::create('township_delivery_prices', function (Blueprint $table) {
            $table->id();
            $table->string('township_name');
            $table->string('township_name_mm')->nullable();
            $table->string('state_region');
            $table->string('state_region_mm')->nullable();
            $table->decimal('delivery_price', 15, 2);
            $table->string('estimated_days')->default(1);
            $table->string('is_active')->default(1);
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('is_active', 'township_delivery_prices_is_active_index');
            $table->index(['township_name', 'state_region'], 'township_delivery_prices_township_name_state_region_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('township_delivery_prices');
    }
};
