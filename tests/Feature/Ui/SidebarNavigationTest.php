<?php

declare(strict_types=1);

use App\Contracts\Services\AdminNavigationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\DTOs\Admin\AdminNavigationItem;
use App\DTOs\Admin\AdminNavigationSection;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Tests\Support\AdminCatalogComposition;
use Tests\Support\CmsFixtures;

uses(AdminCatalogComposition::class);

/** @param array<int, AdminNavigationSection> $sections */
function sidebarNavigationDom(array $sections): DOMXPath
{
    $html = Blade::render('<x-admin.sidebar-navigation :sections="$sections" />', compact('sections'));
    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    return new DOMXPath($dom);
}

test('sidebar renders accessible independent groups and preserves child actions in both brands', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    $current = new AdminNavigationItem(
        id: 'current-posts', label: 'Posts', route: 'admin.blog.posts.index', icon: 'fa-solid fa-pen',
        createRoute: 'admin.blog.posts.create', createTooltip: 'Create post', badgeText: '12', active: true
    );
    $settings = new AdminNavigationItem(
        id: 'settings-link', label: 'Settings', route: 'admin.modules.settings', icon: 'fa-solid fa-wrench',
        routeParameters: ['identifier' => 'shop']
    );
    $sections = [new AdminNavigationSection('Modules', [
        new AdminNavigationItem(id: 'active-group', label: 'Blog', route: null, icon: 'fa-solid fa-pen', active: true, children: [$current, $settings]),
        new AdminNavigationItem(id: 'inactive-group', label: 'Shop', route: null, icon: 'fa-solid fa-bag-shopping', children: [
            new AdminNavigationItem(id: 'shop-products', label: 'Products', route: 'admin.shop.products.index', icon: 'fa-solid fa-box'),
        ]),
    ])];
    $xpath = sidebarNavigationDom($sections);

    $active = $xpath->query('//button[@id="active-group"]')->item(0);
    $inactive = $xpath->query('//button[@id="inactive-group"]')->item(0);
    expect($active)->toBeInstanceOf(DOMElement::class);
    expect($inactive)->toBeInstanceOf(DOMElement::class);
    expect($active->getAttribute('type'))->toBe('button');
    expect($active->hasAttribute('href'))->toBeFalse();
    expect($active->getAttribute('aria-expanded'))->toBe('true');
    expect($inactive->getAttribute('aria-expanded'))->toBe('false');
    expect($active->getAttribute(':aria-expanded'))->toBe('expanded.toString()');
    expect($active->getAttribute('x-on:click'))->toBe('expanded = !expanded');
    expect($active->parentNode->getAttribute('x-data'))->toContain('true');
    expect($inactive->parentNode->getAttribute('x-data'))->toContain('false');
    expect($active->parentNode->isSameNode($inactive->parentNode))->toBeFalse();
    foreach ([$active, $inactive] as $button) {
        $panel = $xpath->query('//*[@id="'.$button->getAttribute('aria-controls').'"]')->item(0);
        expect($panel)->toBeInstanceOf(DOMElement::class);
        expect($panel->getAttribute('x-show'))->toBe('expanded');
        expect($panel->parentNode->isSameNode($button->parentNode))->toBeTrue();
        expect(str_contains($panel->getAttribute('style'), 'display: none'))->toBe($button === $inactive);
    }
    expect($xpath->query('//a[@id="current-posts"]')->item(0)->getAttribute('aria-current'))->toBe('page');
    expect($xpath->query('//a[@id="settings-link"]')->item(0)->getAttribute('href'))->toBe(route('admin.modules.settings', ['identifier' => 'shop']));
    expect($xpath->query('//a[@aria-label="Create post"]')->item(0)->getAttribute('href'))->toBe(route('admin.blog.posts.create'));
    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " admin-sidebar-badge ")]')->item(0)->textContent)->toContain('12');
})->with(['dbcms', 'termocms']);

test('real module providers contribute one module group with their destinations and shop mode', function (string $mode, array $shopRoutes): void {
    CmsFixtures::identities();
    app(ModuleSettingsServiceInterface::class)->set('shop', 'shop_mode', $mode);
    $user = User::query()->where('email', 'admin@db-cms.io')->firstOrFail();
    $sections = app(AdminNavigationServiceInterface::class)->getNavigationSections($user);
    $items = collect($sections)->flatMap(fn (AdminNavigationSection $section): array => $section->items)->keyBy('module');
    $routesByModule = [
        'shop' => $shopRoutes,
        'blog' => ['admin.blog.posts.index', 'admin.blog.categories.index'],
        'forms' => ['admin.forms.index', 'admin.forms.submissions'],
    ];
    $xpath = sidebarNavigationDom($sections);
    foreach ($routesByModule as $module => $routes) {
        $group = $items->get($module);
        expect($group)->toBeInstanceOf(AdminNavigationItem::class);
        expect($group->route)->toBeNull();
        expect(array_map(fn (AdminNavigationItem $child): ?string => $child->route, $group->children))->toBe($routes);
        expect($xpath->query('//button[@id="'.$group->id.'"]')->length)->toBe(1);
        expect($xpath->query('//a[@id="'.$group->id.'"]')->length)->toBe(0);
        foreach ($group->children as $child) {
            expect($xpath->query('//a[@id="'.$child->id.'"]')->item(0)->getAttribute('href'))->toBe(route($child->route, $child->routeParameters));
        }
    }
    expect($xpath->query('//a[@href="'.route('admin.shop.orders.index').'"]')->length)->toBe($mode === 'catalog_only' ? 0 : 1);
})->with([
    'catalog' => ['catalog_only', ['admin.shop.products.index', 'admin.shop.categories.index', 'admin.shop.inquiries.index']],
    'full shop' => ['full_shop', ['admin.shop.products.index', 'admin.shop.categories.index', 'admin.shop.inquiries.index', 'admin.shop.orders.index']],
]);

test('real provider navigation hides ungranted module groups', function (): void {
    CmsFixtures::identities(['blog-editor' => ['manage:posts']]);
    $user = User::query()->where('email', 'blog-editor@db-cms.io')->firstOrFail();
    $sections = app(AdminNavigationServiceInterface::class)->getNavigationSections($user);
    $items = collect($sections)->flatMap(fn (AdminNavigationSection $section): array => $section->items);
    expect($items->where('module', 'blog')->count())->toBe(1);
    expect($items->whereIn('module', ['shop', 'forms', 'gallery']))->toBeEmpty();
    $xpath = sidebarNavigationDom($sections);
    expect($xpath->query('//button[@id="nav-blog-blog"]')->length)->toBe(1);
    expect($xpath->query('//button[@id="nav-shop-shop" or @id="nav-forms-forms"]')->length)->toBe(0);
});
