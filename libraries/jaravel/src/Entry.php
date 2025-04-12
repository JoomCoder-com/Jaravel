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
     * Constructor
     */
    public function __construct()
    {
        $this->manager = new Manager();
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

        // Register the component
        self::$registeredComponents[] = $componentName;
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

            // Ensure component is registered
            $this->registerComponent($componentName);

            // Get the Laravel application instance
            $app = $this->manager->getInstance($componentName);

            // Load routes from component
            $this->loadRoutes($app, $componentName);

            // Create a request for the route
            $request = $this->createRequest($route, $params);

            // Process the request through Laravel
            $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
            $response = $kernel->handle($request);

            // Log routes if debugging is enabled
            if (Debug::isEnabled()) {
                Debug::log("Registered routes", Debug::getRoutes($componentName));
            }

            // Terminate the application
            $kernel->terminate($request, $response);

            // Add debug output if enabled
            if (Debug::isEnabled() && $response instanceof Response) {
                $content = $response->getContent();
                $debugOutput = Debug::render();
                $response->setContent($content . $debugOutput);
            }

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
        $router = $app['router'];
        $router->group(['middleware' => 'web'], function() use ($router, $routesPath) {
            require $routesPath;
        });

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