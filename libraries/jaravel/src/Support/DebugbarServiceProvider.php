<?php
// libraries/jaravel/src/Support/DebugbarServiceProvider.php

namespace Jaravel\Support;

use Illuminate\Support\ServiceProvider;
use Barryvdh\Debugbar\Facade as DebugbarFacade;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;

class DebugbarServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register the original Laravel Debugbar service provider
        $this->app->register(DebugbarServiceProvider::class);

        // Register the Facade alias
        $this->app->alias('Debugbar', DebugbarFacade::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->bound('debugbar')) {
            $debugbar = $this->app['debugbar'];

            // Configure Debugbar
            $this->configureDebugbar($debugbar);

            // Add a custom Jaravel collector
            $this->addJaravelCollector($debugbar);
        }
    }

    /**
     * Configure Debugbar settings
     *
     * @param \Barryvdh\Debugbar\LaravelDebugbar $debugbar
     * @return void
     */
    protected function configureDebugbar($debugbar)
    {
        // Set storage path for debugbar
        $debugbar->setStorage(new \DebugBar\Storage\FileStorage(JPATH_CACHE . '/debugbar'));

        // Customize configuration
        $debugbar->enable();

        // Set a custom JavascriptRenderer instance
        $renderer = $debugbar->getJavascriptRenderer();
        $renderer->setBaseUrl('/media/jaravel/debugbar');

        // Ensure the assets are up to date
        $renderer->setEnableJqueryNoConflict(true);
    }

    /**
     * Add a custom Jaravel collector to Debugbar
     *
     * @param \Barryvdh\Debugbar\LaravelDebugbar $debugbar
     * @return void
     */
    protected function addJaravelCollector($debugbar)
    {
        $debugbar->addCollector(new \DebugBar\DataCollector\MessagesCollector('jaravel'));

        // Add Joomla version info
        $debugbar->addMessage('Joomla Version: ' . \Joomla\CMS\Version::MAJOR_VERSION . '.' . \Joomla\CMS\Version::MINOR_VERSION, 'jaravel');

        // Add component info if available
        if ($this->app->bound('jaravel.component_id')) {
            $debugbar->addMessage('Component: ' . $this->app['jaravel.component_id'], 'jaravel');
        }
    }
}