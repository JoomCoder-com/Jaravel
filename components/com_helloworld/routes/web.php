<?php
// administrator/components/com_helloworld/routes/web.php

use Illuminate\Support\Facades\Route;
use Jaravel\Component\HelloWorld\Http\Controllers\TestController;

// Using the controller class with the use statement
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

// Simple route to check if controller exists
Route::get('/controller-test', function() {
    if (class_exists(TestController::class)) {
        return 'TestController class exists!';
    } else {
        return 'TestController class does not exist!';
    }
});