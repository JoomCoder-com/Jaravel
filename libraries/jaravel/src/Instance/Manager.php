<?php
namespace Jaravel\Instance;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Joomla\CMS\Factory;
use Dotenv\Dotenv;
// Ensure we have Http Kernel contract
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
// Ensure we have Exception Handler contract
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Jaravel\Support\Debug; // Make sure Debug facade is available
use Throwable; // Import Throwable

class Manager
{
    /**
     * @var array Registered Laravel instances
     */
    protected $instances = [];

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

        // Load environment variables if .env exists
        $this->loadEnvironmentVariables($app, $basePath);

        // Set up primary required services (Config, Events, Files, Router, URL, View)
        $this->registerBasicServices($app, $componentName, $basePath);

        // Register component namespace for autoloading etc.
        $this->registerNamespace($app, $componentName);

        // Register HTTP kernel and Exception Handler **AFTER** basic services and env
        $this->registerKernelAndHandler($app);

        // Configure database connection using Joomla's details
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
            @mkdir($bootstrapPath, 0755, true);
        }

        // Create cache directory if it doesn't exist
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0755, true);
        }

        // Ensure the cache directory is writable
        if (is_dir($cachePath) && !is_writable($cachePath)) {
             @chmod($cachePath, 0755);
        }
    }

     /**
     * Load environment variables from .env file
     *
     * @param Application $app
     * @param string $basePath
     * @return void
     */
    protected function loadEnvironmentVariables($app, $basePath)
    {
        // If .env file exists, load it
        if (file_exists($basePath . '/.env')) {
            try {
                $dotenv = Dotenv::createImmutable($basePath);
                $dotenv->load();
                 Debug::log(".env loaded for {$basePath}");

            } catch (\Exception $e) {
                // Silent fail if .env cannot be loaded
                Debug::error("Failed to load .env file at {$basePath}: " . $e->getMessage());
            }
        } else {
             Debug::log(".env file not found at {$basePath}");
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

        // Config service (Initialize empty, loadEnvironmentVariables will populate later if needed)
        $app->singleton('config', function() {
            return new \Illuminate\Config\Repository([]);
        });
        Debug::log("Registered Config service");

        // Events service
        $app->singleton('events', function() {
            return new \Illuminate\Events\Dispatcher();
        });
        Debug::log("Registered Events service");

        // File system service
        $app->singleton('files', function() {
            return new \Illuminate\Filesystem\Filesystem();
        });
        Debug::log("Registered Files service");

        // Request service (capture current request)
         $app->singleton('request', function() {
            return \Illuminate\Http\Request::capture();
         });
         Debug::log("Registered Request service");

        // Router service (needs request)
        $app->singleton('router', function($app) {
            return new \Illuminate\Routing\Router($app['events'], $app);
        });
         Debug::log("Registered Router service");

        // URL Generator service (depends on router and request)
        $app->singleton('url', function($app) {
            $routes = $app['router']->getRoutes();
             // Ensure routes are available for URL generation
            if(is_null($routes)) {
                 $routes = new \Illuminate\Routing\RouteCollection();
            }
            $app->instance('routes', $routes);

            $url = new \Illuminate\Routing\UrlGenerator(
                $routes, $app->make('request') // Use app's request instance
            );
            return $url;
        });
        Debug::log("Registered URL service");

        // Basic View Factory (without Blade for now)
        $this->registerBasicViewFactory($app, $componentName, $basePath);
        Debug::log("Registered View factory");

        // Configure env variables into config AFTER initial services registered
         $this->applyEnvToConfig($app);

    }

    /**
     * Apply loaded environment variables to the application config
     * Needs to run AFTER config service is bound.
     *
     * @param Application $app
     */
    protected function applyEnvToConfig($app)
    {
        // This uses $_ENV which should be populated by loadEnvironmentVariables
        $config = $app->make('config');

        // Set environment
        $config->set('app.env', $_ENV['APP_ENV'] ?? 'production');
        Debug::log("Set app.env to: " . $config->get('app.env'));

        // Set debug mode
        $appDebug = isset($_ENV['APP_DEBUG']) ? filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) : false;
        $config->set('app.debug', $appDebug);
        Debug::log("Set app.debug to: " . ($appDebug?'true':'false'));

        // Set storage paths if defined in .env
        if (isset($_ENV['STORAGE_PATH'])) {
            $storagePath = $app->basePath($_ENV['STORAGE_PATH']);
            $app->useStoragePath($storagePath);
            Debug::log("Set storage path to: " . $storagePath);

             // Ensure framework dirs exist within storage path
             if (!is_dir($storagePath . '/framework/views')) @mkdir($storagePath . '/framework/views', 0755, true);
             if (!is_dir($storagePath . '/framework/cache')) @mkdir($storagePath . '/framework/cache', 0755, true);
             if (!is_dir($storagePath . '/framework/sessions')) @mkdir($storagePath . '/framework/sessions', 0755, true);
             if (!is_dir($storagePath . '/logs')) @mkdir($storagePath . '/logs', 0755, true); // Ensure logs dir exists too

        } else {
             // Default storage path if not in .env
             $app->useStoragePath($app->basePath('storage'));
             Debug::log("Using default storage path: " . $app->storagePath());
        }

        // Set cached views path
        if (isset($_ENV['VIEW_COMPILED_PATH'])) {
            $compiledPath = $app->basePath($_ENV['VIEW_COMPILED_PATH']);
            $config->set('view.compiled', $compiledPath);
            Debug::log("Set view.compiled path to: " . $compiledPath);
        } else {
            // Default if not set, ensuring it's within storage path
            $config->set('view.compiled', $app->storagePath('framework/views'));
             Debug::log("Using default view.compiled path: " . $config->get('view.compiled'));
        }

        // Set logging path
        if (isset($_ENV['LOG_PATH'])) {
            $logPath = $app->basePath($_ENV['LOG_PATH']);
             // Ensure the config structure exists before setting
             if (!$config->has('logging.channels.single')) {
                 $config->set('logging.channels.single', []);
             }
            $config->set('logging.channels.single.path', $logPath . '/jaravel.log');
            Debug::log("Set logging.channels.single.path to: " . $config->get('logging.channels.single.path'));
        } else {
             // Default log path within storage
             if (!$config->has('logging.channels.single')) {
                 $config->set('logging.channels.single', []);
             }
             $config->set('logging.channels.single.path', $app->storagePath('logs/jaravel.log'));
             Debug::log("Using default log path: " . $config->get('logging.channels.single.path'));
        }
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
        // View finder service
        $app->singleton('view.finder', function($app) use ($basePath) {
            // Ensure views directory exists or provide a sensible default
             $viewPaths = [
                is_dir($basePath . '/resources/views') ? $basePath . '/resources/views' : null,
                is_dir($basePath . '/views') ? $basePath . '/views' : null,
             ];
             // Filter out null paths
             $viewPaths = array_filter($viewPaths);
             // Add a fallback empty dir if none exist? Maybe not necessary.
            if (empty($viewPaths)) {
                 Debug::log("No standard view directories found for component.");
                 // Optionally create a default if needed:
                 // $defaultViewPath = $basePath . '/resources/views';
                 // if (!is_dir($defaultViewPath)) @mkdir($defaultViewPath, 0755, true);
                 // $viewPaths[] = $defaultViewPath;
            }

            return new \Illuminate\View\FileViewFinder(
                $app['files'], $viewPaths
            );
        });

        // Simple engine resolver with just PHP engine
        $app->singleton('view.engine.resolver', function($app) {
            $resolver = new \Illuminate\View\Engines\EngineResolver;

            // Register PHP engine
            $resolver->register('php', function() use ($app) {
                return new \Illuminate\View\Engines\PhpEngine($app['files']);
            });

            // Register Blade engine if Blade compiler is available
            if (class_exists('\\Illuminate\\View\\Compilers\\BladeCompiler')) {
                $resolver->register('blade', function () use ($app) {
                     // Blade requires a cache path
                     $cachePath = $app['config']->get('view.compiled', $app->storagePath('framework/views'));
                     if (!is_dir($cachePath)) {
                          @mkdir($cachePath, 0755, true); // Ensure cache path exists
                     }
                    return new \Illuminate\View\Engines\CompilerEngine(
                        new \Illuminate\View\Compilers\BladeCompiler($app['files'], $cachePath),
                         $app['files']
                    );
                });
                 Debug::log("Registered Blade view engine.");
            } else {
                 Debug::log("Blade compiler not found, Blade engine not registered.");
            }

            return $resolver;
        });

        // View factory
        $app->singleton('view', function($app) use ($componentName, $basePath) {
            $factory = new \Illuminate\View\Factory(
                $app['view.engine.resolver'],
                $app['view.finder'],
                $app['events']
            );

            $factory->setContainer($app);

            // Share application instance with all views
            $factory->share('app', $app);

            // Add component namespace (using the primary view path if available)
            $viewPath = is_dir($basePath . '/resources/views') ? $basePath . '/resources/views' : (is_dir($basePath . '/views') ? $basePath . '/views' : null);
            if ($viewPath) {
                $factory->addNamespace($componentName, $viewPath);
                 Debug::log("Added view namespace '{$componentName}' pointing to '{$viewPath}'");
            }


            return $factory;
        });
    }

    /**
     * Register component namespace
     *
     * @param Application $app
     * @param string $componentName
     * @return string The generated namespace
     */
    protected function registerNamespace($app, $componentName)
    {
        // Generate namespace: JaravelComponent\ComponentName
        $name = str_replace('com_', '', $componentName);
        $name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
        $namespace = 'JaravelComponent\\' . $name;

        $app->instance('jaravel.component_namespace', $namespace);
        Debug::log("Set component namespace to: {$namespace}");

        // Register component's primary 'app' directory for PSR-4 autoloading if it exists
        $componentAppPath = $app->basePath('app'); // Laravel convention
        if (is_dir($componentAppPath)) {
            // Basic PSR-4 autoloader for the component's namespace -> app directory
            spl_autoload_register(function($class) use ($namespace, $componentAppPath) {
                // Check if the class starts with the component's namespace
                if (strpos($class, $namespace . '\\') === 0) {
                    // Remove the namespace prefix
                    $relativeClass = substr($class, strlen($namespace) + 1);
                    // Replace namespace separators with directory separators and add .php
                    $file = $componentAppPath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                    // If the file exists, require it
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            }, true, true); // Prepend this autoloader
             Debug::log("Registered PSR-4 autoloader for {$namespace} -> {$componentAppPath}");
        } else {
             Debug::log("Component 'app' directory not found at {$componentAppPath}, PSR-4 autoloader not registered.");
        }

        return $namespace;
    }

    /**
     * Register HTTP Kernel and Exception Handler using minimal fallbacks
     *
     * @param Application $app
     * @return void
     */
    protected function registerKernelAndHandler($app)
    {
        // Define minimal kernel class
        $kernelClass = 'JaravelMinimalKernel' . md5(uniqid('', true));
        if (!class_exists($kernelClass)) {
            Debug::log("Creating minimal Kernel: {$kernelClass}");
            eval("
                namespace { // Ensure global namespace
                    class {$kernelClass} extends \Illuminate\Foundation\Http\Kernel
                    {
                        protected \$middleware = [];
                        protected \$middlewareGroups = ['web' => []];
                        protected \$routeMiddleware = [];
                        public function __construct(\\Illuminate\\Contracts\\Foundation\\Application \$app, \\Illuminate\\Routing\\Router \$router) {
                            parent::__construct(\$app, \$router);
                        }
                    }
                } // End global namespace
            ");
        }

        // Bind the minimal Kernel class
        $app->singleton(HttpKernelContract::class, $kernelClass);
        Debug::log("Bound HttpKernelContract to {$kernelClass}");

        // Define minimal handler class
        $handlerClass = 'JaravelMinimalHandler' . md5(uniqid('', true));
        if (!class_exists($handlerClass)) {
             Debug::log("Creating minimal Handler: {$handlerClass}");
             eval("
                namespace { // Ensure global namespace
                    class {$handlerClass} extends \Illuminate\Foundation\Exceptions\Handler
                    {
                        protected \$dontReport = [];
                        public function __construct(\\Illuminate\\Contracts\\Container\\Container \$container) {
                             parent::__construct(\$container);
                        }
                        public function render(\$request, \Throwable \$e) {
                            try {
                                \Jaravel\Support\Debug::captureException(\$e);
                                if (\Jaravel\Support\Debug::isEnabled()) {
                                    return new \Illuminate\Http\Response(\Jaravel\Support\Debug::renderException(\$e), 500);
                                }
                            } catch (\\Throwable \$renderException) {
                                return new \Illuminate\Http\Response('Error: ' . \$e->getMessage() . ' (Handler Failed)', 500);
                            }
                            return new \Illuminate\Http\Response('Server Error', 500);
                        }
                        public function report(\Throwable \$e) {
                            try {
                                 \Jaravel\Support\Debug::captureException(\$e);
                            } catch (\\Throwable \$reportException) { /* Ignore */ }
                            // parent::report(\$e); // Avoid potential issues with parent report
                        }
                    }
                } // End global namespace
             ");
        }

        // Bind the minimal Handler class
        $app->singleton(ExceptionHandlerContract::class, $handlerClass);
        Debug::log("Bound ExceptionHandlerContract to {$handlerClass}");

        // Add Debugbar middleware using Reflection AFTER kernel is bound
        if (isset($_ENV['DEBUGBAR_ENABLED']) && filter_var($_ENV['DEBUGBAR_ENABLED'], FILTER_VALIDATE_BOOLEAN)) {
             if (class_exists(\Barryvdh\Debugbar\Middleware\InjectDebugbar::class)) {
                try {
                     // Resolve the kernel instance
                     $kernel = $app->make(HttpKernelContract::class);
                     // Use Reflection to add middleware to the protected property
                    $middlewareProperty = new \ReflectionProperty($kernel, 'middleware');
                    $middlewareProperty->setAccessible(true);
                    $currentMiddleware = $middlewareProperty->getValue($kernel);
                    // Add only if not already present
                    if (!in_array(\Barryvdh\Debugbar\Middleware\InjectDebugbar::class, $currentMiddleware)) {
                        $currentMiddleware[] = \Barryvdh\Debugbar\Middleware\InjectDebugbar::class;
                        $middlewareProperty->setValue($kernel, $currentMiddleware);
                        Debug::log("Added InjectDebugbar middleware to Kernel instance via Reflection.");
                    } else {
                         Debug::log("InjectDebugbar middleware already present in Kernel instance.");
                    }
                } catch (\Throwable $e) {
                     Debug::error("Failed to add InjectDebugbar middleware via Reflection: " . $e->getMessage());
                }
             } else {
                 Debug::log("InjectDebugbar middleware class not found.");
             }
        } else {
            Debug::log("Debugbar middleware not added (DEBUGBAR_ENABLED is false or not set)");
        }
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
            $joomlaDb = Factory::getDbo(); // Get Joomla DB Object to get prefix reliably

            // Configure database settings
            $config = $app->make('config'); // Get config repository

             // Ensure the base 'database' key exists
             if (!$config->has('database')) {
                 $config->set('database', []);
             }
              // Ensure the 'connections' key exists
             if (!$config->has('database.connections')) {
                 $config->set('database.connections', []);
             }

            $config->set('database.default', 'joomla');
            $config->set('database.connections.joomla', [
                'driver' => 'mysql', // Assuming mysql, adjust if needed
                'host' => $jConfig->get('host'),
                'port' => $jConfig->get('port', '3306'), // Add port, default 3306
                'database' => $jConfig->get('db'),
                'username' => $jConfig->get('user'),
                'password' => $jConfig->get('password'),
                'unix_socket' => $jConfig->get('socket', ''), // Add socket support
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => $joomlaDb->getPrefix(),
                'prefix_indexes' => true, // Standard Laravel setting
                'strict' => true, // Standard Laravel setting
                'engine' => null, // Standard Laravel setting
                // 'options' => extension_loaded('pdo_mysql') ? array_filter([ // PDO options if needed
                //     \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                // ]) : [],
            ]);
            Debug::log("Configured 'joomla' database connection using Joomla settings.");

            // If Eloquent is available, bootstrap it
             if (class_exists('\\Illuminate\\Database\\Eloquent\\Model')) {
                 // Ensure DB service provider is registered if needed (usually automatic)
                 if (!$app->bound('db')) {
                    if (class_exists('\\Illuminate\\Database\\DatabaseServiceProvider')) {
                        $app->register('\\Illuminate\\Database\\DatabaseServiceProvider');
                        Debug::log("Registered DatabaseServiceProvider.");
                    }
                 }
                 // Resolve connection factory and set it up for Eloquent
                 $app->make('db');
                 \Illuminate\Database\Eloquent\Model::setConnectionResolver($app['db']);
                 if ($app->bound('events')) { // Check if events dispatcher is bound
                    \Illuminate\Database\Eloquent\Model::setEventDispatcher($app['events']);
                 }
                  Debug::log("Bootstrapped Eloquent Models.");
             }


        } catch (\Exception $e) {
            Debug::error("Error configuring database: " . $e->getMessage());
        } catch (Throwable $e) { // Catch Throwable for PHP 7+ errors
             Debug::error("Throwable caught during database configuration: " . $e->getMessage());
        }
    }
}