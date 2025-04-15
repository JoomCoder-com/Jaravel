<?php
// administrator/components/com_helloworld/helloworld.php
defined('_JEXEC') or die('Restricted access');

// Check if Jaravel library exists
if (!file_exists(JPATH_LIBRARIES . '/jaravel/vendor/autoload.php')) {
    die('Jaravel library not found. Please make sure it is installed correctly.');
}

// Include the Jaravel autoloader
require_once JPATH_LIBRARIES . '/jaravel/vendor/autoload.php';

// Load component's autoloader if it exists
$componentAutoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($componentAutoloader)) {
    require_once $componentAutoloader;
}

// Create a new Jaravel entry point
$jaravel = new \Jaravel\Entry();

// Enable debug mode
$jaravel->enableDebug(true);

// Enable route debugging
$jaravel->enableRouteDebugging(true);

// Register the component
$jaravel->registerComponent('com_helloworld');

// Get the route from input
$route = \Joomla\CMS\Factory::getApplication()->input->getString('route', '/');

// Run the component through Jaravel
$response = $jaravel->runTask('com_helloworld', $route);

// Output the response
echo $response->getContent();