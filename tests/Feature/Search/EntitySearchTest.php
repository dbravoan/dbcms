<?php

declare(strict_types=1);

use App\Contracts\Services\EntitySearchServiceInterface;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Forms\Models\CustomForm;
use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;
use Tests\Support\EntitySearchComposition;

uses(RefreshDatabase::class, EntitySearchComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    BlogFixtures::post();
    $form = CustomForm::create(['slug' => 'contacto', 'is_active' => true]);
    $form->translations()->create(['locale' => 'es', 'name' => 'Formulario de contacto']);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->service = app(EntitySearchServiceInterface::class);
    $this->actingAs($this->admin, 'web');
});

test('the registry exposes the whitelisted system sources', function (): void {
    expect($this->service->sources())->toContain('markets')
        ->toContain('languages')
        ->toContain('blog_categories')
        ->toContain('blog_posts')
        ->toContain('shop_categories')
        ->toContain('shop_products')
        ->toContain('gallery_albums')
        ->toContain('forms')
        ->toContain('users')
        ->toContain('roles');
});

test('unknown sources are rejected', function (): void {
    expect(fn () => $this->service->search('sql_injection_table'))
        ->toThrow(InvalidArgumentException::class);
});

test('value keys outside the allow-list are rejected', function (): void {
    expect(fn () => $this->service->search('markets', null, 'password'))
        ->toThrow(InvalidArgumentException::class);
});

test('markets can be searched and are returned with value and label', function (): void {
    $result = $this->service->search('markets');

    expect($result)->toHaveKeys(['results', 'has_more']);
    expect($result['results'])->not->toBeEmpty();

    $first = $result['results'][0];
    expect($first)->toHaveKeys(['value', 'label', 'icon', 'badge'])
        ->and($first['value'])->not->toBe('')
        ->and($first['badge'])->toBe('MARKET');
});

test('translated content is searchable through its translations', function (): void {
    $result = $this->service->search('blog_posts');

    expect($result['results'])->not->toBeEmpty();

    foreach ($result['results'] as $item) {
        expect($item['value'])->not->toBe('')
            ->and($item['label'])->not->toBe('');
    }
});

test('blog categories support the translated slug as an allowed value key', function (): void {
    $result = $this->service->search('blog_categories', null, 'translation.slug');

    expect($result['results'])->not->toBeEmpty();
    expect($result['results'][0]['value'])->toBeString();
});

test('selected values can be resolved into labels', function (): void {
    $found = $this->service->search('markets');

    expect($found['results'])->not->toBeEmpty();

    $value = $found['results'][0]['value'];
    $resolved = $this->service->resolve('markets', [$value]);

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['value'])->toBe($value);
    expect($resolved[0]['label'])->not->toBe('');
});

test('guests cannot query the entity search endpoint', function (): void {
    auth('web')->logout();
    $this->get(route('admin.api.entity-search', ['source' => 'markets']))
        ->assertRedirect(route('admin.login'));
});

test('authenticated admins receive whitelisted results from the endpoint', function (): void {
    $response = $this->actingAs($this->admin, 'web')
        ->getJson(route('admin.api.entity-search', ['source' => 'markets', 'per_page' => 5]));

    $response->assertOk();
    $response->assertJsonStructure([
        'results' => [['value', 'label', 'icon', 'badge']],
        'has_more',
    ]);
});

test('the endpoint rejects unknown sources with a validation error', function (): void {
    $this->actingAs($this->admin, 'web')
        ->getJson(route('admin.api.entity-search', ['source' => 'definitely_not_allowed']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source']);
});

test('the endpoint resolves preselected ids', function (): void {
    $found = $this->service->search('markets');
    $value = $found['results'][0]['value'];

    $response = $this->actingAs($this->admin, 'web')
        ->getJson(route('admin.api.entity-search', ['source' => 'markets', 'ids' => [$value]]));

    $response->assertOk();
    expect($response->json('results'))->toHaveCount(1);
});

test('the reusable search select renders single and multiple hidden inputs', function (): void {
    $single = $this->blade('<x-admin.search-select source="markets" name="market_id" :value="3" />');
    $single->assertSee('searchSelect(', false);
    $single->assertSee('entity-search', false);
    $single->assertSee('name="market_id"', false);

    $multiple = $this->blade('<x-admin.search-select source="roles" name="role_ids" multiple :values="[1, 2]" />');
    $multiple->assertSee('name="role_ids[]"', false);
});

test('content editors use the reusable ajax search select by default', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.blog.posts.create'));

    $response->assertOk();
    $response->assertSee('searchSelect(', false);
    $response->assertSee('blog_categories', false);
    $response->assertSee('entity-search', false);
});

test('forms are searchable through their translations', function (): void {
    $result = $this->service->search('forms', null, 'slug');

    expect($result['results'])->not->toBeEmpty();

    $contact = collect($result['results'])->firstWhere('value', 'contacto');
    expect($contact)->not->toBeNull();
    expect($contact['label'])->toBe('Formulario de contacto');
});

test('market-scoped sources are filtered by the inherited block markets', function (): void {
    $orphanMarket = Market::create([
        'name' => 'Isla Desierta',
        'code' => 'zz',
        'is_active' => true,
    ]);

    $unscoped = $this->service->search('blog_posts');
    $scoped = $this->service->search('blog_posts', null, null, null, null, [$orphanMarket->id]);

    expect($unscoped['results'])->not->toBeEmpty();
    expect($scoped['results'])->toBeEmpty();
});

test('contextual search selects expose the inherited context flag', function (): void {
    $scoped = $this->blade('<x-admin.search-select source="blog_posts" :contextual="true" />');
    $scoped->assertSee('\u0022contextual\u0022:true', false);

    $plain = $this->blade('<x-admin.search-select source="markets" />');
    $plain->assertSee('\u0022contextual\u0022:false', false);
});
