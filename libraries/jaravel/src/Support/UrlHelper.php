<?php
// libraries/jaravel/src/Support/UrlHelper.php

namespace Jaravel\Support;

use Illuminate\Container\Container;
use Jaravel\Routing\Bridge;

/**
 * Helper class for generating Joomla URLs for Laravel routes
 */
class UrlHelper
{
    /**
     * Generate a URL for a Laravel route within Joomla
     *
     * @param string $route The Laravel route path
     * @param array $params Additional query parameters
     * @param bool $sef Whether to generate a SEF URL
     * @return string The Joomla URL
     */
    public static function route($route, $params = [], $sef = true)
    {
        // Get the component name from the container
        $app = Container::getInstance();
        $componentName = $app->make('jaravel.component_id');

        // Create a new bridge instance
        $bridge = new Bridge();

        // Generate the URL
        if ($sef) {
            return $bridge->generateSefUrl($componentName, $route, $params);
        } else {
            return $bridge->generateUrl($componentName, $route, $params);
        }
    }

    /**
     * Generate a URL for a named Laravel route within Joomla
     *
     * @param string $name The route name
     * @param array $parameters Route parameters
     * @param array $query Additional query parameters
     * @param bool $sef Whether to generate a SEF URL
     * @return string The Joomla URL
     */
    public static function namedRoute($name, $parameters = [], $query = [], $sef = true)
    {
        // Get the router from the container
        $app = Container::getInstance();
        $router = $app->make('router');

        // Get the route by name
        $route = $router->getRoutes()->getByName($name);

        if (!$route) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        // Get the route path
        $uri = $route->uri();

        // Replace route parameters
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
            $uri = str_replace('{' . $key . '?}', $value, $uri);
        }

        // Remove any remaining route parameters
        $uri = preg_replace('/{[^}]*\?}/', '', $uri);

        // Generate the full URL
        return self::route($uri, $query, $sef);
    }

    /**
     * Create a back URL that preserves the referrer within the Joomla context
     *
     * @return string
     */
    public static function back()
    {
        // Try to get the referrer
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // Check if the referrer is from the same Joomla site
        if (empty($referrer) || strpos($referrer, \Joomla\CMS\Uri\Uri::base()) === false) {
            // Default to the component's home route
            return self::route('/');
        }

        return $referrer;
    }

    /**
     * Generate a URL for an asset in the component's media directory
     *
     * @param string $path Path to the asset relative to the component's media directory
     * @return string
     */
    public static function asset($path)
    {
        // Get the component name from the container
        $app = Container::getInstance();
        $componentName = $app->make('jaravel.component_id');

        // Generate the URL to the asset
        return \Joomla\CMS\Uri\Uri::base(true) . '/media/' . $componentName . '/' . ltrim($path, '/');
    }
}