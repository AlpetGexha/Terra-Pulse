<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // Register individual services as singletons
        $this->app->singleton(\App\Services\CopernicusService::class);
        $this->app->singleton(\App\Services\ScoringService::class);
        $this->app->singleton(\App\Services\GalileoService::class);
        $this->app->singleton(\App\Services\WeatherService::class);
        $this->app->singleton(\App\Services\RouteSafetyService::class);
        $this->app->singleton(\App\Services\TravelIntelligenceService::class);
        $this->app->singleton(\App\Services\RoutePlanningService::class);
        $this->app->singleton(\App\Services\EmergencySatelliteService::class);

        // Keep the existing action for backward compatibility
        $this->app->singleton(\App\Actions\ComputeDestinationHealthScoreAction::class, function ($app) {
            return new \App\Actions\ComputeDestinationHealthScoreAction(
                $app->make(\App\Services\ScoringService::class),
                $app->make(\App\Services\CopernicusService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // $this->configurateModels();
        // $this->configurateCommands();
        // $this->configurateURL();
    }

    private function configurateModels(): void
    {
        Model::automaticallyEagerLoadRelationships();
        // Model::unguard();
        Model::shouldBeStrict(! app()->isProduction());
        Model::preventLazyLoading(! app()->isProduction());
    }

    private function configurateCommands(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction()
        );
    }

    private function configurateURL(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
