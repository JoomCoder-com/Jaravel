<?php
// libraries/jaravel/src/Facades/Debug.php

namespace Jaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for Debug functionality
 *
 * @method static void enable(bool $enabled = true)
 * @method static bool isEnabled()
 * @method static void log(string $message, mixed $context = null)
 * @method static array getLog()
 * @method static void clearLog()
 * @method static array getRoutes(string $componentName)
 * @method static string render(bool $asHtml = true)
 */
class Debug extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'jaravel.debug';
    }
}