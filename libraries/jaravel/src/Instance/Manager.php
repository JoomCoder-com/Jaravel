<?php
namespace Jaravel\Instance;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Joomla\CMS\Factory;

class Manager
{
    /**
     * @var array Registered Laravel instances
     */
    protected $instances = [];

    /**
     * @var array Shared resources paths by component
     */
    protected $sharedResourcesPaths = [];

    /**
     * Set a shared resources path for a component
     *
     * @param string $componentName
     * @param string $path
     * @return void
     */
    public function setSharedResourcesPath($componentName, $path)
    {
        $this->sharedResourcesPaths[$componentName] = $path;
    }

    /**
     * Get the shared resources path for a component
     *
     * @param string $componentName
     * @return string|null
     */
    public function getSharedResourcesPath($componentName)
    {
        return $this->sharedResourcesPaths[$componentName] ?? null;
    }

    /**
     * Get Laravel application instance for a component
     *
     * @param string $componentName Component name (e.g., com_myapp)
     * @return Application
     */
    public function getInstance($componentName)
    {
        // If instance already exists, return it
        if (isset($this->instances[$componentName])) {
            $app = $this->instances[$componentName];
            // Ensure facade root is set
            $this->setupFacadeRoot($app);
            return $app;
        }

        // Create new Laravel application instance
        $instance = $this->bootstrapNewInstance($componentName);

        // Setup facade root
        $this->setupFacadeRoot($instance);

        // Store instance for future use
        $this->instances[$componentName] = $instance;

        return $instance;
    }

    /**
     * Setup Facade root application
     *
     * @param Application $app
     * @return void
     */
    protected function setupFacadeRoot($app)
    {
        // Set the facade application for the current request
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }

    /**
     * Create a new Laravel application instance with basic bootstrapping
     *
     * @param string $componentName Component name
     * @return Application
     */
    protected function bootstrapNewInstance($componentName)
    {
        // Define base path for this component
        $isAdmin = strpos(\Joomla\CMS\Uri\Uri::current(), '/administrator/') !== false;
        $basePath = ($isAdmin ? JPATH_ADMINISTRATOR : JPATH_SITE) . '/components/' . $componentName;

        // Ensure bootstrap/cache directory exists and is writable
        $this->ensureBootstrapCacheDirectory($basePath);

        // Create application with base path
        $app = new Application($basePath);

        // Set up primary required services
        $this->registerBasicServices($app, $componentName, $basePath);

        // Register component namespace
        $namespace = $this->registerNamespace($app, $componentName);

        // Register HTTP kernel and Exception Handler
        $this->registerKernelAndHandler($app);

        // Configure database
        $this->configureDatabase($app);

        return $app;
    }

    /**
     * Ensure bootstrap/cache directory exists and is writable
     *
     * @param string $basePath
     * @return void
     */
    protected function ensureBootstrapCacheDirectory($basePath)
    {
        $bootstrapPath = $basePath . '/bootstrap';
        $cachePath = $bootstrapPath . '/cache';

        // Create bootstrap directory if it doesn't exist
        if (!is_dir($bootstrapPath)) {
            mkdir($bootstrapPath, 0755, true);
        }

        // Create cache directory if it doesn't exist
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        // Ensure the cache directory is writable
        if (!is_writable($cachePath)) {
            chmod($cachePath, 0755);
        }
    }

    /**
     * Register basic required Laravel services
     *
     * @param Application $app
     * @param string $componentName
     * @param string $basePath
     * @return void
     */
    protected function registerBasicServices($app, $componentName, $basePath)
    {
        // Store component ID
        $app->instance('jaravel.component_id', $componentName);

        // Config service
        $app->singleton('config', function() {
            return new \Illuminate\Config\Repository([]);
        });

        // Events service
        $app->singleton('events', function() {
            return new \Illuminate\Events\Dispatcher();
        });

        // File system service
        $app->singleton('files', function() {
            return new \Illuminate\Filesystem\Filesystem();
        });

        // Router service
        $app->singleton('router', function($app) {
            return new \Illuminate\Routing\Router($app['events'], $app);
        });

        // URL Generator service
        $app->singleton('url', function($app) {
            $routes = $app['router']->getRoutes();
            $app->instance('routes', $routes);

            $url = new \Illuminate\Routing\UrlGenerator(
                $routes, \Illuminate\Http\Request::capture()
            );

            return $url;
        });

        // Basic View Factory (without Blade for now)
        $this->registerBasicViewFactory($app, $componentName, $basePath);
    }

