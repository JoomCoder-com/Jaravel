<?php
// libraries/jaravel/src/Support/Debug.php

namespace Jaravel\Support;

use Illuminate\Container\Container;
use Jaravel\Instance\Manager;

/**
 * Debug utility for Jaravel with Bootstrap styling
 */
class Debug
{
    /**
     * @var bool Whether debug mode is enabled
     */
    protected static $enabled = false;

    /**
     * @var array Debug log messages
     */
    protected static $log = [];

    /**
     * @var array Error messages
     */
    protected static $errors = [];

    /**
     * Enable debug mode
     *
     * @param bool $enabled
     * @return void
     */
    public static function enable($enabled = true)
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return self::$enabled;
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param mixed $context Optional context data
     * @return void
     */
    public static function log($message, $context = null)
    {
        if (!self::$enabled) {
            return;
        }

        self::$log[] = [
            'time' => microtime(true),
            'message' => $message,
            'context' => $context
        ];
    }

    /**
     * Log an error message
     *
     * @param string $message
     * @param mixed $context Optional context data
     * @return void
     */
    public static function error($message, $context = null)
    {
        if (!self::$enabled) {
            return;
        }

        self::$errors[] = [
            'time' => microtime(true),
            'message' => $message,
            'context' => $context
        ];

        // Also add to regular log with error flag
        self::$log[] = [
            'time' => microtime(true),
            'message' => "Error: " . $message,
            'context' => $context,
            'type' => 'error'
        ];
    }

    /**
     * Get all logged messages
     *
     * @return array
     */
    public static function getLog()
    {
        return self::$log;
    }

    /**
     * Get all error messages
     *
     * @return array
     */
    public static function getErrors()
    {
        return self::$errors;
    }

    /**
     * Clear the log
     *
     * @return void
     */
    public static function clearLog()
    {
        self::$log = [];
        self::$errors = [];
    }

    /**
     * Get a list of registered routes for a component
     *
     * @param string $componentName
     * @return array
     */
    public static function getRoutes($componentName)
    {
        if (!self::$enabled) {
            return [];
        }

        try {
            // Get the Laravel application instance
            $manager = new Manager();
            $app = $manager->getInstance($componentName);

            // Get all routes
            $routesCollection = $app['router']->getRoutes();
            $routes = [];

            foreach ($routesCollection->getRoutes() as $route) {
                $routes[] = [
                    'methods' => $route->methods(),
                    'uri' => $route->uri(),
                    'action' => $route->getActionName()
                ];
            }

            return $routes;
        } catch (\Exception $e) {
            self::error('Error getting routes: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [];
        }
    }

    /**
     * Format a variable for display
     *
     * @param mixed $var
     * @return string
     */
    protected static function formatVar($var)
    {
        if (is_null($var)) {
            return '<span class="text-muted">null</span>';
        } elseif (is_bool($var)) {
            return $var ? '<span class="text-primary">true</span>' : '<span class="text-primary">false</span>';
        } elseif (is_string($var)) {
            if (strlen($var) > 100) {
                $var = htmlspecialchars(substr($var, 0, 100)) . '...';
            } else {
                $var = htmlspecialchars($var);
            }
            return '<span class="text-success">"' . $var . '"</span>';
        } elseif (is_numeric($var)) {
            return '<span class="text-primary">' . $var . '</span>';
        } elseif (is_array($var)) {
            $output = '<details><summary class="text-info">Array (' . count($var) . ')</summary>';
            $output .= '<div class="ms-4">';
            foreach ($var as $key => $value) {
                $output .= '<div class="mb-1"><span class="text-dark">' . htmlspecialchars($key) . '</span> => ' . self::formatVar($value) . '</div>';
            }
            $output .= '</div></details>';
            return $output;
        } elseif (is_object($var)) {
            $class = get_class($var);
            $output = '<details><summary class="text-warning">' . $class . '</summary>';
            $output .= '<div class="ms-4">';

            $reflect = new \ReflectionObject($var);
            $props = $reflect->getProperties();

            foreach ($props as $prop) {
                $prop->setAccessible(true);
                if ($prop->isInitialized($var)) {
                    $value = $prop->getValue($var);
                    $output .= '<div class="mb-1"><span class="text-dark">' . $prop->getName() . '</span> => ' . self::formatVar($value) . '</div>';
                } else {
                    $output .= '<div class="mb-1"><span class="text-dark">' . $prop->getName() . '</span> => <span class="text-muted">uninitialized</span></div>';
                }
            }

            $output .= '</div></details>';
            return $output;
        } else {
            return '<span class="text-muted">' . gettype($var) . '</span>';
        }
    }

    /**
     * Render debug information
     *
     * @return string
     */
    public static function render()
    {
        if (!self::$enabled) {
            return '';
        }

        // Generate a unique ID for this debug instance to prevent conflicts
        $debugId = 'jaravel-debug-' . mt_rand(1000000, 9999999);

        $output = '<div class="card mt-4 mb-4 shadow-sm" id="' . $debugId . '">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 d-flex align-items-center">
                    <span class="me-2"><i class="icon-wrench"></i> Jaravel Debug</span>
                    <span class="ms-auto badge bg-light text-dark">' . count(self::$log) . ' Events</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <ul class="nav nav-tabs" role="tablist" id="' . $debugId . '-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#' . $debugId . '-log" role="tab" aria-selected="true">Log Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#' . $debugId . '-routes" role="tab" aria-selected="false">Routes</a>
                    </li>';

        if (!empty(self::$errors)) {
            $output .= '
                    <li class="nav-item">
                        <a class="nav-link text-danger" data-bs-toggle="tab" href="#' . $debugId . '-errors" role="tab" aria-selected="false">
                            Errors <span class="badge bg-danger">' . count(self::$errors) . '</span>
                        </a>
                    </li>';
        }

        $output .= '
                </ul>
                
                <div class="tab-content p-3">
                    <div class="tab-pane fade show active" id="' . $debugId . '-log" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 120px">Time</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>';

        if (empty(self::$log)) {
            $output .= '<tr><td colspan="2" class="text-center text-muted">No log entries</td></tr>';
        } else {
            foreach (self::$log as $entry) {
                $time = date('H:i:s', (int)$entry['time']) . '.' . substr(number_format($entry['time'] - (int)$entry['time'], 4), 2);
                $rowClass = isset($entry['type']) && $entry['type'] === 'error' ? 'table-danger' : '';

                $output .= '<tr class="' . $rowClass . '">
                    <td class="text-muted">' . $time . '</td>
                    <td>' . htmlspecialchars($entry['message']);

                if ($entry['context'] !== null) {
                    $output .= '<div class="mt-2 p-2 bg-light rounded">' . self::formatVar($entry['context']) . '</div>';
                }

                $output .= '</td></tr>';
            }
        }

        $output .= '</tbody></table></div></div>';

        // Routes Tab
        $output .= '<div class="tab-pane fade" id="' . $debugId . '-routes" role="tabpanel">';

        $routes = self::$log ? array_filter(self::$log, function($entry) {
            return $entry['message'] === 'Registered routes' && isset($entry['context']);
        }) : [];

        if (!empty($routes)) {
            $routeData = current($routes);
            $output .= '<div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>URI</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($routeData['context'] as $route) {
                $method = implode('|', $route['methods']);
                $methodClass = '';

                if (in_array('GET', $route['methods'])) {
                    $methodClass = 'bg-success text-white';
                } elseif (in_array('POST', $route['methods'])) {
                    $methodClass = 'bg-primary text-white';
                } elseif (in_array('PUT', $route['methods']) || in_array('PATCH', $route['methods'])) {
                    $methodClass = 'bg-warning text-dark';
                } elseif (in_array('DELETE', $route['methods'])) {
                    $methodClass = 'bg-danger text-white';
                }

                $output .= '<tr>
                    <td><span class="badge ' . $methodClass . '">' . $method . '</span></td>
                    <td>' . htmlspecialchars($route['uri']) . '</td>
                    <td><code>' . htmlspecialchars($route['action']) . '</code></td>
                </tr>';
            }

            $output .= '</tbody></table></div>';
        } else {
            $output .= '<div class="alert alert-info">No routes registered or route information not available.</div>';
        }

        $output .= '</div>';

        // Errors Tab
        if (!empty(self::$errors)) {
            $output .= '<div class="tab-pane fade" id="' . $debugId . '-errors" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th style="width: 120px">Time</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach (self::$errors as $error) {
                $time = date('H:i:s', (int)$error['time']) . '.' . substr(number_format($error['time'] - (int)$error['time'], 4), 2);

                $output .= '<tr class="table-danger">
                    <td class="text-muted">' . $time . '</td>
                    <td><strong>' . htmlspecialchars($error['message']) . '</strong>';

                if ($error['context'] !== null) {
                    $output .= '<div class="mt-2 p-2 bg-light rounded">' . self::formatVar($error['context']) . '</div>';
                }

                $output .= '</td></tr>';
            }

            $output .= '</tbody></table></div></div>';
        }

