<?php

declare(strict_types=1);

namespace Modules\CreativeStudio\Services;

use App\Core\Capabilities\ActorContext;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Http\Request;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\CreativeStudio\Models\CreativeCampaign;
use Modules\CreativeStudio\Models\CreativeGenerationTask;
use Modules\CreativeStudio\Models\CreativeInputAsset;
use Modules\Marketing\Contracts\MarketingArtifactStoreInterface;
use Modules\Marketing\DTOs\ArtifactDescriptor;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

final class CreativeStudioHttpService
{
    public function __construct(private readonly CreativeStudioDomainService $domain, private readonly CreativeStudioAccess $access, private readonly ActorContextFactory $actors, private readonly MarketingArtifactStoreInterface $store) {}

    private function actor(Request $request): ActorContext
    {
        return $this->actors->fromRequest($request, $request->is('api/*') ? 'api' : 'web');
    }

    public function index(Request $request): Response
    {
        $this->access->check($this->actor($request), 'read');
        $campaigns = CreativeCampaign::with(['translations', 'markets'])->latest()->paginate(20);

        return $request->expectsJson() ? response()->json(['data' => $campaigns]) : response()->view('creative-studio::admin.index', ['campaigns' => $campaigns, 'markets' => Market::where('is_active', true)->get(), 'languages' => Language::where('is_active', true)->get()]);
    }

    public function show(Request $request, string $campaign): Response
    {
        $this->access->check($this->actor($request), 'read');
        $item = CreativeCampaign::with(['translations', 'markets', 'revisions'])->findOrFail($campaign);
        $tasks = CreativeGenerationTask::where('campaign_id', $campaign)->latest()->get(['id', 'status', 'reserved_cost', 'confirmed_cost', 'revision_id', 'error', 'created_at']);
        $assets = CreativeInputAsset::where('campaign_id', $campaign)->latest()->get();
        if ($request->expectsJson()) {
            return response()->json(['data' => $item, 'tasks' => $tasks, 'assets' => $assets]);
        }
        $aiAvailable = app(ModuleManager::class)->isModuleEnabled('ai_assistant');
        $providers = $aiAvailable && app()->bound(AiConfigBridgeInterface::class) ? app(AiConfigBridgeInterface::class)->getProvidersCatalog() : [];

        return response()->view('creative-studio::admin.show', ['campaign' => $item, 'tasks' => $tasks, 'assets' => $assets, 'providers' => $providers, 'aiAvailable' => $aiAvailable]);
    }

    public function sources(Request $request): Response
    {
        $data = $request->validate(['locale' => 'required|string|max:10', 'market_id' => 'required|integer|min:1']);

        return response()->json(['data' => $this->domain->sourceChoices((string) $data['locale'], (int) $data['market_id'], $this->actor($request))]);
    }

    public function dates(Request $request, string $campaign): Response
    {
        $item = $this->domain->setDates($campaign, $request->all(), $this->actor($request));

        return $this->result($request, $item->toArray(), route('admin.creative-studio.show', $campaign));
    }

    public function budget(Request $request, string $campaign): Response
    {
        $item = $this->domain->setBudget($campaign, $request->all(), $this->actor($request));

        return $this->result($request, $item->toArray(), route('admin.creative-studio.show', $campaign));
    }

    public function create(Request $request): Response
    {
        $campaign = $this->domain->createCampaign($request->all(), $this->actor($request));

        return $this->result($request, $campaign->toArray(), route('admin.creative-studio.show', $campaign->id), 201);
    }

    public function brief(Request $request, string $campaign): Response
    {
        $item = $this->domain->setBrief($campaign, $request->all(), $this->actor($request));

        return $this->result($request, $item->toArray(), route('admin.creative-studio.show', $campaign));
    }

    public function brand(Request $request, string $campaign): Response
    {
        $item = $this->domain->setBrand($campaign, $request->all(), $this->actor($request));

        return $this->result($request, $item->toArray(), route('admin.creative-studio.show', $campaign));
    }

    public function revise(Request $request, string $campaign): Response
    {
        $revision = $this->domain->createRevision($campaign, $request->all(), $this->actor($request));

        return $this->result($request, $revision->toArray(), route('admin.creative-studio.show', $campaign), 201);
    }

    public function generate(Request $request, string $campaign): Response
    {
        $task = $this->domain->enqueueGeneration($campaign, $request->all(), $this->actor($request));

        return $this->result($request, ['id' => $task->id, 'status' => $task->status, 'reserved_cost' => $task->reserved_cost], route('admin.creative-studio.show', $campaign), 202);
    }

