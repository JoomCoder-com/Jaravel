<?php
// libraries/jaravel/src/JaravelServiceProvider.php

namespace Jaravel;

use Illuminate\Support\ServiceProvider;
use Jaravel\Instance\Manager;
use Jaravel\Support\BladeServiceProvider;
use Jaravel\Support\Debug;
use Jaravel\Support\Bootstrap;

/**
 * Core service provider for Jaravel
 */
class JaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Initialize the bootstrap
        Bootstrap::init();

        // Register the instance manager
        $this->app->singleton('jaravel.manager', function ($app) {
            return new Manager();
        });

        // Register the 'jaravel' binding for the facade
        $this->app->singleton('jaravel', function ($app) {
            return new Support\JaravelService($app);
        });

        // Register debug service
        $this->app->singleton('jaravel.debug', function ($app) {
            return new Debug();
        });

        // Check for debug settings in config
        if ($this->app->bound('config')) {
            if ($this->app['config']->get('jaravel.debug', false)) {
                Debug::enable();
            }
        }

        // Register additional service providers
        $this->app->register(BladeServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Nothing additional needed here since Bootstrap::init() is called in register()
    }
}