<?php

declare(strict_types=1);

namespace App\Http\ViewComposers;

use App\Contracts\Services\AdminNavigationServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use App\Services\Cms\BackofficeLocaleService;
use Illuminate\View\View;

class AdminLayoutComposer
{
    public function __construct(
        protected AdminNavigationServiceInterface $navigationService,
        protected BackofficeLocaleService $backofficeLocale,
        protected LocalizationServiceInterface $localization
    ) {}

    /**
     * Bind data to the admin layout view.
     */
    public function compose(View $view): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        $isAdmin = $user?->isAdmin() ?? false;
        $isViewer = $user?->hasRole('viewer') ?? false;
        $isEditor = $user?->hasRole('editor') ?? false;
        $isAuthor = $user?->hasRole('author') ?? false;
        $isMarketing = $user?->hasRole('marketing') ?? false;

        $view->with([
            'currentUser' => $user,
            'isAdmin' => $isAdmin,
            'isViewer' => $isViewer,
            'isEditor' => $isEditor,
            'isAuthor' => $isAuthor,
            'isMarketing' => $isMarketing,
            'navSections' => $this->navigationService->getNavigationSections($user),
            'activeModules' => $this->navigationService->getActiveModuleIdentifiers(),
            'isMaintenanceActive' => $this->navigationService->isMaintenanceActive(),
            'pendingAuditCount' => $this->navigationService->getPendingAuditCount(),
            'activeLanguages' => $this->localization->activeLanguages(),
            'activeMarkets' => $this->localization->activeMarkets(),
            'allRoles' => Role::all(),
            'languageFlags' => $this->localization->activeLanguages()
                ->mapWithKeys(static fn (Language $language): array => [$language->code => $language->flag])
                ->all(),
            'currentAdminLocale' => app()->getLocale(),
            'availableAdminLocales' => $this->backofficeLocale->available(),
        ]);
    }
}
