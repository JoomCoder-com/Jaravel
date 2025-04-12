<?php
// libraries/jaravel/src/Facades/Jaravel.php

namespace Jaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Jaravel\Support\UrlHelper;

/**
 * Facade for Jaravel functionality
 *
 * @method static string route(string $route, array $params = [], bool $sef = true)
 * @method static string namedRoute(string $name, array $parameters = [], array $query = [], bool $sef = true)
 * @method static string back()
 * @method static string asset(string $path)
 */
class Jaravel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'jaravel';
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        // Check if the method exists in UrlHelper
        if (method_exists(UrlHelper::class, $method)) {
            return UrlHelper::$method(...$args);
        }

        return parent::__callStatic($method, $args);
    }
}