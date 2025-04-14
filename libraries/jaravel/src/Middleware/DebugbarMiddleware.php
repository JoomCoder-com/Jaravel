<?php
// libraries/jaravel/src/Middleware/DebugbarMiddleware.php

namespace Jaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Jaravel\Support\Debug;

/**
 * Middleware to handle Debugbar in Joomla context
 */
class DebugbarMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Log basic request info in Debugbar
        if (App::bound('debugbar') && App::make('debugbar')->isEnabled()) {
            $debugbar = App::make('debugbar');
            $debugbar->addMessage("Request URI: " . $request->getRequestUri(), 'jaravel');
            $debugbar->addMessage("Method: " . $request->method(), 'jaravel');

            // Log request parameters (exclude sensitive data)
            $params = $request->except(['password', 'token', 'key']);
            if (!empty($params)) {
                $debugbar->addMessage("Parameters: " . json_encode($params), 'jaravel');
            }

            // Start measuring the request handling time
            Debug::startMeasure('requestHandling', 'Request Handling');
        }

        // Process the request
        $response = $next($request);

        // Add Joomla-specific information to Debugbar
        if (App::bound('debugbar') && App::make('debugbar')->isEnabled()) {
            // Stop measuring
            Debug::stopMeasure('requestHandling');

            $debugbar = App::make('debugbar');

            // Add Joomla information
            $joomlaApp = \Joomla\CMS\Factory::getApplication();
            $debugbar->addMessage("Joomla Template: " . $joomlaApp->getTemplate(), 'jaravel');
            $debugbar->addMessage("Joomla User ID: " . \Joomla\CMS\Factory::getUser()->id, 'jaravel');

            // Get component information
            if (App::bound('jaravel.component_id')) {
                $componentId = App::make('jaravel.component_id');
                $debugbar->addMessage("Component: " . $componentId, 'jaravel');
            }

            // Modify response if it's HTML to ensure Debugbar assets load correctly in Joomla
            if ($response instanceof Response) {
                $content = $response->getContent();

                // Ensure jQuery doesn't conflict with Joomla's jQuery
                if (strpos($content, '</body>') !== false) {
                    $content = str_replace('</body>',
                        "<script>if (typeof jQuery !== 'undefined') { jQuery.noConflict(true); }</script>\n</body>",
                        $content);
                    $response->setContent($content);
                }
            }
        }

        return $response;
    }
}