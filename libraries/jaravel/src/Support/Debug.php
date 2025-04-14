<?php
// libraries/jaravel/src/Support/Debug.php

namespace Jaravel\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/**
 * Debug utility for Jaravel - wrapper around Laravel Debugbar
 */
class Debug
{
    /**
     * @var bool Whether debug mode is enabled
     */
    protected static $enabled = false;

    /**
     * @var array Legacy error messages for backwards compatibility
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

        // Enable or disable Debugbar if it exists
        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                if ($enabled) {
                    $app['debugbar']->enable();
                } else {
                    $app['debugbar']->disable();
                }
            }
        }
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

        // Use Debugbar if available
        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                if ($context !== null) {
                    $message .= ' ' . json_encode($context);
                }
                $app['debugbar']->addMessage($message, 'jaravel');
                return;
            }
        }

        // Legacy fallback - log to error_log if Debugbar is not available
        error_log('[Jaravel Debug] ' . $message . ($context !== null ? ' ' . json_encode($context) : ''));
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

        // Store for backwards compatibility
        self::$errors[] = [
            'time' => microtime(true),
            'message' => $message,
            'context' => $context
        ];

        // Use Debugbar if available
        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                if ($context !== null) {
                    $app['debugbar']->addMessage($context, 'errors');
                }
                $app['debugbar']->addMessage($message, 'errors');
                return;
            }
        }

        // Legacy fallback - log to error_log if Debugbar is not available
        error_log('[Jaravel Error] ' . $message . ($context !== null ? ' ' . json_encode($context) : ''));
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
     * Start timing a section
     *
     * @param string $name
     * @return void
     */
    public static function startMeasure($name)
    {
        if (!self::$enabled) {
            return;
        }

        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                $app['debugbar']->startMeasure($name);
            }
        }
    }

    /**
     * Stop timing a section
     *
     * @param string $name
     * @return void
     */
    public static function stopMeasure($name)
    {
        if (!self::$enabled) {
            return;
        }

        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                $app['debugbar']->stopMeasure($name);
            }
        }
    }

    /**
     * Add a measure
     *
     * @param string $name
     * @param float $start
     * @param float $end
     * @return void
     */
    public static function addMeasure($name, $start, $end)
    {
        if (!self::$enabled) {
            return;
        }

        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                $app['debugbar']->addMeasure($name, $start, $end);
            }
        }
    }

    /**
     * Get the current Laravel application instance
     *
     * @return \Illuminate\Contracts\Foundation\Application|null
     */
    protected static function getApplication()
    {
        // Try to get application from Container
        try {
            return Container::getInstance();
        } catch (\Exception $e) {
            // If Container is not initialized, return null
            return null;
        }
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

        // Log to Debugbar if available
        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                $app['debugbar']->addException($e);
                return;
            }
        }

        // Fallback to error log
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

    /**
     * Render debug information
     * This is mostly for backward compatibility
     *
     * @return string
     */
    public static function render()
    {
        if (!self::$enabled) {
            return '';
        }

        // Use Debugbar if available
        if ($app = self::getApplication()) {
            if ($app->bound('debugbar')) {
                // Debugbar will render itself, no need to do anything here
                return '';
            }
        }

        // Fallback message if Debugbar is not available
        return '<div class="alert alert-info">
            <strong>Debug information:</strong> For better debugging, install Laravel Debugbar.
        </div>';
    }
}