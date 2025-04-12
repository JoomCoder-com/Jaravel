<?php
// libraries/jaravel/src/Support/JaravelService.php

namespace Jaravel\Support;

use Illuminate\Contracts\Container\Container;

/**
 * Main service class for Jaravel functionality
 */
class JaravelService
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * Create a new Jaravel service instance.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     * @return void
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Generate a URL for a Laravel route within Joomla
     *
     * @param string $route The Laravel route path
     * @param array $params Additional query parameters
     * @param bool $sef Whether to generate a SEF URL
     * @return string The Joomla URL
     */
    public function route($route, $params = [], $sef = true)
    {
        return UrlHelper::route($route, $params, $sef);
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
    public function namedRoute($name, $parameters = [], $query = [], $sef = true)
    {
        return UrlHelper::namedRoute($name, $parameters, $query, $sef);
    }

    /**
     * Create a back URL that preserves the referrer within the Joomla context
     *
     * @return string
     */
    public function back()
    {
        return UrlHelper::back();
    }

    /**
     * Generate a URL for an asset in the component's media directory
     *
     * @param string $path Path to the asset relative to the component's media directory
     * @return string
     */
    public function asset($path)
    {
        return UrlHelper::asset($path);
    }

    /**
     * Get the Joomla version
     *
     * @return string
     */
    public function joomlaVersion()
    {
        return \Joomla\CMS\Version::MAJOR_VERSION . '.' . \Joomla\CMS\Version::MINOR_VERSION;
    }

    /**
     * Get the current Joomla user
     *
     * @return \Joomla\CMS\User\User
     */
    public function user()
    {
        return \Joomla\CMS\Factory::getUser();
    }

    /**
     * Check if a user is authorized for an action
     *
     * @param string $action The action to check
     * @param string $asset The asset name
     * @param int|null $userId User ID (null for current user)
     * @return bool
     */
    public function authorize($action, $asset, $userId = null)
    {
        $user = $userId ? \Joomla\CMS\Factory::getUser($userId) : $this->user();
        return $user->authorise($action, $asset);
    }
}