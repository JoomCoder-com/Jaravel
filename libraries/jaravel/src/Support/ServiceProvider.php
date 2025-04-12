<?php
namespace Jaravel\Support;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Base service provider for Jaravel components
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register Joomla-specific services
        $this->registerJoomlaServices();

        // Register component-specific paths
        $this->registerComponentPaths();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Get component ID from container
        $componentId = $this->app->make('jaravel.component_id');

        // Configure view paths
        $this->configureViewPaths($componentId);

        // Configure translations
        $this->configureTranslations($componentId);

        // Handle Joomla-specific middleware
        $this->handleJoomlaMiddleware();
    }

    /**
     * Register Joomla-specific services.
     *
     * @return void
     */
    protected function registerJoomlaServices()
    {
        // Bind Joomla application to container
        $this->app->singleton('joomla.app', function ($app) {
            return \Joomla\CMS\Factory::getApplication();
        });

        // Bind Joomla database to container
        $this->app->singleton('joomla.db', function ($app) {
            return \Joomla\CMS\Factory::getDbo();
        });

        // Bind Joomla user to container
        $this->app->singleton('joomla.user', function ($app) {
            return \Joomla\CMS\Factory::getUser();
        });

        // Bind Joomla language to container
        $this->app->singleton('joomla.language', function ($app) {
            return \Joomla\CMS\Factory::getLanguage();
        });

        // Bind Joomla session to container
        $this->app->singleton('joomla.session', function ($app) {
            return \Joomla\CMS\Factory::getSession();
        });
    }

    /**
     * Register component-specific paths.
     *
     * @return void
     */
    protected function registerComponentPaths()
    {
        // Get component ID from container
        $componentId = $this->app->make('jaravel.component_id');

        // Define component paths
        $componentPath = JPATH_SITE . '/components/' . $componentId;
        $mediaPath = JPATH_SITE . '/media/' . $componentId;

        // Register paths in config
        $this->app['config']->set('jaravel.paths.component', $componentPath);
        $this->app['config']->set('jaravel.paths.media', $mediaPath);
    }

    /**
     * Configure view paths for the component.
     *
     * @param string $componentId
     * @return void
     */
    protected function configureViewPaths($componentId)
    {
        // Define view paths
        $componentPath = JPATH_SITE . '/components/' . $componentId;
        $viewPaths = [
            $componentPath . '/resources/views',
            $componentPath . '/views'
        ];

        // Configure Laravel View Factory to use these paths
        $this->loadViewsFrom($viewPaths, $componentId);
    }

    /**
     * Configure translations for the component.
     *
     * @param string $componentId
     * @return void
     */
    protected function configureTranslations($componentId)
    {
        // Define language paths
        $componentPath = JPATH_SITE . '/components/' . $componentId;
        $langPath = $componentPath . '/resources/lang';

        // Configure Laravel Translator to use these paths
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $componentId);
        }

        // Use Joomla's language system for fallback translations
        $this->app->resolving('translator', function ($translator, $app) use ($componentId) {
            $translator->setFallback(function ($key, $locale) use ($componentId) {
                // Try to get translation from Joomla
                $jLang = \Joomla\CMS\Factory::getLanguage();
                return $jLang->_($key);
            });
        });
    }

    /**
     * Handle Joomla-specific middleware.
     *
     * @return void
     */
    protected function handleJoomlaMiddleware()
    {
        // Add Joomla authentication middleware
        $this->app['router']->aliasMiddleware('joomla.auth', \Jaravel\Middleware\JoomlaAuthentication::class);

        // Add Joomla CSRF protection middleware
        $this->app['router']->aliasMiddleware('joomla.csrf', \Jaravel\Middleware\JoomlaCsrfToken::class);

        // Add Joomla permissions middleware
        $this->app['router']->aliasMiddleware('joomla.can', \Jaravel\Middleware\JoomlaAuthorization::class);
    }
}