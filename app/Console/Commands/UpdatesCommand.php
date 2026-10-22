<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Contracts\Services\UpdatePreparationServiceInterface;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Hash;

final class UpdatesCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'cms:updates {operation : check|prepare|adopt|status|approve|advance|cancel|proposal} {target? : Version or execution UUID} {--actor= : Human account ID} {--distribution=source} {--plan-hash=} {--output= : Destination for managed review ZIP} {--backup-evidence= : Path to private JSON backup evidence} {--force : Force remote discovery}';

    protected $description = 'Check, review and explicitly approve verified core updates';

    public function handle(UpdateCatalogServiceInterface $catalog, UpdatePreparationServiceInterface $preparation, UpdateExecutionServiceInterface $execution): int
    {
        try {
            $operation = (string) $this->argument('operation');
            if ($operation === 'check') {
                $this->line(json_encode($catalog->check((bool) $this->option('force')), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            $actor = User::find((int) $this->option('actor'));
            if (! $actor instanceof User || (bool) $actor->getAttribute('is_service_account') || ! $actor->hasPermission('access:admin') || ! $actor->hasPermission('manage:updates')) {
                throw new \DomainException('Select a human account with manage:updates using --actor.');
            }
            $target = (string) $this->argument('target');
            if ($target === '') {
                throw new \DomainException('Specify a version or execution UUID.');
            }
            if ($operation === 'approve' && (! $this->input->isInteractive() || ! $this->confirm('Apply this exact reviewed update with its verified backup?', false))) {
                throw new \DomainException('Explicit interactive human approval is required.');
            }
            if ($operation === 'approve') {
                $password = $this->secret('Confirm the CMS administrator password');
                if (! is_string($password) || ! Hash::check($password, $actor->getAuthPassword())) {
                    throw new \DomainException('Administrator password verification failed.');
                }
                request()->attributes->set('_cms_update_cli_approval', true);
            }
            $result = match ($operation) {
                'prepare' => $preparation->prepare($target, $actor->id),
                'adopt' => $preparation->adopt($target, (string) $this->option('distribution'), $actor->id),
                'status' => $execution->status($target, $actor->id),
                'advance' => $execution->advance($target, $actor->id),
                'cancel' => $execution->cancel($target, $actor->id),
                'approve' => $execution->approve($target, $actor->id, (string) $this->option('plan-hash'), $this->evidence()),
                'proposal' => ['proposal' => $this->export($preparation->proposal($target, $actor->id))],
                default => throw new \DomainException('Unknown update operation.'),
            };
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            request()->attributes->remove('_cms_update_cli_approval');
        }
    }

    /** @return array<string, mixed> */
    private function evidence(): array
    {
        $path = $this->option('backup-evidence');
        if (! is_string($path) || $path === '') {
            return [];
        }
        $bytes = is_file($path) ? file_get_contents($path) : false;
        $data = $bytes === false ? null : json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \DomainException('Backup evidence must be a private JSON object.');
        }

        return $data;
    }

    private function export(string $source): string
    {
        $destination = $this->option('output');
        if (! is_string($destination) || $destination === '') {
            return $source;
        }
        if (file_exists($destination) || ! copy($source, $destination)) {
            throw new \DomainException('Review output already exists or cannot be written.');
        }
        chmod($destination, 0600);

        return $destination;
    }
}
