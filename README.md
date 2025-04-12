# Jaravel

## Laravel Integration for Joomla

Jaravel is a library that enables seamless integration of Laravel's powerful features within Joomla components. It allows Joomla developers to leverage Laravel's modern PHP architecture while maintaining compatibility with the Joomla CMS.

> **IMPORTANT**: This library is still under development and is NOT intended for production use.

## Features

- **Laravel in Joomla**: Run Laravel applications within Joomla components
- **Routing System**: Use Laravel's elegant routing system in your Joomla components
- **MVC Architecture**: Implement Laravel's Model-View-Controller pattern
- **Dependency Injection**: Access Laravel's powerful service container
- **Eloquent ORM**: Use Laravel's intuitive database ORM alongside Joomla
- **Blade Templating**: Leverage Laravel's Blade template engine for your views
- **Debugging Tools**: Built-in debug mode for development

## Installation

1. Install the Jaravel library in your Joomla installation's `/libraries` directory
2. Run `composer install` inside the Jaravel library directory
3. Create a Joomla component that uses Jaravel

## Basic Usage

### Component Entry Point

```php
// File: components/com_example/example.php
<?php
defined('_JEXEC') or die('Restricted access');

// Include the Jaravel autoloader
require_once JPATH_LIBRARIES . '/jaravel/vendor/autoload.php';

// Create a new Jaravel entry point
$jaravel = new \Jaravel\Entry();

// Enable debug mode during development
$jaravel->enableDebug(true);

// Register the component
$jaravel->registerComponent('com_example');

// Get the route from input
$route = \Joomla\CMS\Factory::getApplication()->input->getString('route', '/');

// Run the component through Jaravel
$response = $jaravel->runTask('com_example', $route);

// Output the response
echo $response->getContent();
```

### Component Routing

Create a `routes/web.php` file in your component directory:

```php
// File: components/com_example/routes/web.php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExampleController;

// Define routes
Route::get('/', function() {
    return view('welcome');
});

Route::get('/items', [ExampleController::class, 'index']);
Route::get('/items/{id}', [ExampleController::class, 'show']);
```

## Why Jaravel?

Jaravel was created to bridge the gap between Joomla's ecosystem and Laravel's modern PHP development approach. It allows developers to:

- Use modern PHP practices within Joomla
- Write cleaner, testable code
- Leverage Laravel's extensive ecosystem
- Modernize legacy Joomla components
- Create a pathway for Joomla components to potentially migrate to Laravel

## Current Status

This library is in early development stages and is actively being improved. Many features are experimental and the API may change significantly. Please do not use this in production environments yet.

## Requirements

- PHP 8.2 or higher
- Joomla 3.0+
- Laravel 12.0+

## License

GNU General Public License version 2 or later 