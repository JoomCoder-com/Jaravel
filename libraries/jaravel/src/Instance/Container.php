<?php
namespace Jaravel\Instance;

use Illuminate\Container\Container as IlluminateContainer;

/**
 * Laravel Container wrapper for Jaravel
 * Extends Laravel's container with component-specific functionality
 */
class Container extends IlluminateContainer
{
    /**
     * @var string The component ID this container belongs to
     */
    protected $componentId;

    /**
     * Set the component ID for this container
     *
     * @param string $componentId
     * @return void
     */
    public function setComponentId($componentId)
    {
        $this->componentId = $componentId;
        $this->instance('jaravel.component_id', $componentId);
    }

    /**
     * Get the component ID
     *
     * @return string
     */
    public function getComponentId()
    {
        return $this->componentId;
    }

    /**
     * Get a prefixed binding key to prevent conflicts
     * between multiple component instances
     *
     * @param string $abstract
     * @return string
     */
    protected function getPrefixedKey($abstract)
    {
        // Only prefix non-global bindings
        if ($this->shouldPrefixBinding($abstract)) {
            return "{$this->componentId}.{$abstract}";
        }

        return $abstract;
    }

    /**
     * Determine if a binding should be prefixed
     *
     * @param string $abstract
     * @return bool
     */
    protected function shouldPrefixBinding($abstract)
    {
        // Don't prefix core Laravel services
        $coreServices = [
            'app', 'config', 'db', 'files', 'events', 'log',
            'router', 'url', 'view', 'cache', 'auth',
        ];

        return !in_array($abstract, $coreServices) &&
            strpos($abstract, 'Illuminate\\') !== 0;
    }

    /**
     * Override bind method to use prefixed keys
     *
     * {@inheritdoc}
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        $abstract = $this->getPrefixedKey($abstract);

        return parent::bind($abstract, $concrete, $shared);
    }

    /**
     * Override make method to use prefixed keys
     *
     * {@inheritdoc}
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getPrefixedKey($abstract);

        return parent::make($abstract, $parameters);
    }

    /**
     * Override has method to use prefixed keys
     *
     * {@inheritdoc}
     */
    public function has($abstract)
    {
        $abstract = $this->getPrefixedKey($abstract);

        return parent::has($abstract);
    }
}