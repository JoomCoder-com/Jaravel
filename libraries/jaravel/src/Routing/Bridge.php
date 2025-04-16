<?php
namespace Jaravel\Routing;

use Illuminate\Http\Request;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Router;

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

    /**
     * Generate a Joomla URL for a Laravel route
     *
     * @param string $componentName Component name (e.g., com_myapp)
     * @param string $route Laravel route
     * @param array $params Additional query parameters
     * @return string
     */
    public function generateUrl($componentName, $route, $params = [])
    {
        // Make sure route doesn't start with a slash for the URL
        $routePath = ltrim($route, '/');

        // Create query parameters
        $query = array_merge([
            'option' => $componentName,
            'route' => $routePath
        ], $params);

        // Build URL
        $uri = new Uri();
        $uri->setPath('index.php');
        $uri->setQuery($query);

        return $uri->toString();
    }

    /**
     * Generate a Joomla SEF URL for a Laravel route
     *
     * @param string $componentName Component name (e.g., com_myapp)
     * @param string $route Laravel route
     * @param array $params Additional query parameters
     * @return string
     */
    public function generateSefUrl($componentName, $route, $params = [])
    {
        // First, build the non-SEF URL
        $nonSefUrl = $this->generateUrl($componentName, $route, $params);

        // Let Joomla's router build the SEF URL
        $router = Factory::getApplication()->getRouter();

        // Split the route into segments for SEF URL
        $segments = explode('/', trim($route, '/'));

        // Add segments to the router vars for SEF URL generation
        $uri = new Uri($nonSefUrl);
        $uri->setVar('segments', $segments);

        // Build and return the SEF URL
        return $router->build($uri)->toString();
    }

    /**
     * Parse a Joomla SEF URL to get the Laravel route
     *
     * @param string $url The SEF URL
     * @return string The Laravel route
     */
    public function parseSefUrl($url)
    {
        $router = Factory::getApplication()->getRouter();
        $uri = new Uri($url);

        // Parse the URI
        $vars = $router->parse($uri);

        // Extract segments from parsed vars
        $segments = $vars['segments'] ?? [];

        // Combine segments into a route
        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', $segments);
    }
}