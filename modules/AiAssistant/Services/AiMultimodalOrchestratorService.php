<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Services\Media\MediaManagerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiImageProviderInterface;
use Modules\AiAssistant\Contracts\AiMultimodalOrchestratorInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\Contracts\AiVideoProviderInterface;
use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationResult;
use Modules\AiAssistant\DTOs\VideoGenerationRequest;
use Modules\AiAssistant\Exceptions\AiProviderException;
use Modules\AiAssistant\Exceptions\AiProviderPendingException;

final class AiMultimodalOrchestratorService implements AiMultimodalOrchestratorInterface
{
    public function __construct(
        private readonly AiProviderRegistryInterface $registry,
        private readonly AiProviderSettings $settings,
        private readonly AiConfigBridgeInterface $configBridge,
        private readonly MediaManagerService $mediaManager,
        private readonly AiResearchService $researchService,
        private readonly BlockIntelligenceService $blockIntelligence,
        private readonly AiDraftPersistenceService $drafts,
    ) {}

    public function generateImage(
        string $prompt = '',
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $content = null,
        ?string $title = null,
        array $options = []
    ): array {
        $providerId = (string) ($options['provider'] ?? $this->settings->activeProvider('image'));

        if (! $this->registry->has($providerId)) {
            throw new AiProviderException('Unknown image provider.');
        }

        $provider = $this->registry->resolve($providerId);
        if (! $provider instanceof AiImageProviderInterface) {
            throw new AiProviderException("El proveedor [{$providerId}] no soporta la generación de imágenes.");
        }

        $apiKey = (string) $this->settings->apiKey($providerId);
        $baseUrl = $this->settings->baseUrl($providerId);
        $model = (string) ($options['model'] ?? $this->settings->modelForModality($providerId, 'image') ?? $this->defaultMediaModel($providerId, 'image'));

        // Resolve artistic prompt: if empty, extract essence from content
        $effectivePrompt = trim($prompt);
        if ($effectivePrompt === '') {
            $contextText = trim(($title ? "Título: {$title}\n" : '').($content ?: ''));
            $effectivePrompt = $this->synthesizeVisualPrompt($contextText, $title ?: 'Entidad CMS');
        }

        $aspectRatio = (string) ($options['aspect_ratio'] ?? '16:9');
        $style = (string) ($options['style'] ?? 'photorealistic');
        $referenceUrl = isset($options['reference_image_url']) ? (string) $options['reference_image_url'] : null;

        $request = new ImageGenerationRequest(
            prompt: $effectivePrompt,
            aspectRatio: $aspectRatio,
            style: $style,
            referenceImageUrl: $referenceUrl,
            extraParameters: $options
        );

        $result = $provider->generateImage($request, $apiKey, $model, $baseUrl);

        // Download and persist in media library
        $mediaTitle = $title ? "Imagen para: {$title}" : Str::limit($effectivePrompt, 40);
        $media = $result->binaryData !== null
            ? $this->mediaManager->saveFromBinary($result->binaryData, match ($result->mimeType) {
                'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', default => throw new AiProviderException('Unsupported generated image MIME type.')
            }, $mediaTitle, $effectivePrompt)
            : $this->mediaManager->importFromUrl($result->url, $mediaTitle, $effectivePrompt);

        if ($entityType !== null && $entityId !== null) {
            $this->drafts->attachFeaturedImage($entityType, $entityId, $media['url'], isset($options['actor_id']) ? (int) $options['actor_id'] : null);
        }

        return [
            'url' => $media['url'],
            'media_file_id' => $media['id'],
            'prompt' => $effectivePrompt,
            'provider' => $providerId,
            'model' => $result->model,
        ];
    }

