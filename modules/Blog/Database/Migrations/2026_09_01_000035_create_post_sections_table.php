<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedSmallInteger('order')->default(0);
            $table->json('styling_json')->nullable(); // animations, scroll_magic, container_style
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('published')->index(); // draft|published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('post_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_section_id')->constrained('post_sections')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->json('content_json'); // Localized parameters, text, or HTML
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['post_section_id', 'locale', 'market_id'], 'post_sec_trans_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_section_translations');
        Schema::dropIfExists('post_sections');
    }
};
