<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Requests;

use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AiAssistant\Services\AiActionRunner;

final class GenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(AiActionRunner::ACTIONS)],
            'prompt' => ['nullable', 'string'],
            'block_type' => ['nullable', 'string'],
            'topic' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'element_type' => ['nullable', 'string', Rule::in(app(EntitySchemaRegistryInterface::class)->types())],
            'locale' => ['nullable', 'string', 'max:10'],
            'market' => ['nullable', 'string', 'max:10'],
            'context_source' => ['nullable', 'string', 'in:prompt,editor'],
            'editor_market_ids' => ['nullable', 'array', 'max:100'],
            'editor_market_ids.*' => ['integer', 'min:1', 'distinct'],
            'style' => ['nullable', 'string'],
            'include_blocks' => ['nullable', 'boolean'],
            'proofread_action' => ['nullable', 'string', 'in:improve,shorten,expand,fix_grammar'],
            'entity_type' => ['required_if:action,translate_entity', 'nullable', 'string', Rule::in(app(EntitySchemaRegistryInterface::class)->types())],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'source_locale' => ['required_if:action,translate_entity', 'string', 'max:10'],
            'target_locale' => ['required_if:action,translate_entity', 'string', 'max:10', 'different:source_locale'],
            'fields' => ['present_if:action,translate_entity', 'array', 'max:20'],
            'fields.*' => ['string', 'max:200000'],
            'sections' => ['sometimes', 'array', 'list', 'max:300'],
            'sections.*' => ['array'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:200'],
            'text_provider' => ['nullable', 'string'],
            'text_model' => ['nullable', 'string', 'max:200'],
            'image_model' => ['nullable', 'string', 'max:200'],
            'video_model' => ['nullable', 'string', 'max:200'],
            'image_provider' => ['nullable', 'string'],
            'video_provider' => ['nullable', 'string'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,16:9,9:16,4:3,3:4'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:30'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:30'],
            'reference_image_url' => ['nullable', 'url', 'max:2048'],
            'camera_motion' => ['nullable', 'string', 'max:100'],
            'generate_image' => ['nullable', 'boolean'],
            'generate_video' => ['nullable', 'boolean'],
            'generate_media' => ['nullable', 'boolean'],
            'research_web' => ['nullable', 'boolean'],
            'persist_draft' => ['nullable', 'boolean'],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
