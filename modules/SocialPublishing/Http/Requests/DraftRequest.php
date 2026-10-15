<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['account_id' => 'required|uuid', 'creative_revision_id' => 'nullable|string|max:100', 'publication_id' => 'nullable|uuid', 'expected_revision' => 'required_with:publication_id|uuid', 'campaign_id' => 'nullable|string|max:100', 'locale' => 'required_without:creative_revision_id|string|max:10', 'market' => 'required_without:creative_revision_id|string|max:10', 'text' => 'required_without:creative_revision_id|string|max:63000', 'destination_url' => 'nullable|url|max:2000', 'remove_assets' => 'nullable|boolean', 'files' => 'nullable|array|max:20', 'files.*' => 'file|max:524288|mimes:jpg,jpeg,png,mp4', 'metadata' => 'nullable|array:title,description,made_for_kids,synthetic_media,category_id', 'metadata.title' => 'nullable|string|max:150', 'metadata.description' => 'nullable|string|max:500', 'metadata.made_for_kids' => 'nullable|boolean', 'metadata.synthetic_media' => 'nullable|boolean', 'metadata.category_id' => 'nullable|integer|min:1'];
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        $data = $this->validated();
        unset($data['files']);
        foreach (['made_for_kids', 'synthetic_media'] as $key) {
            if (isset($data['metadata'][$key])) {
                $data['metadata'][$key] = filter_var($data['metadata'][$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $data;
    }
}
