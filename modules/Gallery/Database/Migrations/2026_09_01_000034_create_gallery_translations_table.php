<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('title');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gallery_id', 'locale', 'market_id']);
        });

        Schema::create('market_gallery', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_id', 'gallery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_gallery');
        Schema::dropIfExists('gallery_translations');
    }
};