    public function generateVideo(
        string $prompt = '',
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $content = null,
        ?string $title = null,
        array $options = []
    ): array {
        $providerId = (string) ($options['provider'] ?? $this->settings->activeProvider('video'));

        if (! $this->registry->has($providerId)) {
            throw new AiProviderException('Unknown video provider.');
        }

        $provider = $this->registry->resolve($providerId);
        if (! $provider instanceof AiVideoProviderInterface) {
            throw new AiProviderException("El proveedor [{$providerId}] no soporta la generación de vídeo.");
        }

        $apiKey = (string) $this->settings->apiKey($providerId);
        $baseUrl = $this->settings->baseUrl($providerId);
        $model = (string) ($options['model'] ?? $this->settings->modelForModality($providerId, 'video') ?? $this->defaultMediaModel($providerId, 'video'));

        $effectivePrompt = trim($prompt);
        if ($effectivePrompt === '') {
            $contextText = trim(($title ? "Título: {$title}\n" : '').($content ?: ''));
            $effectivePrompt = $this->synthesizeVideoScript($contextText, $title ?: 'Vídeo Teaser');
        }

        $referenceUrl = isset($options['reference_image_url']) ? (string) $options['reference_image_url'] : null;

        $cameraMotion = (string) ($options['camera_motion'] ?? 'cinematic_push');
        $duration = (int) ($options['duration_seconds'] ?? $options['duration'] ?? ($providerId === 'magnific' ? 6 : 5));
        if (str_ends_with($model, '/text-to-video') || $model === 'ltx-2-pro') {
            $referenceUrl = null;
        }

        $request = new VideoGenerationRequest(
            prompt: $effectivePrompt,
            referenceImageUrl: $referenceUrl,
            durationSeconds: $duration,
            cameraMotion: $cameraMotion,
            aspectRatio: (string) ($options['aspect_ratio'] ?? '16:9'),
            extraParameters: $options
        );

        $result = $provider->generateVideo($request, $apiKey, $model, $baseUrl);

        $mediaTitle = $title ? "Vídeo Teaser: {$title}" : Str::limit($effectivePrompt, 40);
        $media = $this->mediaManager->importFromUrl($result->url, $mediaTitle, $effectivePrompt);

        return [
            'url' => $media['url'],
            'media_file_id' => $media['id'],
            'poster_url' => $referenceUrl,
            'duration_seconds' => $duration,
            'provider' => $providerId,
            'model' => $result->model,
        ];
    }

