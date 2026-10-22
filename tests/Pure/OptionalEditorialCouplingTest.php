<?php

declare(strict_types=1);

test('pages module has zero hardcoded knowledge of ai_assistant and content_audit', function (): void {
    $pagesDir = dirname(__DIR__, 2).'/app/Content/Pages';

    // Models should not import ContentAudit traits
    $pageModel = file_get_contents($pagesDir.'/Models/Page.php');
    expect($pageModel)->not->toContain('HasContentAudit')
        ->and($pageModel)->not->toContain('Modules\\ContentAudit');

    // Controllers should not inject or import ContentAudit
    $postController = file_get_contents($pagesDir.'/Http/Controllers/Admin/PagePostController.php');
    expect($postController)->not->toContain('ContentAudit')
        ->and($postController)->not->toContain('contentAuditService');

    $putController = file_get_contents($pagesDir.'/Http/Controllers/Admin/PagePutController.php');
    expect($putController)->not->toContain('ContentAudit')
        ->and($putController)->not->toContain('contentAuditService');
});

test('blog module has zero hardcoded knowledge of ai_assistant and content_audit', function (): void {
    $blogDir = dirname(__DIR__, 2).'/modules/Blog';
    if (! is_dir($blogDir)) {
        $this->markTestSkipped('Outside this artifact: Blog sources are absent.');
    }

    // Models should not import ContentAudit traits
    $postModel = file_get_contents($blogDir.'/Models/Post.php');
    expect($postModel)->not->toContain('HasContentAudit')
        ->and($postModel)->not->toContain('Modules\\ContentAudit');

    // Controllers should not inject or import ContentAudit
    $postController = file_get_contents($blogDir.'/Http/Controllers/Admin/PostPostController.php');
    expect($postController)->not->toContain('ContentAudit')
        ->and($postController)->not->toContain('contentAuditService');

    $putController = file_get_contents($blogDir.'/Http/Controllers/Admin/PostPutController.php');
    expect($putController)->not->toContain('ContentAudit')
        ->and($putController)->not->toContain('contentAuditService');
});

test('shop module has zero hardcoded knowledge of content_audit', function (): void {
    $shopDir = dirname(__DIR__, 2).'/modules/Shop';
    if (! is_dir($shopDir)) {
        $this->markTestSkipped('Outside this artifact: Shop sources are absent.');
    }

    $productModel = file_get_contents($shopDir.'/Models/Product.php');
    expect($productModel)->not->toContain('HasContentAudit')
        ->and($productModel)->not->toContain('Modules\\ContentAudit');
});
