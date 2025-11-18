<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Scramble to use /api-docs instead of /docs/api
        Scramble::registerUiRoute('api-docs')
            ->middleware(config('scramble.middleware', []));

        Scramble::registerJsonSpecificationRoute('api-docs.json')
            ->middleware(config('scramble.middleware', []));

        // Filter routes to only show /api/v1/* endpoints
        Scramble::routes(function ($router) {
            return $router
                ->whereStartsWith('api/v1');
        });

        // Remove User schema from documentation
        Scramble::extendOpenApi(function ($openApi) {
            $schemas = $openApi->components->schemas ?? [];
            if (isset($schemas['User'])) {
                unset($openApi->components->schemas['User']);
            }
            return $openApi;
        });
    }
}
