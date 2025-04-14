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

            // Register Debugbar
            if ($this->app['config']->get('jaravel.use_debugbar', false)) {
                // Register Debugbar middleware for all web routes
                if ($this->app->bound('router')) {
                    $this->app['router']->pushMiddlewareToGroup('web', \Jaravel\Middleware\DebugbarMiddleware::class);
                }

                // Register Laravel Debugbar if the class exists
                if (class_exists('\Barryvdh\Debugbar\ServiceProvider')) {
                    $this->app->register('\Barryvdh\Debugbar\ServiceProvider');
                    $this->app->alias('Debugbar', '\Barryvdh\Debugbar\Facade');
                }
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

        // Configure Debugbar if it's available
        if ($this->app->bound('debugbar')) {
            // Make sure Debugbar is enabled
            $this->app['debugbar']->enable();

            // Add Jaravel information
            $this->app['debugbar']->addMessage('Jaravel Framework', 'jaravel');

            if ($this->app->bound('jaravel.component_id')) {
                $this->app['debugbar']->addMessage(
                    'Component: ' . $this->app['jaravel.component_id'],
                    'jaravel'
                );
            }
        } else {
            // Log that Debugbar is not available
            Debug::log('Laravel Debugbar is not bound in the service container');
        }
    }
}