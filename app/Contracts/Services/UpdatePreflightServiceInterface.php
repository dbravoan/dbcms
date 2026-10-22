<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface UpdatePreflightServiceInterface
{
    /**
     * @param  array<string,mixed>  $run
     * @param  array<string,mixed>  $hosting
     * @return array{mode:string,ready:bool,issues:list<string>}
     */
    public function inspect(array $run, array $hosting = []): array;
}
