<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Capabilities;

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\ExecutionContext;
use Modules\SocialPublishing\Services\PublicationInputService;
use Modules\SocialPublishing\Services\PublicationService;
use Modules\SocialPublishing\Services\SocialAuthorization;
use Modules\SocialPublishing\Services\SocialPresentation;

final readonly class SocialCapabilities
{
    public function __construct(private PublicationInputService $input, private SocialPresentation $views, private PublicationService $publications, private SocialAuthorization $auth) {}

    public function register(CapabilityRegistry $registry): void
    {
        $registry->register(new CallableCapability('cms_social_publications', 'Consultar las publicaciones y resultados de esta instalación.', [], [], 'read:social-publishing', 'read', fn (array $args): array => $this->read()), 'social-publishing', 'read');
        $registry->register(new CallableCapability('cms_social_import', 'Importar una revisión autorizada de CreativeStudio como borrador social. No aprueba ni publica.', ['creative_revision_id' => ['type' => 'string'], 'account_id' => ['type' => 'string'], 'metadata' => ['type' => 'object']], ['creative_revision_id', 'account_id'], 'manage:social-publishing', 'write', fn (array $args): array => ['publication' => $this->input->create($args, [], $this->actor())->toArray()]), 'social-publishing', 'draft', dependencies: ['creative-studio']);
        $registry->register(new CallableCapability('cms_social_schedule', 'Programar la revisión que un humano ya aprobó. El worker comprueba de nuevo la autorización.', ['publication_id' => ['type' => 'string'], 'publish_at' => ['type' => 'string']], ['publication_id', 'publish_at'], 'manage:social-publishing', 'write', fn (array $args): array => ['delivery' => $this->publications->schedule((string) $args['publication_id'], (string) $args['publish_at'], $this->actor())->toArray()]), 'social-publishing', 'draft');
        $registry->register(new CallableCapability('cms_social_cancel', 'Cancelar los pasos locales pendientes. No elimina una publicación remota.', ['delivery_id' => ['type' => 'string']], ['delivery_id'], 'manage:social-publishing', 'write', function (array $args): array {
            $this->publications->cancel((string) $args['delivery_id'], $this->actor());

            return ['cancellation_requested' => true];
        }), 'social-publishing', 'draft');
        $registry->register(new CallableCapability('cms_social_result', 'Leer el estado y las métricas con su procedencia.', ['publication_id' => ['type' => 'string']], ['publication_id'], 'read:social-publishing', 'read', fn (array $args): array => $this->result((string) $args['publication_id'])), 'social-publishing', 'read');
    }

    private function actor(): ActorContext
    {
        $actor = app(ExecutionContext::class)->actor;
        abort_unless($actor !== null, 403);

        return $actor;
    }

    /** @return array<string,mixed> */
    private function read(): array
    {
        $this->auth->authorize($this->actor(), 'read:social-publishing');
        $data = $this->views->dashboard();

        return ['accounts' => $data['accounts']->toArray(), 'publications' => $data['publications']->toArray(), 'deliveries' => $data['deliveries']->toArray()];
    }

    /** @return array<string,mixed> */
    private function result(string $id): array
    {
        $this->auth->authorize($this->actor(), 'read:social-publishing');
        $data = $this->views->preview($id);

        return ['publication' => $data['publication']->toArray(), 'deliveries' => $data['deliveries']->toArray(), 'metrics' => $data['metrics']->toArray()];
    }
}
