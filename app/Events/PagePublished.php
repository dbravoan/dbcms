<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Pages\Models\Page;

class PagePublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Page $page
    ) {}
}
