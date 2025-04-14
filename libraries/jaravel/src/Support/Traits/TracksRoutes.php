<?php
// libraries/jaravel/src/Support/Traits/TracksRoutes.php

namespace Jaravel\Support\Traits;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;

/**
 * Trait to add route tracking capabilities for Debugbar
 */
trait TracksRoutes
{
    /**
     * Track the current route in Debugbar
     *
     * @param Route $route
     * @return void
     */
    protected function trackRouteInDebugbar(Route $route)
    {
        if (!App::bound('debugbar') || !App::make('debugbar')->isEnabled()) {
            return;
        }

        $debugbar = App::make('debugbar');

        // Add route information to Debugbar
        $routeInfo = [
            'uri' => $route->uri(),
            'methods' => implode('|', $route->methods()),
            'name' => $route->getName() ?: '(unnamed)',
            'action' => $route->getActionName(),
            'middleware' => $route->gatherMiddleware(),
            'parameters' => $route->parameters(),
        ];

        $debugbar->addMessage('Current Route: ' . $route->uri(), 'routes');
        $debugbar->addMessage($routeInfo, 'routes');

        // Add to timeline
        if (method_exists($debugbar, 'startMeasure')) {
            $debugbar->startMeasure(
                'route_' . $route->uri(),
                'Route: ' . $route->getActionName()
            );
        }
    }

    /**
     * Track controller execution in Debugbar
     *
     * @param string $controller Controller class name
     * @param string $method Method name
     * @param callable $callback The controller execution callback
     * @return mixed
     */
    protected function trackControllerInDebugbar($controller, $method, callable $callback)
    {
        if (!App::bound('debugbar') || !App::make('debugbar')->isEnabled()) {
            return $callback();
        }

        $debugbar = App::make('debugbar');

        // Start measuring controller execution time
        $debugbar->startMeasure(
            'controller_' . class_basename($controller) . '_' . $method,
            'Controller: ' . class_basename($controller) . '@' . $method
        );

        // Log controller information
        $debugbar->addMessage(
            'Executing controller: ' . class_basename($controller) . '@' . $method,
            'controllers'
        );

        // Execute the controller method
        $result = $callback();

        // Stop measuring
        $debugbar->stopMeasure('controller_' . class_basename($controller) . '_' . $method);

        return $result;
    }

    /**
     * Track view rendering in Debugbar
     *
     * @param string $view View name
     * @param array $data View data
     * @param callable $callback The view rendering callback
     * @return mixed
     */
    protected function trackViewInDebugbar($view, array $data, callable $callback)
    {
        if (!App::bound('debugbar') || !App::make('debugbar')->isEnabled()) {
            return $callback();
        }

        $debugbar = App::make('debugbar');

        // Start measuring view rendering time
        $debugbar->startMeasure('view_' . $view, 'View: ' . $view);

        // Log view information
        $debugbar->addMessage('Rendering view: ' . $view, 'views');
        $debugbar->addMessage('View data: ' . json_encode(array_keys($data)), 'views');

        // Render the view
        $result = $callback();

        // Stop measuring
        $debugbar->stopMeasure('view_' . $view);

        return $result;
    }
}