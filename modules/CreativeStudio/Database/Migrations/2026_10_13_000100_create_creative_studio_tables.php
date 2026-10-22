<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_campaigns', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('users');
            $t->string('mode', 20);
            $t->text('objective');
            $t->text('audience');
            $t->json('context_snapshot');
            $t->json('brand_snapshot');
            $t->string('source_type', 80)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unsignedBigInteger('budget_limit')->default(0);
            $t->string('currency', 3)->default('USD');
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->timestamps();
        });
        Schema::create('creative_campaign_translations', function (Blueprint $t): void {
            $t->id();
            $t->foreignUuid('campaign_id')->constrained('creative_campaigns')->cascadeOnDelete();
            $t->string('locale', 10);
            $t->string('title');
            $t->text('brief');
            $t->unique(['campaign_id', 'locale']);
        });
        Schema::create('creative_campaign_market', function (Blueprint $t): void {
            $t->foreignUuid('campaign_id')->constrained('creative_campaigns')->cascadeOnDelete();
            $t->foreignId('market_id')->constrained('markets');
            $t->primary(['campaign_id', 'market_id']);
        });
        Schema::create('creative_revisions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('campaign_id')->constrained('creative_campaigns');
            $t->foreignId('user_id')->constrained('users');
            $t->unsignedInteger('number');
            $t->string('locale', 10);
            $t->string('market', 10);
            $t->string('format', 20);
            $t->text('text');
            $t->text('destination_url')->nullable();
            foreach (['artifacts', 'context_snapshot', 'brand_snapshot', 'template_snapshot', 'provenance'] as $field) {
                $t->json($field);
            }
            $t->timestamps();
            $t->unique(['campaign_id', 'number']);
        });
        Schema::create('creative_input_assets', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('campaign_id')->constrained('creative_campaigns');
            $t->foreignId('user_id')->constrained('users');
            $t->json('artifact');
            $t->timestamps();
        });
        Schema::create('creative_budget_accounts', function (Blueprint $t): void {
            $t->unsignedInteger('id')->primary();
            $t->timestamps();
        });
        Schema::create('creative_generation_tasks', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('campaign_id')->constrained('creative_campaigns');
            $t->foreignId('user_id')->constrained('users');
            $t->string('request_key', 64);
            $t->string('request_hash', 64);
            $t->string('status', 25)->index();
            $t->unsignedBigInteger('reserved_cost');
            $t->unsignedBigInteger('confirmed_cost')->nullable();
            $t->text('payload');
            $t->text('stages');
            $t->foreignUuid('revision_id')->nullable()->constrained('creative_revisions');
            $t->timestamp('lease_until')->nullable();
            $t->uuid('claim_id')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'request_key']);
        });
    }

    public function down(): void
    {
        foreach (['creative_generation_tasks', 'creative_input_assets', 'creative_budget_accounts', 'creative_revisions', 'creative_campaign_market', 'creative_campaign_translations', 'creative_campaigns'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
