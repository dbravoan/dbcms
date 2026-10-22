<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\DTOs\Front\PublicContentContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Contracts\CommentPublicationServiceInterface;
use Modules\Blog\DTOs\CommentOutcome;
use Modules\Blog\DTOs\CommentSubmission;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;

final class CommentPublicationService implements CommentPublicationServiceInterface
{
    public function __construct(private readonly LocalizationServiceInterface $localization, private readonly ModuleSettingsServiceInterface $settings, private readonly ModuleNotificationServiceInterface $notifications, private readonly ModuleManager $modules) {}

    public function submit(PublicContentContext $context, CommentSubmission $submission): CommentOutcome
    {
        if (! $this->modules->isModuleEnabled('blog')) {
            return new CommentOutcome(null);
        }

        return DB::transaction(function () use ($context, $submission): CommentOutcome {
            $post = Post::published()->forMarket($context->market)->whereHas('translations', fn ($query) => $query->where('slug', $context->slug)->where('locale', $context->locale))->with('translations')->lockForUpdate()->first();
            if ($post === null) {
                return new CommentOutcome(null);
            }
            $returnUrl = $this->localization->frontUrl('blog', $context->slug, $context->locale, $context->market);
            if (! $post->allowsComments()) {
                $message = 'Los comentarios están desactivados para este artículo.';

                return new CommentOutcome($returnUrl, ['error' => $message], ['comment_error' => $message]);
            }
            if ($submission->honeypotFilled) {
                return new CommentOutcome($returnUrl);
            }
            if ($submission->parentId !== null && ! Comment::query()->whereKey($submission->parentId)->where('post_id', $post->getKey())->where('is_approved', true)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'La respuesta debe corresponder a un comentario publicado de este artículo.']);
            }
            $approved = ! (bool) $this->settings->get('blog', 'comments_require_approval', false);
            $comment = Comment::create(['post_id' => $post->getKey(), 'parent_id' => $submission->parentId, 'author_name' => $submission->authorName, 'author_email' => $submission->authorEmail, 'content' => $submission->content, 'is_approved' => $approved]);
            DB::afterCommit(function () use ($post, $comment, $context): void {
                $postTitle = $post->translations->firstWhere('locale', $context->locale)->title ?? 'Artículo';
                $this->notifications->notify(module: 'blog', type: 'info', title: 'Nuevo comentario en el blog', message: "{$comment->author_name} comentó en \"{$postTitle}\"", actionUrl: route('admin.blog.posts.edit', $post->getKey()), data: ['comment_id' => $comment->getKey(), 'post_id' => $post->getKey(), 'author_email' => $comment->author_email, 'required_permission' => 'manage:posts']);
            });
            $message = $approved ? '¡Comentario publicado correctamente!' : 'Tu comentario ha sido enviado y se publicará tras ser revisado.';

            return new CommentOutcome($returnUrl, ['success' => $message, 'comment_success' => $message]);
        });
    }
}
