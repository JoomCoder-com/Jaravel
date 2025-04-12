<?php
// libraries/jaravel/src/Support/BladeServiceProvider.php

namespace Jaravel\Support;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Blade extensions
 */
class BladeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register custom Blade directives for URL generation
        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives.
     *
     * @return void
     */
    protected function registerBladeDirectives()
    {
        // Blade directive for route URLs
        Blade::directive('jaravelRoute', function ($expression) {
            return "<?php echo \\Jaravel\\Support\\UrlHelper::route($expression); ?>";
        });

        // Blade directive for named route URLs
        Blade::directive('jaravelNamedRoute', function ($expression) {
            return "<?php echo \\Jaravel\\Support\\UrlHelper::namedRoute($expression); ?>";
        });

        // Blade directive for asset URLs
        Blade::directive('jaravelAsset', function ($expression) {
            return "<?php echo \\Jaravel\\Support\\UrlHelper::asset($expression); ?>";
        });
    }
}