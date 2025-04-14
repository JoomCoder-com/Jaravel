<?php
namespace Jaravel;

use Jaravel\Instance\Manager;
use Jaravel\Support\Debug;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;
use Dotenv\Dotenv;
use Throwable;

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
     * @var array Cached routes by component
     */
    protected $routeCache = [];

    /**
     * @var bool Whether Laravel Debugbar should be used instead of Jaravel debug
     */
    protected $useDebugbar = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->manager = new Manager();
    }

    /**
     * Load environment variables from .env file for a component
     * 
     * @param string $componentName
     * @return bool Whether .env was loaded successfully
     */
    public function loadEnvironment($componentName)
    {
        // Determine component base path
        $isAdmin = strpos(\Joomla\CMS\Uri\Uri::current(), '/administrator/') !== false;
        $basePath = ($isAdmin ? JPATH_ADMINISTRATOR : JPATH_SITE) . '/components/' . $componentName;
        
        // Check if .env file exists
        if (file_exists($basePath . '/.env')) {
            try {
                $dotenv = Dotenv::createImmutable($basePath);
                $dotenv->load();
                
                // Check if we should use Laravel Debugbar
                $this->useDebugbar = filter_var($_ENV['DEBUGBAR_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
                
                // Set debug mode based on .env
                $appDebug = filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $this->enableDebug($appDebug);
                
                Debug::log("Loaded environment from .env file for {$componentName}");
                return true;
            } catch (\Exception $e) {
                // Failed to load .env, fallback to default settings
                Debug::error("Failed to load .env: " . $e->getMessage());
                return false;
            } catch (Throwable $e) {
                Debug::error("Throwable caught loading .env: " . $e->getMessage());
                return false;
            }
        }
        
        return false;
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

        if ($enabled) { // Just re-register if enabled, Debug class should handle duplicates if needed
            Debug::registerErrorHandlers();
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
        // Prevent re-registration
        if (isset(self::$registeredComponents[$componentName])) {
            return;
        }
        self::$registeredComponents[$componentName] = true; // Mark as registered early

        Debug::log("Registering component: {$componentName}");
        
        // Try to load environment variables from .env file
        $envLoaded = $this->loadEnvironment($componentName);
        Debug::log("Environment loaded: " . ($envLoaded ? 'true' : 'false'));
        Debug::log("Debugbar should be used: " . ($this->useDebugbar ? 'true' : 'false'));
        
        // Explicitly check for debugbar class
        $debugbarExists = class_exists('\Barryvdh\Debugbar\ServiceProvider');
        Debug::log("Debugbar ServiceProvider class exists: " . ($debugbarExists ? 'true' : 'false'));

        // Initialize the application instance - this will also setup the Facade root
        // This now includes Kernel/Handler registration and middleware push if enabled
        $app = $this->manager->getInstance($componentName);
        
        // Register Laravel Debugbar service provider if needed
        if ($this->useDebugbar && $debugbarExists) {
            try {
                // Only register if not already registered by Laravel's auto-discovery or previous run
                if (!$app->bound('debugbar') && !$app->resolved('debugbar')) {
                    Debug::log("Registering Debugbar service provider");

                    // Set minimal config necessary BEFORE registering
                    $config = $app->make('config');
                    if (!$config->has('debugbar')) {
                        $config->set('debugbar', [
                            'enabled' => true,
                            'inject' => true, // Let middleware handle injection if possible
                            'options' => [
                                'render' => [
                                    'position' => 'bottom',
                                    'force_render' => true, // Keep forcing render for manual injection fallback
                                ],
                            ],
                        ]);
                        Debug::log("Set minimal debugbar config");
                    } else {
                        // Ensure it's enabled if config already exists
                        $config->set('debugbar.enabled', true);
                        $config->set('debugbar.inject', true);
                        Debug::log("Ensured debugbar enabled in existing config");
                    }

                    // Register provider
                    $app->register('\Barryvdh\Debugbar\ServiceProvider');

                    // Alias facade
                    if (!class_exists('Debugbar')) {
                        $app->alias('Debugbar', '\Barryvdh\Debugbar\Facades\Debugbar');
                    }

                    Debug::log("Debugbar service provider registered");

                } else {
                    Debug::log("Debugbar service already bound/resolved, ensuring enabled state.");
                    // Ensure it's enabled if already bound
                    $app->make('config')->set('debugbar.enabled', true);
                    $app->make('config')->set('debugbar.inject', true);
                    if ($app->bound('debugbar')) {
                        $app->make('debugbar')->enable(); // Explicitly enable instance too
                         Debug::log("Explicitly enabled existing debugbar instance.");
                    }
                }
                
                 // Add a test message AFTER registration and potential boot
                 // This check should happen regardless of whether we registered it now or it existed
                 if ($app->bound('debugbar')) {
                     try {
                         $debugbar = $app->make('debugbar');
                         $debugbar->info('Debugbar active for component: ' . $componentName);
                         $finalConfig = $app->make('config')->get('debugbar');
                         Debug::log("Final Debugbar Config: ", $finalConfig);
                         Debug::log("Final App Debug Config: " . $app->make('config')->get('app.debug'));
                     } catch (\Exception $e) {
                         Debug::log("Could not add test message or log config: " . $e->getMessage());
                     } catch (Throwable $e) {
                         Debug::log("Throwable caught adding test message: " . $e->getMessage());
                     }
                 } else {
                      Debug::log("Debugbar service not bound after registration attempt.");
                 }

            } catch (\Exception $e) {
                Debug::error("Error during debugbar registration logic: " . $e->getMessage(), $e->getTraceAsString());
            } catch (Throwable $e) {
                Debug::error("Throwable during debugbar registration logic: " . $e->getMessage(), $e->getTraceAsString());
            }
        } else {
             Debug::log("Skipping Debugbar registration (useDebugbar=" . ($this->useDebugbar?'true':'false') . ", classExists=" . ($debugbarExists?'true':'false') . ")");
        }
        
        // Cache routes if route debugging is enabled
        if ($this->routeDebuggingEnabled) {
            Debug::log("Pre-loading routes for component: {$componentName}");
            $this->loadRoutes($app, $componentName); // Keep route loading simple now
            $this->cacheRoutes($componentName);
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
                    'action' => $route->getActionName()
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
     * Run a component task through Laravel
     *
     * @param string $componentName
     * @param string $route
     * @param array $params
     * @return Response
     */
    public function runTask($componentName, $route, $params = [])
    {
        $response = null;
        try {
            Debug::log("Starting runTask for component: {$componentName}, route: {$route}", $params);

            // Ensure component is registered (this includes env loading, app init, debugbar registration)
            $this->registerComponent($componentName);

            // Get the Laravel application instance
            $app = $this->manager->getInstance($componentName);

            // Create a request for the route
            $request = $this->createRequest($route, $params);
            $app->instance('request', $request); // Ensure current request is bound

            // DIRECT DEBUGBAR SETUP - do this before kernel runs
            if ($this->useDebugbar && class_exists('\Barryvdh\Debugbar\Facades\Debugbar')) {
                Debug::log("Using direct debugbar approach");
                try {
                    // Make sure config is set
                    $config = $app->make('config');
                    $config->set('debugbar.enabled', true);
                    
                    // Register if not registered
                    if (!$app->bound('debugbar')) {
                        $app->register('\Barryvdh\Debugbar\ServiceProvider');
                        $app->alias('Debugbar', '\Barryvdh\Debugbar\Facades\Debugbar');
                        Debug::log("Directly registered debugbar service provider");
                    }
                    
                    // Force enable and start measuring
                    $debugbar = $app->make('debugbar');
                    $debugbar->enable();
                    $debugbar->startMeasure('jaravel-task', 'Jaravel Task Execution');
                    
                    // Add a message to confirm debugbar is working
                    $debugbar->info('Debugbar manually activated for ' . $componentName);
                    Debug::log("Debugbar manually enabled and started measuring");
                    
                    // Check renderer paths
                    $renderer = $debugbar->getJavascriptRenderer();
                    $basePath = $renderer->getBaseUrl();
                    $baseDir = $renderer->getBasePath();
                    Debug::log("Debugbar assets path: $basePath (dir: $baseDir)");
                    
                } catch (\Throwable $e) {
                    Debug::error("Error in direct debugbar setup: " . $e->getMessage());
                }
            }

            // Load routes from component if not already loaded
            if (!isset($this->routeCache[$componentName])) {
                $this->loadRoutes($app, $componentName);
            }

            // Process the request through Laravel kernel
            Debug::log("Handling request with Kernel");
            $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
            $response = $kernel->handle($request); // Middleware should run here
            Debug::log("Request handled, Response type: " . get_class($response) . ", Status: " . $response->getStatusCode());

            // Log cached routes if debugging is enabled and not already logged
            if (Debug::isEnabled() && $this->routeDebuggingEnabled && isset($this->routeCache[$componentName])) {
                Debug::log("Registered routes", $this->routeCache[$componentName]);
            }

            // Stop debugbar measuring if we started it
            if ($this->useDebugbar && $app->bound('debugbar')) {
                try {
                    $debugbar = $app->make('debugbar');
                    $debugbar->stopMeasure('jaravel-task');
                    Debug::log("Stopped debugbar measuring");
                } catch (\Throwable $e) {
                    Debug::error("Error stopping debugbar measuring: " . $e->getMessage());
                }
            }

            // ALWAYS try manual injection for debugbar
            if ($this->useDebugbar && $app->bound('debugbar') && $response instanceof Response) {
                $content = $response->getContent();
                $isHtml = stripos($content, '</html>') !== false;
                
                if ($isHtml) {
                    Debug::log("HTML detected, manually injecting debugbar");
                    try {
                        $debugbar = $app->make('debugbar');
                        
                        // Force collection
                        if (method_exists($debugbar, 'collect')) {
                            $debugbar->collect();
                            Debug::log("Collected debugbar data");
                        }
                        
                        // Get rendered content
                        $renderer = $debugbar->getJavascriptRenderer();
                        $head = $renderer->renderHead();
                        $body = $renderer->render();
                        Debug::log("Debugbar head length: " . strlen($head) . ", body length: " . strlen($body));
                        
                        // Add a detection script to console log if PhpDebugBar exists
                        $script = '<script>console.log("PhpDebugBar object exists: " + (typeof PhpDebugBar !== "undefined"))</script>';
                        
                        // Insert head, body and script
                        if (strlen($head) > 0 && strlen($body) > 0) {
                            $content = preg_replace('/<\/head>/i', $head . $script . "</head>", $content, 1);
                            $content = preg_replace('/<\/body>/i', $body . "</body>", $content, 1);
                            $response->setContent($content);
                            Debug::log("Debugbar manually injected");
                        } else {
                            Debug::log("Debugbar rendered empty content");
                        }
                    } catch (\Throwable $e) {
                        Debug::error("Error in manual debugbar injection: " . $e->getMessage());
                    }
                } else {
                    Debug::log("Response is not HTML, skipping debugbar injection");
                }
            }
            
            // Add Jaravel debug output if enabled and not using Debugbar
            if (Debug::isEnabled() && !$this->useDebugbar && $response instanceof Response) {
                $content = $response->getContent();
                $debugOutput = Debug::render();
                
                // Try to insert debug output before closing body tag if content appears to be HTML
                if (strpos($content, '</body>') !== false) {
                    $content = str_replace('</body>', $debugOutput . '</body>', $content);
                } else {
                    // Fallback: append to the end if no closing body tag is found
                    $content .= $debugOutput;
                }
                
                $response->setContent($content);
                Debug::log("Added Jaravel built-in debug output.");
            }

            // Terminate the application
            Debug::log("Terminating kernel");
            // Ensure $request and $response are valid before terminating
            if (isset($request) && $response instanceof Response) {
                $kernel->terminate($request, $response);
            } else {
                Debug::log("Skipping kernel terminate (invalid request/response?)");
            }

            return $response;

        } catch (\Exception $e) {
             Debug::captureException($e);
             if (Debug::isEnabled()) {
                 return new Response(Debug::renderException($e), 500);
             } else {
                 return new Response('Server Error', 500);
             }
        } catch (Throwable $e) { // Catch Throwable
            Debug::captureException($e);
             if (Debug::isEnabled()) {
                 return new Response(Debug::renderException($e), 500);
             } else {
                 return new Response('Server Error', 500);
             }
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

        $basePath = $isAdmin ? JPATH_ADMINISTRATOR : JPATH_SITE;
        $routesPath = $basePath . '/components/' . $componentName . '/routes/web.php';

        Debug::log("Loading routes from: {$routesPath}, exists: " . (file_exists($routesPath) ? 'yes' : 'no'));

        if (!file_exists($routesPath)) {
            // ... (fallback route handling) ...
             Debug::log("Routes file not found, creating fallback route");
             $app['router']->get('/{any?}', function($any = null) {
                 return new Response("Route not found: " . ($any ?: 'home'));
             })->where('any', '.*');
            return;
        }

        // Facade root setup
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        // Load routes within the 'web' middleware group
        // Debugbar middleware should be applied globally by the Kernel
        $router = $app['router'];
         $router->group(['middleware' => 'web'], function () use ($router, $routesPath) {
            require $routesPath;
        });
         Debug::log("Loaded routes using 'web' middleware group.");

        // Add fallback route
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