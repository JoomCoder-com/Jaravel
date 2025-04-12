<?php

use Illuminate\Support\Facades\Route;
use Jaravel\Component\HelloWorld\Http\Controllers\TestController;

// Simple route to check if controller exists
Route::get('/controller-test', function() {
    if (class_exists('Jaravel\Component\HelloWorld\Http\Controllers\TestController')) {
        return 'TestController class exists!';
    } else {
        return 'TestController class does not exist!';
    }
});

// Controller-based routes using class reference
Route::get('/test', [TestController::class, 'index']);
Route::get('/test/{id}', [TestController::class, 'show'])->where('id', '[0-9]+');

// Simple hello world route
Route::get('/helloworld', function() {
    return 'Hello World from Jaravel Admin!';
});

// Default route
Route::get('/', function() {
    return 'Welcome to Helloworld component admin';
});

// Additional test route to ensure routing is working
Route::get('/api/test', function() {
    return response()->json([
        'status' => 'success',
        'message' => 'API test route is working!',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Catch-all route
Route::any('{any}', function($any = 'home') {
    return 'Caught admin route: ' . $any;
})->where('any', '.*');