    public function orchestratePost(MultimodalOrchestrationRequest $request): MultimodalOrchestrationResult
    {
        $checkpoint = $this->checkpoint($request, 'post');
        if (isset($checkpoint['result'])) {
            return new MultimodalOrchestrationResult(...$checkpoint['result']);
        }
        $sources = [];
        $researchContext = '';

        if ($request->researchWeb && $checkpoint === null) {
            $research = $this->researchService->research($request->topic, $request->locale);
            $sources = $research['sources'];
            $facts = implode("\n- ", $research['key_facts']);
            $researchContext = $sources === [] ? '\nLa investigación no produjo fuentes verificables. No inventes datos ni citas.' : "\nExtractos de fuentes externas sin verificación editorial independiente:\n- {$facts}\nNo añadas cifras o afirmaciones que no aparezcan en los extractos.\n";
        }

        // 1. Synthesize Editorial Blueprint via LLM
        $sys = 'Eres un redactor y director editorial de élite en dbCMS. Diseña un post estructurado, autoritativo y libre de clichés.';
        $usr = "Tema: {$request->topic}\nEstilo: {$request->style}\nIdioma: {$request->locale}\n{$researchContext}\nGenera JSON: {title, slug, summary, content_markdown, meta_title, meta_description, keywords: []}";

        if ($checkpoint !== null) {
            $data = $checkpoint['data'];
        } else {
            $completion = $this->configBridge->generateCompletion($sys, $usr, $request->textProvider, ['json' => true]);
            $data = json_decode($completion['content'], true);
            if (! is_array($data)) {
                throw new AiProviderException('Editorial generation returned invalid JSON.');
            }
        }

        $title = (string) ($data['title'] ?? Str::headline($request->topic));
        $slug = (string) ($data['slug'] ?? Str::slug($title));
        $summary = (string) ($data['summary'] ?? Str::limit($request->topic, 160));
        $content = (string) ($data['content_markdown'] ?? $data['content'] ?? $request->topic);
        $metaTitle = (string) ($data['meta_title'] ?? $title);
        $metaDescription = (string) ($data['meta_description'] ?? $summary);
        $keywords = array_values(array_map('strval', (array) ($data['keywords'] ?? ['cms', 'articulo'])));

        // 2. Multimodal Generation (Images & Video)
        $featuredImageUrl = $checkpoint['featured_image_url'] ?? null;
        $videoUrl = $checkpoint['video_url'] ?? null;
        $mediaIds = $checkpoint['media_ids'] ?? [];
        $sources = $checkpoint['sources'] ?? ($sources ?? []);

        if ($request->generateImage && $featuredImageUrl === null) {
            try {
                $img = $this->generateImage(
                    prompt: '',
                    content: $content,
                    title: $title,
                    options: $this->mediaOptions($request, 'image', $request->imageProvider, $checkpoint)
                );
                $featuredImageUrl = $img['url'];
                if ($img['media_file_id']) {
                    $mediaIds[] = $img['media_file_id'];
                }
            } catch (AiProviderPendingException $error) {
                $this->saveCheckpoint($request, 'post', $error->requestId, ['stage' => 'image', 'provider' => $error->provider, 'model' => $this->mediaOptions($request, 'image', $request->imageProvider, $checkpoint)['model'], 'data' => $data, 'sources' => $sources, 'featured_image_url' => $featuredImageUrl, 'video_url' => $videoUrl, 'media_ids' => $mediaIds]);
                throw $error;
            }
        }

        if ($request->generateVideo && $videoUrl === null) {
            try {
                $vid = $this->generateVideo(
                    prompt: '',
                    content: $summary,
                    title: $title,
                    options: $this->mediaOptions($request, 'video', $request->videoProvider, $checkpoint) + ['reference_image_url' => $featuredImageUrl]
                );
                $videoUrl = $vid['url'];
                if ($vid['media_file_id']) {
                    $mediaIds[] = $vid['media_file_id'];
                }
            } catch (AiProviderPendingException $error) {
                $this->saveCheckpoint($request, 'post', $error->requestId, ['stage' => 'video', 'provider' => $error->provider, 'model' => $this->mediaOptions($request, 'video', $request->videoProvider, $checkpoint)['model'], 'data' => $data, 'sources' => $sources, 'featured_image_url' => $featuredImageUrl, 'video_url' => $videoUrl, 'media_ids' => $mediaIds]);
                throw $error;
            }
        }

        // 3. Assemble CMS Block Sections
        $sections = [];
        $heroData = [
            'title' => $title,
            'content' => $summary,
            'cta_text' => 'Seguir leyendo',
            'cta_url' => '#contenido',
            'image_url' => $featuredImageUrl ?? '',
            'video_url' => $videoUrl ?? '',
            'badge' => 'Destacado',
        ];
        $sections[] = ['type' => 'hero', 'data' => $this->blockIntelligence->sanitizeBlockPayload('hero', $heroData)];

        if ($videoUrl !== null) {
            $sections[] = [
                'type' => 'video',
                'data' => $this->blockIntelligence->sanitizeBlockPayload('video', [
                    'video_url' => $videoUrl,
                    'poster_url' => $featuredImageUrl ?? '',
                    'title' => 'Vídeo Teaser Oficial',
                    'caption' => "Resumen audiovisual de {$title}.",
                ]),
            ];
        }

        $sections[] = [
            'type' => 'text',
            'data' => $this->blockIntelligence->sanitizeBlockPayload('text', [
                'title' => 'Análisis en Profundidad',
                'content' => $content,
                'format' => 'markdown',
            ]),
        ];

        $sections[] = [
            'type' => 'cta',
            'data' => $this->blockIntelligence->sanitizeBlockPayload('cta', [
                'title' => '¿Te ha resultado útil?',
                'content' => 'Suscríbete a nuestra newsletter para recibir las últimas publicaciones.',
                'cta_text' => 'Suscribirme',
                'cta_url' => '/contacto',
            ]),
        ];

        // 4. Persistence in Database if requested
        $entityId = null;
        if ($request->persistDraft) {
            $entityId = $this->drafts->create('post', $request, [
                'title' => $title, 'slug' => $slug, 'summary' => $summary, 'content' => $content,
                'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'featured_image' => $featuredImageUrl,
            ], $sections);
        }

        $result = new MultimodalOrchestrationResult(
            entityType: 'post',
            entityId: $entityId,
            title: $title,
            slug: $slug,
            summary: $summary,
            content: $content,
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            keywords: $keywords,
            featuredImageUrl: $featuredImageUrl,
            videoUrl: $videoUrl,
            mediaFileIds: $mediaIds,
            sections: $sections,
            antiAiAudit: ['status' => 'not_assessed'],
            blueprint: ['sources_count' => count($sources)],
            sources: $sources,
        );
        if ($checkpoint !== null) {
            foreach ($checkpoint['_checkpoint_ids'] as $checkpointId) {
                $this->saveCheckpoint($request, 'post', (string) $checkpointId, ['result' => get_object_vars($result)]);
            }
        }

        return $result;
    }

