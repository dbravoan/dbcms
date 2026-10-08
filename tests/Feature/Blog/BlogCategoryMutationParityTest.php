<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityExecutor;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Blog\Models\Category;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;

beforeEach(function (): void {
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'Spain', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user->roles()->attach($role);
    $this->actingAs($this->user);
    $this->actor = new ActorContext($this->user->id);
});

it('shares draft category defaults and localized normalization between web and native capabilities', function (): void {
    $this->post(route('admin.blog.categories.store'), [])->assertRedirect()->assertSessionHasNoErrors();
    $web = Category::latest('id')->firstOrFail();
    $result = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'blog_category'], $this->actor);
    expect($result)->toHaveKey('id');
    $native = Category::findOrFail($result['id']);
    expect($web->is_active)->toBeFalse()->and($native->is_active)->toBeFalse();
    expect($web->translations->first()->name)->toBe($native->translations->first()->name)->toBe('Nueva categoría');
    $updated = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'blog_category', 'id' => $native->id, 'translations' => ['es' => ['slug' => 'Readable Category', 'description' => 'Patched body']], 'market_ids' => [$this->market->id]], $this->actor);
    expect($updated)->toHaveKey('id');
    expect($native->fresh()->translations->first()->name)->toBe('Nueva categoría');
    expect($native->fresh()->translations->first()->slug)->toBe('readable-category');
    expect($native->fresh()->markets->modelKeys())->toBe([$this->market->id]);
});

it('rejects self parenting and ancestor cycles through web and native writes while keeping hierarchical paths', function (): void {
    $parent = Category::create(['is_active' => false]);
    $parent->translations()->create(['locale' => 'es', 'name' => 'Parent', 'slug' => 'parent']);
    $child = Category::create(['parent_id' => $parent->id, 'is_active' => false]);
    $child->translations()->create(['locale' => 'es', 'name' => 'Child', 'slug' => 'child']);
    foreach ([$parent->id, $child->id] as $invalidParent) {
        $this->put(route('admin.blog.categories.update', $parent), ['parent_id' => $invalidParent])->assertSessionHasErrors('parent_id');
        $result = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'blog_category', 'id' => $parent->id, 'attributes' => ['parent_id' => $invalidParent]], $this->actor);
        expect($result)->toHaveKey('validation');
    }
    expect($parent->fresh()->parent_id)->toBeNull();
    expect($child->fresh()->getSlugPath('es'))->toBe('parent/child');
    expect(Category::resolveBySlugPath('parent/child', 'es'))->toBeNull();
    $parent->forceFill(['is_active' => true])->saveQuietly();
    $child->forceFill(['is_active' => true])->saveQuietly();
    expect(Category::resolveBySlugPath('parent/child', 'es')?->id)->toBe($child->id);
});

it('stages published category edits and hydrates the inline editor until a human approves', function (): void {
    $category = Model::withoutEvents(fn () => Category::create(['is_active' => true]));
    $category->translations()->create(['locale' => 'es', 'name' => 'Live category', 'slug' => 'live-category']);
    $this->put(route('admin.blog.categories.update', $category), ['is_active' => '1', 'translations' => ['es' => ['name' => 'Pending category']]])->assertRedirect()->assertSessionHasNoErrors();
    expect($category->fresh()->translations->first()->name)->toBe('Live category');
    $this->get(route('admin.blog.categories.index'))->assertOk()->assertViewHas('categories', fn ($rows): bool => $rows->getCollection()->firstWhere('id', $category->id)->translations->first()->name === 'Pending category')->assertSee(route('admin.audit.review', ['type' => 'blog_categories', 'id' => $category->id]), false);
    expect($category->fresh()->translations->first()->name)->toBe('Live category');
    app(ContentAuditServiceInterface::class)->approve($category->fresh(), $this->user);
    expect($category->fresh()->translations->first()->name)->toBe('Pending category');
});

it('uses the shared delete effect without deleting children or translations of surviving categories', function (): void {
    $category = Category::create(['is_active' => false]);
    $category->translations()->create(['locale' => 'es', 'name' => 'Delete me', 'slug' => 'delete-me']);
    $child = Category::create(['parent_id' => $category->id, 'is_active' => false]);
    $child->translations()->create(['locale' => 'es', 'name' => 'Keep me', 'slug' => 'keep-me']);
    $this->delete(route('admin.blog.categories.destroy', $category))->assertRedirect();
    expect($child->fresh()->parent_id)->toBeNull();
    expect($child->fresh()->translations->count())->toBe(1);
    $deleted = app(CapabilityExecutor::class)->execute('cms_entity_delete', ['type' => 'blog_category', 'id' => $child->id], $this->actor, approved: true);
    expect($deleted['deleted'] ?? false)->toBeTrue();
    expect(Category::find($child->id))->toBeNull();
});

it('preserves identical leaf slugs under different parents but refuses collisions among siblings', function (): void {
    $children = [];
    foreach (['one', 'two'] as $rootSlug) {
        $parent = Category::create(['is_active' => false]);
        $parent->translations()->create(['locale' => 'es', 'name' => $rootSlug, 'slug' => $rootSlug]);
        $child = Category::create(['parent_id' => $parent->id, 'is_active' => false]);
        $child->translations()->create(['locale' => 'es', 'name' => 'Leaf', 'slug' => 'leaf']);
        $children[] = $child;
    }
    $updated = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'blog_category', 'id' => $children[0]->id, 'translations' => ['es' => ['description' => 'Only body changes']]], $this->actor);
    expect($updated)->toHaveKey('id');
    expect($children[0]->fresh()->getSlugPath('es'))->toBe('one/leaf');
    expect($children[1]->fresh()->getSlugPath('es'))->toBe('two/leaf');
    $this->post(route('admin.blog.categories.store'), ['parent_id' => $children[0]->parent_id, 'translations' => ['es' => ['name' => 'Collision', 'slug' => 'leaf']]])->assertSessionHasErrors('translations.es.slug');
    $root = Category::create(['is_active' => false]);
    $root->translations()->create(['locale' => 'es', 'name' => 'Root leaf', 'slug' => 'leaf']);
    $move = app(CapabilityExecutor::class)->execute('cms_entity_update', ['type' => 'blog_category', 'id' => $children[0]->id, 'attributes' => ['parent_id' => null]], $this->actor);
    expect($move)->toHaveKey('validation');
    expect($children[0]->fresh()->getSlugPath('es'))->toBe('one/leaf');
});
