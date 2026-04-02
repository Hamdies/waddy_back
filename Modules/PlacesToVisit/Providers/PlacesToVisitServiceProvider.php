<?php

namespace Modules\PlacesToVisit\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PlacesToVisit\Services\LeaderboardService;
use Modules\PlacesToVisit\Services\TrendingService;
use Modules\PlacesToVisit\Services\VotingService;

class PlacesToVisitServiceProvider extends ServiceProvider
{
    protected $moduleName = 'PlacesToVisit';
    protected $moduleNameLower = 'placestovisit';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(LeaderboardService::class);
        $this->app->singleton(VotingService::class);
        $this->app->singleton(TrendingService::class);
    }

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        // Routes are auto-loaded by nwidart/laravel-modules
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    public function provides(): array
    {
        return [
            LeaderboardService::class,
            VotingService::class,
            TrendingService::class,
        ];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
