<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityExecutor;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\CmsModule;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\ContentAudit\Services\EditorialWorkflow;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;
use Modules\Shop\Schema\ShopEntitySchemas;
use Modules\Shop\ShopModuleServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\BlogShopAuditComposition;

uses(BlogShopAuditComposition::class);

beforeEach(function (): void {
    foreach (ShopEntitySchemas::all() as $schema) {
        app(EntitySchemaRegistryInterface::class)->register($schema);
    }
    CmsModule::updateOrCreate(['identifier' => 'shop'], ['name' => 'Shop', 'provider' => ShopModuleServiceProvider::class, 'is_installed' => true, 'is_active' => true]);
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->attach($role);
    $this->actingAs($this->user);
    $this->actor = new ActorContext($this->user->id);
});

it('shares product defaults translations block body and zero market prices between web and native executor', function (): void {
    $input = ['price' => 12, 'market_ids' => [$this->market->id], 'market_prices' => [$this->market->id => 0], 'translations' => ['es' => ['name' => 'Same product']], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => '<p>Product block body</p>']]])];
    $this->post(route('admin.shop.products.store'), ['sku' => 'WEB-PARITY'] + $input)->assertRedirect()->assertSessionHasNoErrors();
    $web = Product::where('sku', 'WEB-PARITY')->firstOrFail();
    $result = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'product', 'attributes' => ['sku' => 'NATIVE-PARITY', 'price' => 12, 'market_prices' => [$this->market->id => 0]], 'market_ids' => $input['market_ids'], 'translations' => $input['translations'], 'sections_json' => $input['sections_json']], $this->actor);
    expect($result)->toHaveKey('id');
    $native = Product::findOrFail($result['id']);
    expect($web->only(['price', 'stock_quantity', 'track_stock', 'is_featured', 'is_active']))->toBe($native->only(['price', 'stock_quantity', 'track_stock', 'is_featured', 'is_active']));
    expect($native->is_active)->toBeFalse();
    expect($web->translations->first()->only(['name', 'summary', 'description', 'meta_title', 'meta_description', 'schema_type']))->toBe($native->translations->first()->only(['name', 'summary', 'description', 'meta_title', 'meta_description', 'schema_type']));
    expect($native->translations->first()->description)->toBe('<p>Product block body</p>');
    expect($native->translations->first()->schema_type)->toBe('Product');
    expect($web->load('markets')->getPriceForMarket($this->market))->toBe(0.0);
    expect($native->load('markets')->getPriceForMarket($this->market))->toBe(0.0);
    expect($native->sections()->count())->toBe(1);
});

it('rejects invalid product amounts through both transports without creating records', function (array $attributes, string $field): void {
    $translations = ['es' => ['name' => 'Invalid product']];
    $this->post(route('admin.shop.products.store'), $attributes + ['sku' => 'INVALID-WEB', 'price' => 10, 'translations' => $translations])->assertSessionHasErrors($field);
    $result = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'product', 'attributes' => $attributes + ['sku' => 'INVALID-NATIVE', 'price' => 10], 'translations' => $translations], $this->actor);
    expect($result)->toHaveKey('validation');
    expect(Product::whereIn('sku', ['INVALID-WEB', 'INVALID-NATIVE'])->count())->toBe(0);
})->with([
    'price' => [['price' => -1], 'price'],
    'stock' => [['stock_quantity' => -1], 'stock_quantity'],
    'compare' => [['compare_price' => -1], 'compare_price'],
    'custom' => [['market_prices' => [1 => -1]], 'market_prices.1'],
]);

it('stages published product price translations and pivots while preserving live stock and price', function (): void {
    $product = Model::withoutEvents(fn () => Product::create(['sku' => 'LIVE-PARITY', 'price' => 20, 'stock_quantity' => 4, 'is_active' => true]));
    $product->translations()->create(['locale' => 'es', 'name' => 'Live product', 'slug' => 'live-product']);
    $product->markets()->attach($this->market, ['custom_price' => 9, 'is_active' => true]);
    $this->put(route('admin.shop.products.update', $product), ['sku' => $product->sku, 'price' => 30, 'is_active' => '1', 'market_ids' => [$this->market->id], 'market_prices' => [$this->market->id => 0], 'translations' => ['es' => ['name' => 'Pending product']]])->assertRedirect()->assertSessionHasNoErrors();
    expect($product->fresh()->price)->toBe(20.0);
    expect($product->fresh()->stock_quantity)->toBe(4);
    expect($product->fresh()->load('markets')->getPriceForMarket($this->market))->toBe(9.0);
    $this->get(route('admin.shop.products.edit', $product))->assertOk()->assertViewHas('product', fn (Product $draft): bool => $draft->price === 30.0 && $draft->getPriceForMarket($this->market) === 0.0 && $draft->translations->first()->name === 'Pending product');
    app(EditorialWorkflow::class)->approve($product->fresh(), $this->user);
    expect($product->fresh()->price)->toBe(30.0);
    expect($product->fresh()->load('markets')->getPriceForMarket($this->market))->toBe(0.0);
});

