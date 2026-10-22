<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Connectors;

use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;

final class ManualConnector extends AbstractConnector
{
    public function capabilities(): array
    {
        return ['formats' => ['text', 'image', 'carousel', 'short_video'], 'export' => true, 'direct_publish' => false, 'reason' => 'TikTok: publicación manual; uso interno sin Direct Post.'];
    }

    public function validate(CreativePackage $package, array $metadata): void
    {
        $this->media($package, ['image/jpeg', 'image/png', 'video/mp4'], 35);
    }

    public function step(string $step, SocialAccount $account, CreativePackage $package, array $receipt, array $metadata): OperationResult
    {
        return new OperationResult('blocked', error: 'manual_export_only');
    }
}
