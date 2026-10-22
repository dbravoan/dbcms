<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\Request;
use Modules\SocialPublishing\Services\SocialAssets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PublicationAssetGetController extends Controller
{
    public function __invoke(Request $request, string $publication, int $index, SocialAssets $assets, ActorContextFactory $actors): BinaryFileResponse
    {
        return $assets->preview($publication, $index, $actors->fromRequest($request));
    }
}
