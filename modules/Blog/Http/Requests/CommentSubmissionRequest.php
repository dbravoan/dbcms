<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Requests;

use App\Core\Forms\FormSchemas;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Blog\DTOs\CommentSubmission;

final class CommentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        if ($this->filled('_hp_security_check') || $this->filled('website_url')) {
            return [];
        }
        $schemas = app(FormSchemas::class);

        return $schemas->compileRules($schemas->blogComment()) + ['parent_id' => 'nullable|integer|min:1'];
    }

    public function submission(): CommentSubmission
    {
        $values = $this->validated();

        return new CommentSubmission((string) ($values['author_name'] ?? ''), (string) ($values['author_email'] ?? ''), (string) ($values['content'] ?? ''), isset($values['parent_id']) ? (int) $values['parent_id'] : null, $this->filled('_hp_security_check') || $this->filled('website_url'));
    }
}
