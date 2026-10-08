<?php

declare(strict_types=1);

namespace Modules\Pages\Http\Controllers;

use App\Http\Adapters\FrontPageResponseMapper;
use App\Http\Adapters\PublicContentContextFactory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Pages\Contracts\PagePublicReaderInterface;

final class FrontPageController extends Controller
{
    public function __construct(private readonly PagePublicReaderInterface $reader, private readonly PublicContentContextFactory $contexts, private readonly FrontPageResponseMapper $responses) {}

    public function show(Request $request): View
    {
        return $this->responses->view($this->reader->show($this->contexts->fromRequest($request)));
    }
}
