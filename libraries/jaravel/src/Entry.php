<?php
namespace Jaravel;

use Jaravel\Instance\Manager;
use Jaravel\Support\Debug;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;

class Entry
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var array Registered components
     */
    protected static $registeredComponents = [];

    /**
     * @var bool Whether route debugging is enabled
     */
    protected $routeDebuggingEnabled = false;

    /**
     * @var bool Whether Laravel Debugbar is enabled
     */
    protected $debugbarEnabled = false;

    /**
     * @var array Cached routes by component
     */
    protected $routeCache = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->manager = new Manager();
    }

    /**
     * Get the instance manager
     *
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Enable debug mode
     *
     * @param bool $enabled
     * @return void
     */
    public function enableDebug($enabled = true)
    {
        Debug::enable($enabled);

        if ($enabled) {
            Debug::registerErrorHandlers();
        }
    }

    /**
     * Enable Laravel Debugbar
     *
     * @param bool $enabled
     * @return void
     */
    public function enableDebugbar($enabled = true)
    {
        $this->debugbarEnabled = $enabled;

        // Make sure debug is also enabled if debugbar is enabled
        if ($enabled) {
            $this->enableDebug(true);
        }
    }

    /**
     * Enable detailed route debugging
     *
     * @param bool $enabled
     * @return void
     */
    public function enableRouteDebugging($enabled = true)
    {
        $this->routeDebuggingEnabled = $enabled;

        if ($enabled) {
            // Make sure debug is also enabled
            $this->enableDebug(true);
        }
    }

    /**
     * Register a Joomla component with Jaravel
     *
     * @param string $componentName
     * @return void
     */
    public function registerComponent($componentName)
    {
        if (in_array($componentName, self::$registeredComponents)) {
            return;
        }

        Debug::log("Registering component: {$componentName}");

        // Initialize the application instance - this will also setup the Facade root
        $app = $this->manager->getInstance($componentName);

        // Configure application settings
        $this->configureApplication($app, $componentName);

        // Register the component
        self::$registeredComponents[] = $componentName;

        // Cache routes if route debugging is enabled
        if ($this->routeDebuggingEnabled) {
            Debug::log("Pre-loading routes for component: {$componentName}");
            $this->loadRoutes($app, $componentName);
            $this->cacheRoutes($componentName);
        }
    }

    // In the configureApplication method of Entry.php, add:

    /**
     * Configure application settings
     *
     * @param \Illuminate\Foundation\Application $app
     * @param string $componentName
     * @return void
     */
    protected function configureApplication($app, $componentName)
    {
        // Set debugging config
        $app['config']->set('app.debug', Debug::isEnabled());
        $app['config']->set('jaravel.debug', Debug::isEnabled());
        $app['config']->set('jaravel.use_debugbar', $this->debugbarEnabled);

        if ($this->debugbarEnabled) {
            try {
                // Check if the class exists
                if (class_exists('\Barryvdh\Debugbar\ServiceProvider')) {
                    // Register Debugbar service provider
                    Debug::log("Registering Debugbar service provider");
                    $app->register('\Barryvdh\Debugbar\ServiceProvider');

                    // Create alias
                    $app->alias('Debugbar', '\Barryvdh\Debugbar\Facade');

                    // Make sure it's enabled in the config
                    $app['config']->set('debugbar.enabled', true);

                    // Set storage path
                    $storagePath = JPATH_CACHE . '/debugbar';
                    if (!is_dir($storagePath)) {
                        mkdir($storagePath, 0755, true);
                    }
                    $app['config']->set('debugbar.storage.path', $storagePath);

                    // Set the base URL for assets (using the media directory)
                    $baseUrl = \Joomla\CMS\Uri\Uri::root(true) . '/media/jaravel/debugbar';
                    $app['config']->set('debugbar.javascript_renderer', [
                        'base_url' => $baseUrl
                    ]);

                    // Enable all collectors
                    $app['config']->set('debugbar.collectors.phpinfo', true);
                    $app['config']->set('debugbar.collectors.messages', true);
                    $app['config']->set('debugbar.collectors.time', true);
                    $app['config']->set('debugbar.collectors.memory', true);
                    $app['config']->set('debugbar.collectors.exceptions', true);
                    $app['config']->set('debugbar.collectors.logs', true);
                    $app['config']->set('debugbar.collectors.db', true);
                    $app['config']->set('debugbar.collectors.views', true);
                    $app['config']->set('debugbar.collectors.route', true);
                    $app['config']->set('debugbar.collectors.queries', true);
                    $app['config']->set('debugbar.collectors.cache', true);

                    Debug::log("Debugbar registered successfully");
                } else {
                    Debug::error('Laravel Debugbar class not found. Please ensure it is installed via composer.');
                }
            } catch (\Exception $e) {
                Debug::error('Error registering Debugbar: ' . $e->getMessage());
            }
        }
    }

    /**
     * Run a component task through Laravel
     *
     * @param string $componentName
     * @param string $route
     * @param array $params
     * @return Response
     */
    public function runTask($componentName, $route, $params = [])
    {
        try {
            Debug::log("Running task for component: {$componentName}, route: {$route}", $params);
            Debug::startMeasure('runTask');

            // Ensure component is registered
            $this->registerComponent($componentName);

            // Get the Laravel application instance
            $app = $this->manager->getInstance($componentName);

            // Load routes from component if not already loaded
            if (!isset($this->routeCache[$componentName])) {
                $this->loadRoutes($app, $componentName);
            }

            // Create a request for the route
            $request = $this->createRequest($route, $params);

            // Process the request through Laravel
            $kernel = $app->make('Illuminate\Contracts\Http\Kernel');

            // Register Debugbar middleware if it's enabled
            if ($this->debugbarEnabled) {
                // Make sure debugbar assets are available
                if (class_exists('\Jaravel\Commands\CopyDebugbarAssets')) {
                    \Jaravel\Commands\CopyDebugbarAssets::execute();
                }

                // Push middleware to kernel
                $kernel->pushMiddleware(\Jaravel\Middleware\DebugbarMiddleware::class);
            }

            Debug::startMeasure('handleRequest');
            $response = $kernel->handle($request);
            Debug::stopMeasure('handleRequest');

            // Log cached routes if debugging is enabled and not already logged
            if (Debug::isEnabled() && $this->routeDebuggingEnabled && isset($this->routeCache[$componentName])) {
                Debug::log("Registered routes", $this->routeCache[$componentName]);
            }

            // Terminate the application
            $kernel->terminate($request, $response);

            Debug::stopMeasure('runTask');

            return $response;

        } catch (\Exception $e) {
            Debug::captureException($e);

            // Return a response with error details
            $content = 'Jaravel Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine();

            // Add debug info to error response if enabled
            if (Debug::isEnabled()) {
                $content .= Debug::render();
            }

            return new Response($content, 500);
        }
    }


    /**
     * Cache routes for a component
     *
     * @param string $componentName
     * @return void
     */
    protected function cacheRoutes($componentName)
    {
        try {
            // Get the Laravel application instance
            $app = $this->manager->getInstance($componentName);

            // Get routes collection
            $routes = $app['router']->getRoutes();

            if (count($routes) === 0) {
                Debug::log("No routes found for component: {$componentName}");
                return;
            }

            // Format routes for debugging
            $formattedRoutes = [];
            foreach ($routes->getRoutes() as $route) {
                $formattedRoutes[] = [
                    'methods' => $route->methods(),
                    'uri' => $route->uri(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => $route->gatherMiddleware()
                ];
            }

            // Store in cache
            $this->routeCache[$componentName] = $formattedRoutes;

            // Log for debug output
            Debug::log("Routes registered for component: {$componentName}", $formattedRoutes);

        } catch (\Exception $e) {
            Debug::error("Error caching routes: " . $e->getMessage());
        }
    }

    /**
     * Load routes from component
     *
     * @param \Illuminate\Foundation\Application $app
     * @param string $componentName
     * @return void
     */
    protected function loadRoutes($app, $componentName)
    {
        // Determine if this is an admin component
        $isAdmin = strpos(\Joomla\CMS\Uri\Uri::current(), '/administrator/') !== false;

        // Set correct path for routes file
        $basePath = $isAdmin ? JPATH_ADMINISTRATOR : JPATH_SITE;
        $routesPath = $basePath . '/components/' . $componentName . '/routes/web.php';

        Debug::log("Loading routes from: {$routesPath}, exists: " . (file_exists($routesPath) ? 'yes' : 'no'));

        // Create a fallback route if routes file doesn't exist
        if (!file_exists($routesPath)) {
            Debug::log("Routes file not found, creating fallback route");
            $app['router']->get('/{any?}', function($any = null) {
                return new Response("Route not found: " . ($any ?: 'home'));
            })->where('any', '.*');
            return;
        }

        // Make sure Facade root is set before loading routes
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        // Load the routes file in a separate scope to avoid variable conflicts
        Debug::startMeasure('loadRoutesFromFile');
        $router = $app['router'];
        $router->group(['middleware' => 'web'], function() use ($router, $routesPath) {
            require $routesPath;
        });
        Debug::stopMeasure('loadRoutesFromFile');

        // Add a fallback route
        $app['router']->fallback(function() {
            return new Response("Route not found", 404);
        });
    }

    /**
     * Create a request object
     *
     * @param string $route
     * @param array $params
     * @return \Illuminate\Http\Request
     */
    protected function createRequest($route, $params = [])
    {
        // Make sure route starts with /
        $path = '/' . ltrim($route, '/');

        // Add query parameters if any
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }

        // Create a server array
        $server = $_SERVER;
        $server['REQUEST_URI'] = $path;
        $server['SCRIPT_NAME'] = '';
        $server['PATH_INFO'] = $path;

        // Get request method from Joomla
        $method = \Joomla\CMS\Factory::getApplication()->input->getMethod() ?: 'GET';

        Debug::log("Creating request: {$method} {$path}");

        // Create the request
        return Request::create(
            $path,
            $method,
            $params,
            $_COOKIE,
            $_FILES,
            $server
        );
    }
}