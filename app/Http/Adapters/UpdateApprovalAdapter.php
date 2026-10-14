<?php

declare(strict_types=1);

namespace App\Http\Adapters;

use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Core\Updates\UpdateRunStore;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

final class UpdateApprovalAdapter
{
    public function __construct(private readonly UpdateExecutionServiceInterface $execution, private readonly UpdateRunStore $runs) {}

    /** @return array<string, mixed> */
    public function approve(UpdateRequest $request, string $run): array
    {
        $confirmed = $request->session()->get('auth.password_confirmed_at');
        if (! is_int($confirmed) || $confirmed > time() || time() - $confirmed > 900) {
            throw new HttpResponseException(response()->json(['message' => 'Confirma tu contraseña antes de autorizar la actualización.', 'code' => 'password_confirmation_required'], 423));
        }
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $this->execution->status($run, $actorId);
        $evidence = $request->validated('backup', []);
        $evidence = is_array($evidence) ? array_filter($evidence, static fn (mixed $value): bool => $value !== null && $value !== '') : [];
        $evidence['hosting'] = ['single_host' => $request->boolean('hosting.single_host'), 'no_unmanaged_workers' => $request->boolean('hosting.no_unmanaged_workers')];
        $report = $request->file('verification_report');
        if ($report instanceof UploadedFile) {
            $directory = $this->runs->directory($run).'/evidence';
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException('No se pudo guardar el informe privado de respaldo.');
            }
            $path = $directory.'/'.bin2hex(random_bytes(16)).'.report';
            $report->move($directory, basename($path));
            chmod($path, 0600);
            $evidence['verification'] = ['artifact_path' => $path, 'sha256' => hash_file('sha256', $path)];
        }

        return $this->execution->approve($run, $actorId, (string) $request->validated('plan_hash'), $evidence);
    }
}
