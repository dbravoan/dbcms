<?php

declare(strict_types=1);

namespace Modules\Gallery\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\DTOs\Content\EntityMutationData;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use App\Services\Content\EditorialDraftService;
use Illuminate\Support\Facades\Auth;
use Modules\Gallery\Contracts\GalleryEditorialServiceInterface;
use Modules\Gallery\Models\Gallery;

/** Entry adapter; all mutation rules and transactions belong to the common writer. */
final class GalleryEditorialService implements GalleryEditorialServiceInterface
{
    public function __construct(private readonly EntityCrudService $entities, private readonly CapabilityAuthorizer $authorizer) {}

    public function create(array $input): Gallery
    {
        $actor = $this->authorize();
        $payload = $this->entities->create('gallery', EntityMutationData::fromLegacy($input)->arguments, $actor);
        $gallery = $this->entities->resolveEntity('gallery', $payload['id']);
        assert($gallery instanceof Gallery);

        return $gallery->load(['translations', 'items', 'markets']);
    }

    public function update(Gallery $gallery, array $input): array
    {
        $this->authorize();
        $args = EntityMutationData::fromLegacy($input)->arguments;
        if ($gallery->is_active && ! empty($args['attributes']['is_active'])) {
            unset($args['attributes']['is_active']);
        }

        return $this->entities->update('gallery', $gallery->getKey(), $args);
    }

    public function delete(Gallery $gallery): void
    {
        $this->authorize();
        $this->entities->delete('gallery', $gallery->getKey());
    }

    public function editData(Gallery $gallery): array
    {
        $this->authorize();
        $gallery->load(['translations', 'items', 'markets']);

        return ['gallery' => app(EditorialDraftService::class)->forEditor($gallery), 'markets' => Market::with('languages')->where('is_active', true)->get(), 'languages' => Language::where('is_active', true)->get()];
    }

    private function authorize(): User
    {
        $actor = app(ExecutionContext::class)->actor;
        $user = $actor !== null ? $this->authorizer->user($actor) : Auth::user();
        $actor ??= new ActorContext($user instanceof User ? (int) $user->getKey() : null);
        abort_unless($user instanceof User && $this->authorizer->allowed($actor, 'write', 'manage:galleries'), 403);

        return $user;
    }
}
