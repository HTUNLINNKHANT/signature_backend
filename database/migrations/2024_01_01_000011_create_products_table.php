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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->string('category')->nullable();
            $table->string('in_stock')->default(0);
            $table->text('features')->nullable();
            $table->string('hover_image')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->foreignId('brand_id')->nullable();
            $table->string('slug');
            $table->text('tags')->nullable();
            $table->string('is_active')->default(1);
            $table->string('is_featured')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('product_images')->nullable();
            $table->string('product_code');
            $table->decimal('promotional_price', 15, 2)->nullable();
            $table->dateTime('promotional_price_set_at')->nullable();
            $table->foreignId('applied_promotion_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique('product_code', 'products_product_code_unique');
            $table->unique('slug', 'products_slug_unique');
            $table->index(['is_active', 'is_featured'], 'products_is_active_is_featured_index');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
