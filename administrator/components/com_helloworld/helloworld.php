<?php
// administrator/components/com_helloworld/helloworld.php
defined('_JEXEC') or die('Restricted access');

// ... your existing initialization code ...

// Create a new Jaravel entry point
$jaravel = new \Jaravel\Entry();

// Enable debug mode and Laravel Debugbar
$jaravel->enableDebug(true);
$jaravel->enableDebugbar(true);

// Register the component
$jaravel->registerComponent('com_helloworld');

// Get the route from input
$route = \Joomla\CMS\Factory::getApplication()->input->getString('route', '/');

// Run the component through Jaravel - wrap in try/catch to see any errors
try {
    $response = $jaravel->runTask('com_helloworld', $route);

    // Output the response
    echo $response->getContent();
} catch (\Exception $e) {
    // Display error details
    echo '<div style="padding: 15px; margin: 15px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">';
    echo '<h3>Error: ' . htmlspecialchars($e->getMessage()) . '</h3>';
    echo '<p>File: ' . htmlspecialchars($e->getFile()) . ' (Line: ' . $e->getLine() . ')</p>';
    echo '<h4>Stack Trace:</h4>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}

// Display the debugging information regardless of errors
try {
    echo "<div style='background:#fff; padding:10px; margin:10px; border:1px solid #ccc;'>";
    echo "<h3>Debugbar Status:</h3>";

    // Get the application instance safely
    try {
        $app = \Illuminate\Container\Container::getInstance();
        echo "App instance exists: Yes<br>";

        // Check if there's a binding for Request class
        echo "Request bound: " . ($app->bound('Illuminate\Http\Request') ? 'Yes' : 'No') . "<br>";
        echo "request (lowercase) bound: " . ($app->bound('request') ? 'Yes' : 'No') . "<br>";

        // Check all bindings in the container
        echo "<h4>Registered Bindings:</h4>";
        $bindings = [];
        $reflectionProperty = new \ReflectionProperty(get_class($app), 'bindings');
        $reflectionProperty->setAccessible(true);
        $containerBindings = $reflectionProperty->getValue($app);
        foreach (array_keys($containerBindings) as $binding) {
            $bindings[] = $binding;
        }
        echo "<pre>" . print_r($bindings, true) . "</pre>";

        // Debugbar specific info
        echo "Debugbar bound: " . ($app->bound('debugbar') ? 'Yes' : 'No') . "<br>";
        if ($app->bound('debugbar')) {
            echo "Debugbar enabled: " . ($app['debugbar']->isEnabled() ? 'Yes' : 'No') . "<br>";
        }
    } catch (\Exception $ex) {
        echo "Error getting application details: " . $ex->getMessage();
    }

    echo "</div>";
} catch (\Exception $debugException) {
    echo '<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404;">';
    echo 'Error in debug code: ' . $debugException->getMessage();
    echo '</div>';
}