    public function compose(Request $request, string $campaign): Response
    {
        $task = $this->domain->enqueueComposition($campaign, $request->all(), $this->actor($request));

        return $this->result($request, ['id' => $task->id, 'status' => $task->status], route('admin.creative-studio.show', $campaign), 202);
    }

    public function upload(Request $request, string $campaign): Response
    {
        $data = $request->validate(['file' => 'required|file|max:262144']);
        $asset = $this->domain->importUpload($campaign, $data['file'], $this->actor($request));

        return $this->result($request, $asset->toArray(), route('admin.creative-studio.show', $campaign), 201);
    }

    public function task(Request $request, string $task): Response
    {
        $this->access->check($this->actor($request), 'read');
        $item = CreativeGenerationTask::findOrFail($task);

        return response()->json(['data' => $this->taskData($item)]);
    }

    public function resume(Request $request, string $task): Response
    {
        $item = $this->domain->resumeGeneration($task, $this->actor($request));

        return $this->result($request, $this->taskData($item), route('admin.creative-studio.show', $item->campaign_id), 202);
    }

    public function reconcile(Request $request, string $task): Response
    {
        $item = $this->domain->reconcileGeneration($task, $this->actor($request));

        return $this->result($request, $this->taskData($item), route('admin.creative-studio.show', $item->campaign_id), 202);
    }

    public function cancel(Request $request, string $task): Response
    {
        $item = $this->domain->cancelGeneration($task, $this->actor($request));

        return $this->result($request, $this->taskData($item), route('admin.creative-studio.show', $item->campaign_id));
    }

    public function package(Request $request, string $revision): Response
    {
        return response()->json(['data' => $this->domain->getPackage($revision, $this->actor($request))->toArray()]);
    }

    public function preview(Request $request, string $revision, int $position): Response
    {
        $package = $this->domain->getPackage($revision, $this->actor($request));
        $artifact = $package->artifacts[$position] ?? null;
        abort_if($artifact === null, 404);

        return response()->file($this->store->absolutePath($artifact), ['Content-Type' => $artifact->mimeType, 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function asset(Request $request, string $campaign, string $asset): Response
    {
        $this->access->check($this->actor($request), 'read');
        $item = CreativeInputAsset::where('campaign_id', $campaign)->whereKey($asset)->firstOrFail();
        $artifact = ArtifactDescriptor::fromArray($item->artifact);
        abort_unless($this->store->verify($artifact), 409);

        return response()->file($this->store->absolutePath($artifact), ['Content-Type' => $artifact->mimeType, 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function export(Request $request, string $revision): Response
    {
        $package = $this->domain->getPackage($revision, $this->actor($request));
        $temporary = tempnam(storage_path('app/private'), 'creative-export-');
        if ($temporary === false) {
            abort(503, 'No hay almacenamiento temporal.');
        }$zip = new ZipArchive;
        if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporary);
            abort(503, 'No se puede preparar la exportación.');
        }
        $manifest = $package->toArray();
        $manifest['fingerprint'] = $package->fingerprint();
        $manifest['files'] = [];
        foreach ($package->artifacts as $index => $artifact) {
            $name = sprintf('%02d-', ($index + 1)).basename($artifact->path);
            $zip->addFile($this->store->absolutePath($artifact), $name);
            $manifest['files'][] = ['name' => $name, 'sha256' => $artifact->sha256];
        }
        $zip->addFromString('copy.txt', $package->text.($package->destinationUrl !== null ? "\n\n".$package->destinationUrl : ''));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        return response()->download($temporary, 'creative-'.$package->revisionId.'.zip', ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow'])->deleteFileAfterSend(true);
    }

    /** @return array<string,mixed> */
    public function taskData(CreativeGenerationTask $task): array
    {
        return ['id' => $task->id, 'campaign_id' => $task->campaign_id, 'status' => $task->status, 'revision_id' => $task->revision_id, 'reserved_cost' => $task->reserved_cost, 'confirmed_cost' => $task->confirmed_cost, 'error' => $task->error, 'receipts' => array_values(array_map(static fn (array $stage): array => (array) ($stage['receipt'] ?? []), $task->stages))];
    }

    /** @param array<string,mixed> $data */
    private function result(Request $request, array $data, string $returnUrl, int $status = 200): Response
    {
        return $request->expectsJson() ? response()->json(['data' => $data], $status) : redirect($returnUrl)->with('success', 'Guardado. Las tareas pendientes aparecerán al actualizar la campaña.');
    }
}
