<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Product Categories
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('icon', 50)->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'locale'], 'prod_cat_trans_unique');
        });

        // 2. Products
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku', 64)->unique();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_digital')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();
            $table->json('specs_json')->nullable(); // Technical specifications key-value map
            $table->timestamps();
        });

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('schema_type')->default('Product');
            $table->timestamps();

            $table->unique(['product_id', 'locale'], 'prod_trans_unique');
            $table->index(['locale', 'slug'], 'product_translations_locale_slug_idx');
        });

        // 3. Market Product Pivot (Multi-Market Currency Pricing & Assignment)
        Schema::create('market_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('custom_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['market_id', 'product_id'], 'mkt_prod_unique');
            $table->index(['market_id', 'is_active'], 'market_product_active_idx');
        });

        // 3.b Market Product Category Pivot (Multi-Market Category Assignment)
        Schema::create('market_product_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_id', 'category_id'], 'mkt_prod_cat_unique');
        });

        // 4. Product Sections (Content Block Manager support)
        Schema::create('product_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('widget_key')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->json('styling_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('published')->index(); // draft|published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_section_id')->constrained('product_sections')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->json('content_json');
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_section_id', 'locale', 'market_id'], 'prod_sec_trans_unique');
        });

        // 5. Orders & Order Items
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('pending'); // pending, processing, completed, cancelled
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, paid, refunded
            $table->string('payment_method', 30)->default('card'); // card, stripe, bank_transfer, cod
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax_total', 10, 2)->default(0.00);
            $table->decimal('shipping_total', 10, 2)->default(0.00);
            $table->decimal('discount_total', 10, 2)->default(0.00);
            $table->decimal('grand_total', 10, 2)->default(0.00);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku', 64)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_section_translations');
        Schema::dropIfExists('product_sections');
        Schema::dropIfExists('market_product');
        Schema::dropIfExists('market_product_category');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_category_translations');
        Schema::dropIfExists('product_categories');
    }
};
