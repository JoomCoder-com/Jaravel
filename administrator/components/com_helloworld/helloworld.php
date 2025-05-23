<?php
// administrator/components/com_helloworld/helloworld.php
defined('_JEXEC') or die('Restricted access');

// Check if Jaravel library exists
if (!file_exists(JPATH_LIBRARIES . '/jaravel/vendor/autoload.php')) {
    die('Jaravel library not found. Please make sure it is installed correctly.');
}

// Include the Jaravel autoloader
require_once JPATH_LIBRARIES . '/jaravel/vendor/autoload.php';

// Define frontend component path for sharing resources
$frontendPath = JPATH_SITE . '/components/com_helloworld';

// Use frontend's autoloader
$frontendAutoloader = $frontendPath . '/vendor/autoload.php';
if (file_exists($frontendAutoloader)) {
    require_once $frontendAutoloader;
}

// Create a new Jaravel entry point
$jaravel = new \Jaravel\Entry();

// Enable debug mode
$jaravel->enableDebug(true);

// Enable route debugging
$jaravel->enableRouteDebugging(true);

// Register the component with the shared frontend resources path
$jaravel->registerComponent('com_helloworld', $frontendPath);

// In admin, we just use the route parameter (no need for SEF)
$route = \Joomla\CMS\Factory::getApplication()->input->getString('route', '/');

// Run the component through Jaravel
$response = $jaravel->runTask('com_helloworld', $route);

// Output the response
echo $response->getContent();