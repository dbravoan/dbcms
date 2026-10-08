<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityExecutor;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;
use Modules\Gallery\Models\Gallery;

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->otherMarket = Market::firstOrCreate(['code' => 'mx'], ['name' => 'Mexico', 'default_locale' => 'es', 'is_active' => true]);
    $this->user = User::factory()->create();
    $this->user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    $this->actingAs($this->user);
    $this->input = ['translations' => ['es' => ['title' => 'Galería compartida', 'description' => 'Descripción ES', 'meta_title' => 'SEO ES', 'meta_description' => 'DESC ES'], 'en' => ['title' => 'Shared gallery', 'description' => 'Description EN', 'meta_title' => 'SEO EN']], 'market_ids' => [$this->market->getKey()], 'items_json' => json_encode([['image_url' => '/media/sample.webp', 'title' => 'First image', 'alt_text' => 'Accessible image', 'caption' => 'Caption']])];
});

test('gallery HTTP and native capability share defaults translations markets and ordered items', function (): void {
    $this->post(route('admin.galleries.store'), $this->input)->assertRedirect(route('admin.galleries.index'))->assertSessionHasNoErrors();
    $http = Gallery::with(['translations', 'items', 'markets'])->latest('id')->firstOrFail();
    $native = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'gallery'] + $this->input, new ActorContext((int) $this->user->getKey()));
    expect($native)->toHaveKey('id');
    $shared = Gallery::with(['translations', 'items', 'markets'])->findOrFail($native['id']);
    expect($http->is_active)->toBeFalse()->and($shared->is_active)->toBeFalse();
    expect($http->translations->map->only(['locale', 'title', 'description', 'meta_title', 'meta_description', 'schema_type'])->all())
        ->toBe($shared->translations->map->only(['locale', 'title', 'description', 'meta_title', 'meta_description', 'schema_type'])->all());
    expect($http->translations->first()->schema_type)->toBe('ImageGallery');
    expect($http->items->map->only(['image_url', 'title', 'alt_text', 'caption', 'order'])->all())->toBe($shared->items->map->only(['image_url', 'title', 'alt_text', 'caption', 'order'])->all());
    expect($http->items->first()->alt_text)->toBe('Accessible image');
    expect($shared->markets->modelKeys())->toBe([$this->market->getKey()]);
});

test('published gallery updates preserve live items and markets until human approval', function (): void {
    $gallery = app(GalleryEditorialServiceInterface::class)->create($this->input);
    Gallery::withoutEvents(fn () => $gallery->update(['is_active' => true]));
    $originalItems = $gallery->items()->get()->map->getAttributes()->all();
    $input = ['is_active' => '1', 'translations' => ['es' => ['title' => 'Pending album', 'meta_title' => 'Pending SEO', 'meta_description' => 'Pending description']], 'market_ids' => [$this->otherMarket->getKey()], 'items_json' => json_encode([['image_url' => '/media/pending.webp', 'title' => 'Pending image', 'caption' => 'Pending caption']])];
    $this->put(route('admin.galleries.update', $gallery), $input)->assertRedirect()->assertSessionHasNoErrors();
    $live = $gallery->fresh(['translations', 'items', 'markets']);
    expect($live->is_active)->toBeTrue()->and($live->title)->toBe('Galería compartida');
    expect($live->items->map->getAttributes()->all())->toBe($originalItems);
    expect($live->markets->modelKeys())->toBe([$this->market->getKey()]);
    $this->get(route('admin.galleries.edit', $gallery))->assertOk()->assertViewHas('gallery', fn (Gallery $draft): bool => $draft->title === 'Pending album' && $draft->items->first()->image_url === '/media/pending.webp' && $draft->markets->modelKeys() === [$this->otherMarket->getKey()]);
    $pending = app(GalleryEditorialServiceInterface::class)->editData($live)['gallery'];
    expect($pending->translations->firstWhere('locale', 'es')->meta_title)->toBe('Pending SEO');
    app(ContentAuditServiceInterface::class)->approve($live, $this->user);
    $published = $gallery->fresh(['translations', 'items', 'markets']);
    expect($published->is_active)->toBeTrue()->and($published->title)->toBe('Pending album');
    expect($published->items->first()->image_url)->toBe('/media/pending.webp');
    expect($published->markets->modelKeys())->toBe([$this->otherMarket->getKey()]);
    expect($published->translations->firstWhere('locale', 'es')->meta_description)->toBe('Pending description');
});

test('gallery partial updates preserve items and malformed unsafe items fail before mutation', function (): void {
    $gallery = app(GalleryEditorialServiceInterface::class)->create($this->input);
    $items = $gallery->items()->get()->map->getAttributes()->all();
    $this->put(route('admin.galleries.update', $gallery), ['translations' => ['es' => ['title' => 'Title patch']]])->assertRedirect()->assertSessionHasNoErrors();
    expect($gallery->items()->get()->map->getAttributes()->all())->toBe($items);
    foreach (['{broken', json_encode([['image_url' => 'javascript:alert(1)']])] as $json) {
        $this->put(route('admin.galleries.update', $gallery), ['translations' => ['es' => ['title' => 'Rejected']], 'items_json' => $json])->assertSessionHasErrors('items_json');
        expect($gallery->fresh()->title)->toBe('Title patch');
        expect($gallery->items()->get()->map->getAttributes()->all())->toBe($items);
    }
});