it('rejects approval if live inventory changed after the draft was staged', function (): void {
    $product = Model::withoutEvents(fn () => Product::create(['sku' => 'STOCK-CONFLICT', 'price' => 20, 'stock_quantity' => 4, 'is_active' => true]));
    $product->translations()->create(['locale' => 'es', 'name' => 'Stock conflict', 'slug' => 'stock-conflict']);
    $result = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'product', 'id' => $product->id, 'attributes' => ['price' => 30]], $this->actor);
    expect($result['live_preserved'] ?? false)->toBeTrue();
    Model::withoutEvents(fn () => $product->refresh()->decrement('stock_quantity'));
    expect(fn () => app(EditorialWorkflow::class)->approve($product->fresh(), $this->user))->toThrow(HttpException::class);
    expect($product->fresh()->stock_quantity)->toBe(3);
    expect($product->fresh()->price)->toBe(20.0);
});

it('rejects duplicate SKUs and requires persisted human approval before native publication', function (): void {
    $existing = Product::create(['sku' => 'UNIQUE-PARITY', 'price' => 5, 'is_active' => false]);
    $input = ['sku' => $existing->sku, 'price' => 10, 'translations' => ['es' => ['name' => 'Duplicate']]];
    $this->post(route('admin.shop.products.store'), $input)->assertSessionHasErrors('sku');
    $result = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'product', 'attributes' => ['sku' => $existing->sku], 'translations' => $input['translations']], $this->actor);
    expect($result)->toHaveKey('validation');
    $publish = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'product', 'id' => $existing->id, 'publish' => true, 'confirm' => true], $this->actor);
    expect($publish['confirmation_required'] ?? false)->toBeTrue();
    expect($existing->fresh()->is_active)->toBeFalse();
});

it('shares category defaults validates ancestry and deletes through the same writer', function (): void {
    $this->post(route('admin.shop.categories.store'), [])->assertRedirect()->assertSessionHasNoErrors();
    $web = ProductCategory::latest('id')->firstOrFail();
    $result = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'shop_category'], $this->actor);
    expect($result)->toHaveKey('id');
    $native = ProductCategory::findOrFail($result['id']);
    expect($web->only(['icon', 'order', 'is_active']))->toBe($native->only(['icon', 'order', 'is_active']));
    expect($native->is_active)->toBeFalse();
    expect($native->translations->first()->name)->toBe($web->translations->first()->name);
    $patch = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'shop_category', 'id' => $native->id, 'translations' => ['es' => ['description' => 'Patched description']], 'market_ids' => [$this->market->id]], $this->actor);
    expect($patch)->toHaveKey('id');
    expect($native->fresh()->translations->first()->name)->toBe('Nueva categoría');
    expect($native->fresh()->translations->first()->description)->toBe('Patched description');
    expect($native->fresh()->markets->modelKeys())->toBe([$this->market->id]);
    $this->put(route('admin.shop.categories.update', $web), ['parent_id' => $web->id])->assertSessionHasErrors('parent_id');
    $bad = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'shop_category', 'id' => $native->id, 'attributes' => ['parent_id' => $native->id]], $this->actor);
    expect($bad)->toHaveKey('validation');
    $child = ProductCategory::create(['parent_id' => $web->id, 'is_active' => false]);
    $this->delete(route('admin.shop.categories.destroy', $web))->assertRedirect();
    expect($child->fresh()->parent_id)->toBeNull();
    $deleted = app(CapabilityExecutor::class)->execute('cms_entity_delete', ['type' => 'shop_category', 'id' => $native->id], $this->actor, approved: true);
    expect($deleted['deleted'] ?? false)->toBeTrue();
    expect(ProductCategory::find($native->id))->toBeNull();
});
