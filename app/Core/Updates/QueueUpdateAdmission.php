<?php

declare(strict_types=1);

namespace App\Core\Updates;

use DomainException;

/** Acquire before reservation, so maintenance never consumes a job attempt. */
final class QueueUpdateAdmission
{
    /** @var resource|null */
    private $lease = null;

    private ?string $connection = null;

    public function beforePop(string $connection): void
    {
        $this->release();
        $lease = (new UpdateBarrier($this->directory()))->admit();
        if ($lease === null) {
            throw new DomainException('Actualización del núcleo en curso. El worker debe reiniciarse después.');
        }
        $this->lease = $lease;
        $this->connection = $connection;
    }

    public function looping(): void
    {
        // Release an idle pop before checking the gate, including failed pops.
        $this->release();
        if (is_file($this->directory().'/active.json')) {
            throw new DomainException('Actualización del núcleo en curso. El worker debe reiniciarse después.');
        }
    }

    public function attempted(string $connection): void
    {
        if ($this->connection === $connection) {
            $this->release();
        }
    }

    public function release(): void
    {
        if (is_resource($this->lease)) {
            flock($this->lease, LOCK_UN);
            fclose($this->lease);
        }
        $this->lease = null;
        $this->connection = null;
    }

    private function directory(): string
    {
        return (string) config('updates.storage_path', storage_path('app/private/core-updates'));
    }
}
