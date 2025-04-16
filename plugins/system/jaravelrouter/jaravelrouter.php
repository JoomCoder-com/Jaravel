<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Plugin to handle SEF URL routing for Jaravel components
 */
class PlgSystemJaravelrouter extends CMSPlugin
{
    /**
     * List of detected Jaravel components
     *
     * @var array
     */
    protected $jaravelComponents = [];

    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array  $config  An optional associative array of configuration settings
     */
    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();

        // Auto-detect Jaravel components
        $this->detectJaravelComponents();
    }

    /**
     * After route event
     *
     * @return  void
     */
    public function onAfterRoute()
    {
        $app = Factory::getApplication();

        // Only process in site application
        if ($app->isClient('administrator')) {
            return;
        }

        $option = $app->input->get('option', '');

        // Check if this is a Jaravel component
        if (!in_array($option, $this->jaravelComponents)) {
            return;
        }

        // Get the SEF segments from the router
        $segments = $app->getRouter()->getVars()['segments'] ?? [];

        if (empty($segments)) {
            // No segments, default to home route
            $route = '/';
        } else {
            // Join segments with slash to form the route
            $route = '/' . implode('/', $segments);
        }

        // Set the route in the input
        $app->input->set('route', $route);
    }

    /**
     * Detect installed Jaravel components by checking for routes directory
     *
     * @return void
     */
    protected function detectJaravelComponents()
    {
        $components = [];

        // Get list of site components
        $componentDir = JPATH_SITE . '/components';
        if (is_dir($componentDir)) {
            $folders = scandir($componentDir);
            foreach ($folders as $folder) {
                if ($folder[0] !== '.' && is_dir($componentDir . '/' . $folder)) {
                    // Check if this is a Jaravel component by looking for routes directory
                    if (is_dir($componentDir . '/' . $folder . '/routes')) {
                        $components[] = $folder;
                    }
                }
            }
        }

        // Get list of administrator components
        $adminComponentDir = JPATH_ADMINISTRATOR . '/components';
        if (is_dir($adminComponentDir)) {
            $folders = scandir($adminComponentDir);
            foreach ($folders as $folder) {
                if ($folder[0] !== '.' && is_dir($adminComponentDir . '/' . $folder)) {
                    // Check if this is a Jaravel component by looking for routes directory
                    if (is_dir($adminComponentDir . '/' . $folder . '/routes')) {
                        if (!in_array($folder, $components)) {
                            $components[] = $folder;
                        }
                    }
                }
            }
        }

        $this->jaravelComponents = $components;
    }
}