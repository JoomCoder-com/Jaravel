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
        // Process the request first
        $response = $next($request);

        // If debugbar is enabled and bound in the container
        if (App::bound('debugbar') && App::make('debugbar')->isEnabled()) {
            $debugbar = App::make('debugbar');

            // Add basic request information
            $debugbar->addMessage("Request URI: " . $request->getRequestUri(), 'jaravel');
            $debugbar->addMessage("Method: " . $request->method(), 'jaravel');

            // Add component information if available
            if (App::bound('jaravel.component_id')) {
                $componentId = App::make('jaravel.component_id');
                $debugbar->addMessage("Component: " . $componentId, 'jaravel');
            }

            // Only modify HTML responses
            if ($response instanceof Response &&
                $response->headers->get('Content-Type', '') == '' ||
                strpos($response->headers->get('Content-Type', ''), 'text/html') !== false) {

                $content = $response->getContent();

                // Check if we need to inject the debugbar
                if (strpos($content, '</body>') !== false) {
                    try {
                        // Get the debugbar renderer
                        $renderer = $debugbar->getJavascriptRenderer();

                        // Set the base URL for assets
                        $renderer->setBaseUrl(\Joomla\CMS\Uri\Uri::root(true) . '/media/jaravel/debugbar');

                        // Force enable jQuery NoConflict
                        $renderer->setEnableJqueryNoConflict(true);

                        // Get debugbar HTML (head and body)
                        $debugbarHead = $renderer->renderHead();
                        $debugbarBody = $renderer->render();

                        // Add the head content before </head>
                        if (strpos($content, '</head>') !== false) {
                            $content = str_replace('</head>', $debugbarHead . '</head>', $content);
                        } else {
                            // If no </head> tag, add at the start of the body
                            $content = str_replace('<body', $debugbarHead . '<body', $content);
                        }

                        // Add the body content before </body>
                        $content = str_replace('</body>', $debugbarBody . '</body>', $content);

                        // Update the response content
                        $response->setContent($content);
                    } catch (\Exception $e) {
                        // If there's an error with debugbar rendering, log it but don't break the response
                        error_log('Debugbar error: ' . $e->getMessage());
                    }
                }
            }
        }

        return $response;
    }
}