    public function orchestratePage(MultimodalOrchestrationRequest $request): MultimodalOrchestrationResult
    {
        $checkpoint = $this->checkpoint($request, 'page');
        if (isset($checkpoint['result'])) {
            return new MultimodalOrchestrationResult(...$checkpoint['result']);
        }
        $sys = 'Eres un diseñador de páginas y arquitecto web senior en dbCMS. Diseña una página corporativa o landing page completa.';
        $usr = "Tema: {$request->topic}\nMercado: {$request->market}\nIdioma: {$request->locale}\nGenera JSON: {title, slug, summary, meta_title, meta_description, keywords: []}";

        if ($checkpoint !== null) {
            $data = $checkpoint['data'];
        } else {
            $completion = $this->configBridge->generateCompletion($sys, $usr, $request->textProvider, ['json' => true]);
            $data = json_decode($completion['content'], true);
            if (! is_array($data)) {
                throw new AiProviderException('Editorial generation returned invalid JSON.');
            }
        }

        $title = (string) ($data['title'] ?? Str::headline($request->topic));
        $slug = (string) ($data['slug'] ?? Str::slug($title));
        $summary = (string) ($data['summary'] ?? "Página sobre {$request->topic}");
        $metaTitle = (string) ($data['meta_title'] ?? $title);
        $metaDescription = (string) ($data['meta_description'] ?? $summary);
        $keywords = array_values(array_map('strval', (array) ($data['keywords'] ?? ['web', 'landing'])));

        $featuredImageUrl = $checkpoint['featured_image_url'] ?? null;
        $videoUrl = $checkpoint['video_url'] ?? null;
        $mediaIds = $checkpoint['media_ids'] ?? [];
        $sources = $checkpoint['sources'] ?? [];

        if ($request->generateImage && $featuredImageUrl === null) {
            try {
                $img = $this->generateImage(prompt: '', content: $summary, title: $title, options: $this->mediaOptions($request, 'image', $request->imageProvider, $checkpoint));
                $featuredImageUrl = $img['url'];
                if ($img['media_file_id']) {
                    $mediaIds[] = $img['media_file_id'];
                }
            } catch (AiProviderPendingException $error) {
                $this->saveCheckpoint($request, 'page', $error->requestId, ['stage' => 'image', 'provider' => $error->provider, 'model' => $this->mediaOptions($request, 'image', $request->imageProvider, $checkpoint)['model'], 'data' => $data, 'sources' => $sources, 'featured_image_url' => $featuredImageUrl, 'video_url' => $videoUrl, 'media_ids' => $mediaIds]);
                throw $error;
            }
        }

        if ($request->generateVideo && $videoUrl === null) {
            try {
                $vid = $this->generateVideo(prompt: '', content: $summary, title: $title, options: $this->mediaOptions($request, 'video', $request->videoProvider, $checkpoint) + ['reference_image_url' => $featuredImageUrl]);
                $videoUrl = $vid['url'];
                if ($vid['media_file_id']) {
                    $mediaIds[] = $vid['media_file_id'];
                }
            } catch (AiProviderPendingException $error) {
                $this->saveCheckpoint($request, 'page', $error->requestId, ['stage' => 'video', 'provider' => $error->provider, 'model' => $this->mediaOptions($request, 'video', $request->videoProvider, $checkpoint)['model'], 'data' => $data, 'sources' => $sources, 'featured_image_url' => $featuredImageUrl, 'video_url' => $videoUrl, 'media_ids' => $mediaIds]);
                throw $error;
            }
        }

        // Assemble Landing Sequence: Hero, Features, ROI/KPI, Testimonials, FAQ, CTA
        $sections = [];
        $sections[] = [
            'type' => 'hero',
            'data' => $this->blockIntelligence->sanitizeBlockPayload('hero', [
                'title' => $title,
                'content' => $summary,
                'cta_text' => 'Comenzar ahora',
                'cta_url' => '/contacto',
                'image_url' => $featuredImageUrl ?? '',
                'video_url' => $videoUrl ?? '',
                'badge' => 'Nuevo Lanzamiento',
            ]),
        ];

        $sections[] = [
            'type' => 'features',
            'data' => $this->blockIntelligence->sanitizeBlockPayload('features', [
                'title' => 'Beneficios Principales',
                'columns' => '3',
                'items' => [
                    ['icon' => '⚡', 'title' => 'Velocidad y Precisión', 'description' => 'Optimizado para rendimiento instantáneo.'],
                    ['icon' => '🛡️', 'title' => 'Fiabilidad Absoluta', 'description' => 'Arquitectura desacoplada y robusta.'],
                    ['icon' => '✨', 'title' => 'Experiencia Visual', 'description' => 'Activos de alta resolución integrados.'],
                ],
            ]),
        ];

        if ($videoUrl !== null) {
            $sections[] = [
                'type' => 'video',
                'data' => $this->blockIntelligence->sanitizeBlockPayload('video', [
                    'video_url' => $videoUrl,
                    'poster_url' => $featuredImageUrl ?? '',
                    'title' => 'Tour Virtual',
                    'caption' => 'Descubre la plataforma en acción.',
                ]),
            ];
        }

        $sections[] = [
            'type' => 'faq',
            'data' => $this->blockIntelligence->sanitizeBlockPayload('faq', [
                'title' => 'Preguntas Frecuentes',
                'items' => [
                    ['question' => '¿Cómo empezar?', 'answer' => 'Contáctanos y configuramos tu acceso en minutos.'],
                    ['question' => '¿Qué garantías ofrecemos?', 'answer' => 'Soporte continuo y máxima disponibilidad garantizada.'],
                ],
            ]),
        ];

        $sections[] = [
            'type' => 'cta',
            'data' => $this->blockIntelligence->sanitizeBlockPayload('cta', [
                'title' => '¿Listo para dar el siguiente paso?',
                'content' => 'Únete a las empresas que ya utilizan nuestra tecnología.',
                'cta_text' => 'Solicitar demo gratuita',
                'cta_url' => '/contacto',
            ]),
        ];

        $entityId = null;
        if ($request->persistDraft) {
            $entityId = $this->drafts->create('page', $request, [
                'title' => $title, 'slug' => $slug, 'summary' => $summary, 'content' => $summary,
                'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'featured_image' => $featuredImageUrl,
            ], $sections);
        }

        $result = new MultimodalOrchestrationResult(
            entityType: 'page',
            entityId: $entityId,
            title: $title,
            slug: $slug,
            summary: $summary,
            content: $summary,
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            keywords: $keywords,
            featuredImageUrl: $featuredImageUrl,
            videoUrl: $videoUrl,
            mediaFileIds: $mediaIds,
            sections: $sections,
            antiAiAudit: ['status' => 'not_assessed'],
        );
        if ($checkpoint !== null) {
            foreach ($checkpoint['_checkpoint_ids'] as $checkpointId) {
                $this->saveCheckpoint($request, 'page', (string) $checkpointId, ['result' => get_object_vars($result)]);
            }
        }

        return $result;
    }

