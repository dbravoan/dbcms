<?php

declare(strict_types=1);

namespace App\Core\Updates;

use RuntimeException;

/** Admission is held for the complete request/CLI lifetime, including shutdown callbacks. */
final class UpdateBarrier
{
    public function __construct(private readonly string $directory) {}

    /** @return resource|null */
    public function admit()
    {
        if (is_file($this->directory.'/active.json')) {
            return null;
        }
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('No se puede crear el control privado de actualización.');
        }
        $lease = fopen($this->directory.'/admission.lock', 'c+');
        if ($lease === false || ! flock($lease, LOCK_SH | LOCK_NB)) {
            return null;
        }
        if (is_file($this->directory.'/active.json')) {
            flock($lease, LOCK_UN);
            fclose($lease);

            return null;
        }

        return $lease;
    }

    /** @param array<string, mixed> $state */
    public function close(array $state): void
    {
        FileUpdateEngine::writeJson($this->directory.'/active.json', $state);
    }

    public function drain(): bool
    {
        $lease = fopen($this->directory.'/admission.lock', 'c+');
        if ($lease === false) {
            return false;
        }
        $drained = flock($lease, LOCK_EX | LOCK_NB);
        if ($drained) {
            flock($lease, LOCK_UN);
        }
        fclose($lease);

        return $drained;
    }

    public function open(): void
    {
        if (is_file($this->directory.'/active.json') && ! unlink($this->directory.'/active.json')) {
            throw new RuntimeException('No se puede retirar el cierre de actualización.');
        }
    }
}
