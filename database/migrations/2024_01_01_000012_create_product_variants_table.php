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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('sku');
            $table->string('color');
            $table->string('color_code')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->string('is_active')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->text('sizes')->nullable();
            $table->unique(['product_id', 'color'], 'unique_product_color');
            $table->unique('sku', 'product_variants_sku_unique');
            $table->index('sku', 'product_variants_sku_index');
            $table->index(['product_id', 'color'], 'product_variants_product_id_color_index');
            $table->index(['product_id', 'is_active'], 'product_variants_product_id_is_active_index');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