    private function synthesizeVisualPrompt(string $content, string $title): string
    {
        $prompt = "A high-end editorial commercial photograph representing {$title}. Studio lighting, professional 35mm photography, 8k resolution, shallow depth of field, cinematic color grading, hyper-detailed textures, no text overlays.";

        return $prompt;
    }

    private function synthesizeVideoScript(string $content, string $title): string
    {
        return "Cinematic slow camera push-in revealing {$title}, atmospheric cinematic depth, dynamic lighting transitions, realistic motion blur, 4k ultra-high definition.";
    }

    /** @return array<string, mixed>|null */
    private function checkpoint(MultimodalOrchestrationRequest $request, string $type): ?array
    {
        $id = (string) ($request->options['provider_request_id'] ?? '');
        if ($id === '') {
            return null;
        }
        $visited = [];
        for ($depth = 0; $depth < 5; $depth++) {
            $visited[] = $id;
            $stored = Cache::get($this->checkpointKey($request, $type, $id));
            if (! is_string($stored)) {
                throw new AiProviderException('Composite checkpoint is missing or expired; do not resubmit the paid generation.');
            }
            $data = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new AiProviderException('Invalid composite checkpoint.');
            }
            if (is_string($data['next_request_id'] ?? null)) {
                $id = $data['next_request_id'];

                continue;
            }
            $data['_request_id'] = $id;
            $data['_checkpoint_ids'] = array_values(array_unique(array_merge($visited, (array) ($data['_checkpoint_ids'] ?? []))));

            return $data;
        }
        throw new AiProviderException('Composite checkpoint chain is invalid.');
    }

    /** @param array<string, mixed> $data */
    private function saveCheckpoint(MultimodalOrchestrationRequest $request, string $type, string $id, array $data): void
    {
        $previousId = (string) ($request->options['provider_request_id'] ?? '');
        if (isset($data['stage'])) {
            $data['_checkpoint_ids'] = array_values(array_unique(array_filter([$id, $previousId])));
            if ($previousId !== '' && $previousId !== $id) {
                Cache::put($this->checkpointKey($request, $type, $previousId), Crypt::encryptString(json_encode(['next_request_id' => $id], JSON_THROW_ON_ERROR)), now()->addDay());
            }
        }
        Cache::put($this->checkpointKey($request, $type, $id), Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)), now()->addDay());
    }

    private function checkpointKey(MultimodalOrchestrationRequest $request, string $type, string $id): string
    {
        return 'ai:composite:'.hash('sha256', (string) ($request->options['actor_id'] ?? 0).'|'.$type.'|'.$id);
    }

    /**
     * @param  array<string, mixed>|null  $checkpoint
     * @return array<string, mixed>
     */
    private function mediaOptions(MultimodalOrchestrationRequest $request, string $stage, ?string $provider, ?array $checkpoint): array
    {
        $options = $request->options;
        unset($options['provider_request_id'], $options['provider'], $options['model']);
        $options['provider'] = $provider ?? $this->settings->activeProvider($stage);
        $options['model'] = $request->options[$stage.'_model'] ?? $this->settings->modelForModality($options['provider'], $stage) ?? $this->defaultMediaModel($options['provider'], $stage);
        if (($checkpoint['stage'] ?? null) === $stage) {
            $options['provider_request_id'] = $checkpoint['_request_id'];
            $options['provider'] = $checkpoint['provider'];
            $options['model'] = $checkpoint['model'];
        }

        return $options;
    }

    private function defaultMediaModel(string $provider, string $modality): string
    {
        return match ($provider.':'.$modality) {
            'openai:image' => 'gpt-image-1',
            'gemini:image' => 'gemini-2.5-flash-image',
            'magnific:image' => 'realism',
            'magnific:video' => 'ltx-2-pro',
            'higgsfield:video' => 'bytedance/seedance-2.0/text-to-video',
            default => throw new AiProviderException('Select a model for the requested media modality.'),
        };
    }
}
