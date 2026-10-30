<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('image')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand')->nullable()->index();
            $table->longText('description')->nullable();
            $table->longText('specifications')->nullable();
            $table->json('questions')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->decimal('regular_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sold_count')->default(0);
            $table->string('category_id');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('product_id')->index();
            $table->text('url');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('og_image')->nullable();
            $table->timestamps();
        });

        Schema::create('page_blocks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('page_id')->index();
            $table->string('type');
            $table->json('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->decimal('charge', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->text('address');
            $table->string('district')->nullable();
            $table->string('delivery_zone_id')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('status')->default('PENDING');
            $table->string('payment_method')->default('COD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zones')->nullOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('product_id')->nullable()->index();
            $table->string('product_name');
            $table->string('product_slug')->nullable();
            $table->text('image_url')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('page_blocks');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
