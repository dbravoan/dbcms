@if(app(\App\Services\Cms\CookieConsentService::class)->isModuleActive('analytics'))
    {!! app(\Modules\Analytics\Services\AnalyticsManagerService::class)->renderGa4Script() !!}
@endif
