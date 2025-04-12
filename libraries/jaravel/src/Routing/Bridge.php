<?php
namespace Jaravel\Routing;

use Illuminate\Http\Request;
use Joomla\CMS\Factory;

/**
 * Bridge between Joomla's routing system and Laravel's routing
 */
class Bridge
{
    /**
     * Create a Laravel request from a Joomla request
     *
     * @param string $route The Laravel route (optional)
     * @return \Illuminate\Http\Request
     */
    public function createRequest($route = null)
    {
        $joomlaApp = Factory::getApplication();
        $joomlaInput = $joomlaApp->input;

        // Get the route from input if not provided
        if ($route === null) {
            $route = $joomlaInput->getString('route', '/');
        }

        // Ensure route starts with a slash
        if (substr($route, 0, 1) !== '/') {
            $route = '/' . $route;
        }

        // Create server variables
        $server = $_SERVER;

        // Override some server variables to match the expected route
        $server['REQUEST_URI'] = $route;
        $server['SCRIPT_NAME'] = '';
        $server['PATH_INFO'] = $route;

        // Set HTTP method
        $method = $joomlaInput->getMethod();

        // Get query parameters (excluding Joomla-specific ones)
        $query = $joomlaInput->getArray();
        unset($query['option'], $query['Itemid'], $query['route']);

        // Create Laravel request
        $request = Request::create(
            $route,
            $method,
            $query,
            $_COOKIE,
            $_FILES,
            $server
        );

        return $request;
    }
}