    /**
     * Register a basic view factory without Blade
     *
     * @param Application $app
     * @param string $componentName
     * @param string $basePath
     * @return void
     */
    protected function registerBasicViewFactory($app, $componentName, $basePath)
    {
        // Check if we have shared resources for this component
        $sharedPath = $this->getSharedResourcesPath($componentName);

        // View finder service
        $app->singleton('view.finder', function($app) use ($basePath, $sharedPath) {
            $paths = [];

            // Component's own views come first
            $paths[] = $basePath . '/resources/views';
            $paths[] = $basePath . '/views';

            // If there's a shared path, add it last (fallback)
            if ($sharedPath) {
                $paths[] = $sharedPath . '/resources/views';
                $paths[] = $sharedPath . '/views';
            }

            return new \Illuminate\View\FileViewFinder(
                $app['files'], $paths
            );
        });

        // Simple engine resolver with just PHP engine
        $app->singleton('view.engine.resolver', function($app) {
            $resolver = new \Illuminate\View\Engines\EngineResolver;

            // Register PHP engine
            $resolver->register('php', function() use ($app) {
                return new \Illuminate\View\Engines\PhpEngine($app['files']);
            });

            return $resolver;
        });

        // View factory
        $app->singleton('view', function($app) use ($componentName, $basePath, $sharedPath) {
            $factory = new \Illuminate\View\Factory(
                $app['view.engine.resolver'],
                $app['view.finder'],
                $app['events']
            );

            $factory->setContainer($app);

            // Add component namespace
            $factory->addNamespace($componentName, $basePath . '/resources/views');

            // If there's a shared path, add it as a fallback namespace
            if ($sharedPath) {
                $factory->addNamespace($componentName, $sharedPath . '/resources/views');
            }

            return $factory;
        });
    }

    /**
     * Register component namespace
     *
     * @param Application $app
     * @param string $componentName
     * @return string
     */
    protected function registerNamespace($app, $componentName)
    {
        // Generate namespace
        $name = str_replace('com_', '', $componentName);
        $name = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        $namespace = 'Jaravel\\Component\\' . $name;

        $app->instance('jaravel.component_namespace', $namespace);

        // Get shared resources path if available
        $sharedPath = $this->getSharedResourcesPath($componentName);

        // Register component namespace for autoloading
        $componentPath = ($app->runningInConsole() ? JPATH_ADMINISTRATOR : JPATH_SITE) . '/components/' . $componentName;

        // Register a PSR-4 autoloader for the component
        spl_autoload_register(function($class) use ($namespace, $componentPath, $sharedPath) {
            // Check if the class is in our namespace
            if (strpos($class, $namespace . '\\') === 0) {
                // Get the relative class name
                $relativeClass = substr($class, strlen($namespace) + 1);

                // Replace namespace separators with directory separators
                $relativePath = str_replace('\\', '/', $relativeClass) . '.php';

                // First try in the component path
                $file = $componentPath . '/' . $relativePath;
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }

                // If not found and we have a shared path, try there
                if ($sharedPath && file_exists($sharedPath . '/' . $relativePath)) {
                    require_once $sharedPath . '/' . $relativePath;
                    return;
                }
            }
        });

        return $namespace;
    }

    /**
     * Register HTTP Kernel and Exception Handler
     *
     * @param Application $app
     * @return void
     */
    protected function registerKernelAndHandler($app)
    {
        // Create and register HTTP kernel
        $kernelClass = 'JaravelMinimalKernel' . md5(time());
        if (!class_exists($kernelClass)) {
            eval('
                class ' . $kernelClass . ' extends \Illuminate\Foundation\Http\Kernel
                {
                    protected $middleware = [];
                    protected $middlewareGroups = ["web" => []];
                    protected $routeMiddleware = [];
                }
            ');
        }
        $kernel = new $kernelClass($app, $app['router']);
        $app->instance(\Illuminate\Contracts\Http\Kernel::class, $kernel);

        // Create and register exception handler
        $handlerClass = 'JaravelMinimalHandler' . md5(time());
        if (!class_exists($handlerClass)) {
            eval('
                class ' . $handlerClass . ' extends \Illuminate\Foundation\Exceptions\Handler
                {
                    protected $dontReport = [];
                    
                    public function render($request, \Throwable $e)
                    {
                        return new \Illuminate\Http\Response(
                            "Error: " . $e->getMessage(), 500
                        );
                    }
                }
            ');
        }
        $handler = new $handlerClass($app);
        $app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class, $handler);
    }

    /**
     * Configure database connection to use Joomla's connection
     *
     * @param Application $app
     * @return void
     */
    protected function configureDatabase($app)
    {
        try {
            // Get Joomla configuration for database settings
            $jConfig = Factory::getConfig();
            $joomlaDb = Factory::getDbo();

            // Configure database settings
            $app['config']->set('database.default', 'joomla');
            $app['config']->set('database.connections.joomla', [
                'driver' => 'mysql',
                'host' => $jConfig->get('host'),
                'database' => $jConfig->get('db'),
                'username' => $jConfig->get('user'),
                'password' => $jConfig->get('password'),
                'prefix' => $joomlaDb->getPrefix(),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
        } catch (\Exception $e) {
            // Ignore database errors for now
        }
    }
}