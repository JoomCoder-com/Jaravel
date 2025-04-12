<?php
namespace Jaravel\Support;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;

/**
 * Bootstrap class for Jaravel
 * Handles core initialization and facade setup
 */
class Bootstrap
{
    /**
     * @var bool Whether the bootstrap has been initialized
     */
    protected static $initialized = false;

    /**
     * Initialize the Jaravel framework
     *
     * @return void
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }

        // Set up default facade application
        self::setupDefaultFacadeRoot();

        // Register global aliases
        self::registerGlobalAliases();

        self::$initialized = true;
    }

    /**
     * Setup a default facade root to prevent "A facade root has not been set" errors
     * This will be replaced when a real application instance is available
     *
     * @return void
     */
    protected static function setupDefaultFacadeRoot()
    {
        // Create a default container if none exists
        if (!Facade::getFacadeApplication()) {
            $container = new \Illuminate\Container\Container();
            Facade::setFacadeApplication($container);
        }
    }

    /**
     * Register global aliases for commonly used classes
     *
     * @return void
     */
    protected static function registerGlobalAliases()
    {
        $aliases = [
            'App' => \Illuminate\Support\Facades\App::class,
            'Arr' => \Illuminate\Support\Arr::class,
            'Auth' => \Illuminate\Support\Facades\Auth::class,
            'Blade' => \Illuminate\Support\Facades\Blade::class,
            'Cache' => \Illuminate\Support\Facades\Cache::class,
            'Config' => \Illuminate\Support\Facades\Config::class,
            'Cookie' => \Illuminate\Support\Facades\Cookie::class,
            'Crypt' => \Illuminate\Support\Facades\Crypt::class,
            'DB' => \Illuminate\Support\Facades\DB::class,
            'Eloquent' => \Illuminate\Database\Eloquent\Model::class,
            'Event' => \Illuminate\Support\Facades\Event::class,
            'File' => \Illuminate\Support\Facades\File::class,
            'Gate' => \Illuminate\Support\Facades\Gate::class,
            'Hash' => \Illuminate\Support\Facades\Hash::class,
            'Http' => \Illuminate\Support\Facades\Http::class,
            'Lang' => \Illuminate\Support\Facades\Lang::class,
            'Log' => \Illuminate\Support\Facades\Log::class,
            'Mail' => \Illuminate\Support\Facades\Mail::class,
            'Queue' => \Illuminate\Support\Facades\Queue::class,
            'Redirect' => \Illuminate\Support\Facades\Redirect::class,
            'Request' => \Illuminate\Support\Facades\Request::class,
            'Response' => \Illuminate\Support\Facades\Response::class,
            'Route' => \Illuminate\Support\Facades\Route::class,
            'Schema' => \Illuminate\Support\Facades\Schema::class,
            'Session' => \Illuminate\Support\Facades\Session::class,
            'Storage' => \Illuminate\Support\Facades\Storage::class,
            'Str' => \Illuminate\Support\Str::class,
            'URL' => \Illuminate\Support\Facades\URL::class,
            'Validator' => \Illuminate\Support\Facades\Validator::class,
            'View' => \Illuminate\Support\Facades\View::class,

            // Jaravel specific aliases
            'Jaravel' => \Jaravel\Facades\Jaravel::class,
            'JaravelDebug' => \Jaravel\Facades\Debug::class,
        ];

        // Set up aliases in Laravel's AliasLoader
        $loader = AliasLoader::getInstance();

        foreach ($aliases as $alias => $class) {
            $loader->alias($alias, $class);
        }
    }
}