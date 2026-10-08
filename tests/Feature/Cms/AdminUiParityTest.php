<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Modules\Blog\Models\Post;

test('a shared primary button keeps its base, variant and caller classes in rendered html', function (): void {
    $html = Blade::render('<x-admin.button variant="primary" class="test-action" @click="generate()" x-bind:disabled="busy">Generate</x-admin.button>');
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $button = $dom->getElementsByTagName('button')->item(0);
    expect($button)->not->toBeNull();
    expect(explode(' ', $button->getAttribute('class')))->toContain('admin-btn', 'admin-btn--primary', 'test-action');
    expect($button->getAttribute('x-bind:disabled'))->toBe('busy');
    expect($button->getAttribute('type'))->toBe('button');
});

test('shared select and input keep explicit identity, bindings and accessible validation', function (string $component): void {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['destination' => ['Choose a destination.']]));
    view()->share('errors', $errors);
    $html = Blade::render('<x-admin.'.$component.' name="destination" id="translation-target" label="Destination" x-model="targetLocale" x-bind:disabled="busy" />', ['errors' => $errors]);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $control = $dom->getElementsByTagName($component)->item(0);
    expect($control->getAttribute('id'))->toBe('translation-target');
    expect($control->getAttribute('name'))->toBe('destination');
    expect($control->getAttribute('x-model'))->toBe('targetLocale');
    expect($control->getAttribute('aria-invalid'))->toBe('true');
    expect($control->getAttribute('aria-describedby'))->toBe('translation-target-description');
    expect($dom->getElementsByTagName('label')->item(0)->getAttribute('for'))->toBe('translation-target');
    expect($html)->toContain('Choose a destination.', 'admin-control--invalid');
})->with(['select', 'input']);

test('both backoffice themes render shared controls and editor translation without changing the public shell', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $this->seed(PlatformShowcaseSeeder::class);
    $user = User::whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->firstOrFail();
    $post = Post::firstOrFail();
    $this->actingAs($user);
    $screens = [
        'dashboard' => route('admin.dashboard'),
        'posts' => route('admin.blog.posts.index'),
        'editor' => route('admin.blog.posts.edit', $post),
        'products' => route('admin.shop.products.index'),
        'forms' => route('admin.forms.index'),
        'ai' => route('admin.ai_assistant.workbench'),
    ];
    foreach ($screens as $name => $url) {
        $response = $this->get($url)->assertOk()->assertSee('theme-'.$theme, false)->assertSee('data-admin-shell', false);
        if ($name === 'editor') {
            $response->assertSee('cms-ai-translation-source', false)->assertSee('admin-control', false)->assertSee('admin-btn--primary', false);
        }
        // Optional, private snapshots contain only the isolated seeded QA data.
        if (getenv('CMS_UI_EVIDENCE') === '1') {
            $directory = storage_path('app/runtime-verification/admin-ui-20261011');
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($directory.'/'.$theme.'-'.$name.'.html', $response->getContent());
        }
    }
    $this->get('/es/es')->assertOk()->assertDontSee('data-admin-shell', false);
})->with(['termocms', 'dbcms']);

test('login uses the selected theme and accessible shared fields', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $this->get(route('admin.login'))->assertOk()->assertSee('theme-'.$theme, false)->assertSee('data-admin-shell', false)
        ->assertSee('admin-control', false)->assertSee('autocomplete="username"', false)->assertSee('autocomplete="current-password"', false);
})->with(['termocms', 'dbcms']);
