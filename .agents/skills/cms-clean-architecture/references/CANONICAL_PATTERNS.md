# Canonical Design Patterns in dbCMS

## 1. Descriptive Invokable Verb Controller Pattern
Every controller action is a dedicated class implementing `__invoke`, named descriptively with entity prefix and HTTP verb suffix (e.g. `PagesGetController`, `PageCreateGetController`, `PagePostController`, `PageEditGetController`, `PagePutController`, `PageDeleteController`) located directly inside the domain namespace without deep directory nesting.

```php
declare(strict_types=1);

namespace Modules\Pages\Http\Controllers\Admin;

use App\Contracts\Services\PageServiceInterface;
use App\Http\Controllers\Controller;
use Modules\Pages\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PagePutController extends Controller
{
    public function __construct(
        private readonly PageServiceInterface $pageService
    ) {}

    public function __invoke(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'is_published' => 'boolean',
            'translations' => 'required|array',
        ]);

        $this->pageService->updatePage($page, $validated);

        return redirect()->route('admin.pages.index')->with('success', 'Página actualizada.');
    }
}
```

## 2. Decoupled View Composer Pattern
Blade templates never fetch data. A View Composer supplies global layout context.

```php
declare(strict_types=1);

namespace App\Http\ViewComposers;

use App\Services\Admin\AdminNavigationService;
use Illuminate\View\View;

final class AdminLayoutComposer
{
    public function __construct(
        private readonly AdminNavigationService $navigationService
    ) {}

    public function compose(View $view): void
    {
        $view->with('navSections', $this->navigationService->getNavigationSections());
        $view->with('activeModulesCount', $this->navigationService->getActiveModulesCount());
    }
}
```

## 3. Thin Controller & Fat Domain Service
Controllers are thin: `__invoke()` holds at most 10 lines, delegating to a domain service:

```php
declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers\Admin\BrandProfile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;

final class PutController extends Controller
{
    public function __construct(
        private readonly AiBrandProfileServiceInterface $brandProfileService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'archetype' => 'nullable|string',
            'pronoun_treatment' => 'nullable|in:tu,usted,vosotros,ustedes',
            'tone_keywords' => 'nullable|string',
            'target_audience' => 'nullable|string',
            'value_proposition' => 'nullable|string',
            'forbidden_terms' => 'nullable|string',
            'preferred_terms' => 'nullable|string',
            'editorial_rules' => 'nullable|string',
        ]);

        $profile = $this->brandProfileService->saveProfile($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil de marca actualizado exitosamente.',
            'profile' => $profile,
        ]);
    }
}
```

## 4. Single-Responsibility Sub-Services
Decompose large orchestrators into smaller collaborators:
- `McpToolRegistry`: registers and schemas MCP tools.
- `McpSearchService`: vector or keyword search.
- `McpExecutionDispatcher`: validates auth and dispatches execution.
