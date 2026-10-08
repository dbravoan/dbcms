<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'locale', 'market_id']);
            $table->index(['locale', 'slug'], 'category_translations_locale_slug_idx');
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('featured_image')->nullable();
            $table->unsignedSmallInteger('reading_time_minutes')->default(1);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true)->index();
            $table->boolean('allow_comments')->default(true);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['is_published', 'published_at'], 'posts_published_idx');
        });

        Schema::create('post_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('title');
            $table->string('slug')->index();
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('schema_type')->default('BlogPosting');
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'locale', 'market_id']);
            $table->index(['locale', 'slug'], 'post_translations_locale_slug_idx');
        });

        Schema::create('market_post', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_id', 'post_id']);
        });

        Schema::create('market_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_id', 'category_id']);
        });

        Schema::create('post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('content');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('market_category');
        Schema::dropIfExists('market_post');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
    }
};
