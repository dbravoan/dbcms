<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/** Boots trusted extension code in a child process before publishing its directory. */
final class BlockPackageVerifier
{
    /** @param array<string,mixed> $manifest */
    public function verify(string $directory, array $manifest): void
    {
        $receipt = $directory.'/.verified-'.bin2hex(random_bytes(16));
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
\App\Core\Blocks\BlockPackageVerifier::inspect($argv[1], json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR));
PHP;
        $php = (new PhpExecutableFinder)->find(false);
        if ($php === false) {
            $this->invalid('PHP CLI is required to validate extension packages.');
        }
        $process = new Process([$php, '-d', 'memory_limit=256M', '-r', $script, base_path()], base_path());
        $process->setInput(json_encode(['directory' => $directory, 'manifest' => $manifest, 'config' => config()->all(), 'receipt' => $receipt], JSON_THROW_ON_ERROR));
        $process->setTimeout(max(1, min(30, (float) config('cms.block_packages.validation_timeout', 10))));
        $process->disableOutput();
        try {
            $process->run();
            if (! $process->isSuccessful() || ! is_file($receipt)) {
                $this->invalid('The package definitions cannot boot with the current block catalogue.');
            }
        } catch (ProcessTimedOutException) {
            $this->invalid('The package definitions exceeded the validation time limit.');
        } finally {
            if (is_file($receipt)) {
                unlink($receipt);
            }
        }
    }

    /** Internal subprocess entry point. Never call this in the administration process.
     * @param  array{directory:string,manifest:array<string,mixed>,config:array<string,mixed>,receipt:string}  $input
     */
    public static function inspect(string $basePath, array $input): void
    {
        /** @var Application $app */
        $app = require $basePath.'/bootstrap/app.php';
        $app->beforeBootstrapping(RegisterProviders::class, static function (Application $app) use ($input): void {
            $app->make('config')->set($input['config']);
        });
        $app->make(Kernel::class)->bootstrap();

        foreach ($input['manifest']['blocks'] as $class) {
            if (class_exists($class, false)) {
                throw new \DomainException('A block class already belongs to the current catalogue.');
            }
        }

        $directory = $input['directory'];
        $prefix = 'BlockPackages\\'.basename($directory).'\\';
        spl_autoload_register(static function (string $class) use ($directory, $prefix): void {
            if (str_starts_with($class, $prefix) && preg_match('/^BlockPackages\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)+$/D', $class)) {
                $file = $directory.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        }, true, true);
        $registry = $app->make(BlockRegistryInterface::class);
        foreach ($input['manifest']['blocks'] as $class) {
            $reflection = new ReflectionClass($class);
            $expectedFile = $directory.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (! $reflection->isInstantiable() || ! $reflection->implementsInterface(BlockTypeInterface::class) || $reflection->getFileName() !== realpath($expectedFile)) {
                throw new \DomainException('The declared block class must implement the block contract in its own file.');
            }
            $block = $app->make($class);
            $registry->register($block, $input['manifest']['identifier']);
            $block->getEditorSchema();
            $block->getDefaultPayload();
        }
        if (file_put_contents($input['receipt'], 'verified', LOCK_EX) === false) {
            throw new \RuntimeException('Cannot confirm package validation.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['package_zip' => $message]);
    }
}
