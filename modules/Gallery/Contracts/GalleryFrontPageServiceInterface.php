<?php

declare(strict_types=1);

namespace Modules\Gallery\Contracts;

use App\DTOs\Front\FrontPageData;
use Modules\Gallery\DTOs\GalleryFrontInput;

interface GalleryFrontPageServiceInterface
{
    public function index(GalleryFrontInput $input): FrontPageData;

    public function show(GalleryFrontInput $input): FrontPageData;
}
