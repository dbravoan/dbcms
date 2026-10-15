<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Services;

use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Contracts\Services\PublishedSectionTextInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;

final class CreativeContextService
{
    public function __construct(private readonly PublicContentDiscoveryInterface $publicContent, private readonly PublishedSectionTextInterface $sections, private readonly EntitySchemaRegistryInterface $entities, private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function snapshot(array $input, ActorContext $actor): array
    {
        $editorialBrand = app()->bound(AiBrandProfileServiceInterface::class) ? app(AiBrandProfileServiceInterface::class)->getProfile() : [];
        $snapshot = ['brief' => (string) $input['brief'], 'locale' => (string) $input['locale'], 'editorial_brand' => $editorialBrand];
        if (($input['mode'] ?? 'free') === 'free') {
            return $snapshot;
        }
        $type = (string) ($input['source_type'] ?? '');
        $schema = $this->entities->get($type);
        if (! in_array($type, ['post', 'page', 'product'], true) || $schema === null || ! $this->authorizer->allowed($actor, 'read', $schema->permission)) {
            throw ValidationException::withMessages(['source_type' => 'Selecciona un artículo, página o producto público autorizado.']);
        }
        $market = Market::whereKey((int) ((array) $input['market_ids'])[0])->firstOrFail();
        $entity = $this->publicContent->query($type, $market, (string) $input['locale'])->whereKey((int) ($input['source_id'] ?? 0))->first();
        if (! $entity instanceof Model) {
            throw ValidationException::withMessages(['source_id' => 'El contenido debe estar publicado en el idioma y mercado seleccionados.']);
        }
        $translations = $entity->getRelation('translations');
        $translation = $translations->filter(fn (Model $row): bool => $row->getAttribute('locale') === $input['locale'] && ($row->getAttribute('market_id') === null || (int) $row->getAttribute('market_id') === $market->id))->sortByDesc(static fn (Model $row): int => $row->getAttribute('market_id') === null ? 0 : 1)->first();
        if (! $translation instanceof Model) {
            throw ValidationException::withMessages(['locale' => 'No existe una traducción pública para este contexto.']);
        }
        $slug = (string) $translation->getAttribute('slug');
        $body = (string) ($translation->getAttribute('content') ?? $translation->getAttribute('body') ?? $translation->getAttribute('description') ?? '');
        $snapshot += ['type' => $type, 'id' => (int) $entity->getKey(), 'title' => (string) $translation->getAttribute('title'), 'summary' => (string) ($translation->getAttribute('summary') ?? ''), 'body' => mb_substr(strip_tags($body."\n".$this->sections->text($entity, $market, (string) $input['locale'], false)), 0, 24000), 'destination_url' => $this->publicContent->url($type, $market, (string) $input['locale'], $slug), 'source_updated_at' => (string) $entity->getAttribute('updated_at'), 'market' => $market->code];

        return $snapshot;
    }

    /** @return array<string,string> */
    public function choices(string $locale, int $marketId, ActorContext $actor): array
    {
        $market = Market::whereKey($marketId)->where('is_active', true)->firstOrFail();
        $choices = [];
        foreach (['page', 'post', 'product'] as $type) {
            $schema = $this->entities->get($type);
            if ($schema === null || ! $this->authorizer->allowed($actor, 'read', $schema->permission)) {
                continue;
            }
            foreach ($this->publicContent->query($type, $market, $locale)->limit(100)->get() as $entity) {
                $translation = $entity->getRelation('translations')->filter(fn (Model $row): bool => $row->getAttribute('locale') === $locale && ($row->getAttribute('market_id') === null || (int) $row->getAttribute('market_id') === $marketId))->sortByDesc(static fn (Model $row): int => $row->getAttribute('market_id') === null ? 0 : 1)->first();
                if ($translation !== null) {
                    $choices[$type.':'.$entity->getKey()] = $schema->label.' · '.(string) $translation->getAttribute('title');
                }
            }
        }

        return $choices;
    }

    public function stillPublic(string $type, int $id, string $locale, string $marketCode): bool
    {
        $market = Market::query()->where('code', $marketCode)->where('is_active', true)->first();

        return $market !== null && $this->publicContent->query($type, $market, $locale)->whereKey($id)->exists();
    }
}