        $output .= '</div></div></div>';

        // Add JavaScript to initialize the tabs properly
        $output .= '
        <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize tabs using Bootstrap JavaScript
            var tabEls = document.querySelectorAll(\'#' . $debugId . '-tabs a[data-bs-toggle="tab"]\');
            tabEls.forEach(function(tabEl) {
                tabEl.addEventListener("click", function(event) {
                    event.preventDefault();
                    
                    // Remove active class from all tabs
                    document.querySelectorAll(\'#' . $debugId . '-tabs a[data-bs-toggle="tab"]\').forEach(function(el) {
                        el.classList.remove("active");
                        el.setAttribute("aria-selected", "false");
                    });
                    
                    // Remove active and show class from all tab panes
                    document.querySelectorAll(\'#' . $debugId . ' .tab-pane\').forEach(function(el) {
                        el.classList.remove("active");
                        el.classList.remove("show");
                    });
                    
                    // Add active class to current tab
                    this.classList.add("active");
                    this.setAttribute("aria-selected", "true");
                    
                    // Get target tab pane id
                    var target = this.getAttribute("href");
                    
                    // Add active and show class to target tab pane
                    document.querySelector(target).classList.add("active");
                    document.querySelector(target).classList.add("show");
                });
            });
        });
        </script>';

        return $output;
    }

    /**
     * Capture exception details
     *
     * @param \Throwable $e
     * @return void
     */
    public static function captureException(\Throwable $e)
    {
        if (!self::$enabled) {
            return;
        }

        self::error('Exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * Register error handlers to capture all errors and exceptions
     *
     * @return void
     */
    public static function registerErrorHandlers()
    {
        if (!self::$enabled) {
            return;
        }

        // Set an error handler
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            self::error("PHP Error ($errno): $errstr", [
                'file' => $errfile,
                'line' => $errline
            ]);

            // Don't execute PHP's internal error handler
            return true;
        });

        // Set an exception handler
        set_exception_handler(function($e) {
            self::captureException($e);
        });
    }
}