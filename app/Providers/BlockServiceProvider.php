<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Blocks\BlockEditorCatalogue;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Blocks\BlockRegistry;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

final class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockPackageManager::class);
        $this->app->singleton(BlockRegistryInterface::class, BlockRegistry::class);
        $roots = [$this->app->make(BlockPackageManager::class)->bundledRoot(), $this->app->make(BlockPackageManager::class)->uploadedRoot()];
        spl_autoload_register(static function (string $class) use ($roots): void {
            if (! str_starts_with($class, 'BlockPackages\\') || ! preg_match('/^BlockPackages\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)+$/D', $class)) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen('BlockPackages\\'))).'.php';
            foreach ($roots as $root) {
                if (is_file($root.'/'.$relative)) {
                    require_once $root.'/'.$relative;

                    return;
                }
            }
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('components.admin.block-manager', function (View $view): void {
            $view->with($this->app->make(BlockEditorCatalogue::class)->data());
        });
        $registry = $this->app->make(BlockRegistryInterface::class);
        foreach ($this->app->make(BlockPackageManager::class)->all() as $id => $package) {
            if (is_dir($package['directory'].'/Resources/views')) {
                $this->loadViewsFrom($package['directory'].'/Resources/views', $id);
            }
            foreach ($package['blocks'] as $class) {
                $block = $this->app->make($class);
                if (! $block instanceof BlockTypeInterface) {
                    throw new \DomainException('Package definitions must implement BlockTypeInterface.');
                }
                $registry->register($block, $id);
            }
        }
    }
}
