<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\Modules\ModulePackageDTO;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class ModulePackageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission('access:admin') && $user->hasPermission('manage:modules');
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['module_zip' => 'required|file|mimes:zip|max:51200'];
    }

    public function package(): UploadedFile
    {
        $file = $this->file('module_zip');
        if (! $file instanceof UploadedFile) {
            throw new \LogicException('The module package was not validated.');
        }

        return $file;
    }

    public function toDto(): ModulePackageDTO
    {
        $file = $this->package();

        return new ModulePackageDTO($file->getPathname(), (int) $file->getSize());
    }
}
