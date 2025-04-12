<?php

use Illuminate\Support\Facades\Route;

// Simple hello world route
Route::get('/helloworld', function() {
    return 'Hello World from Jaravel Admin!';
});

// Default route
Route::get('/', function() {
    return 'Welcome to Helloworld component admin';
});

// Additional test route to ensure routing is working
Route::get('/test', function() {
    return 'Admin test route is working!';
});

// Catch-all route
Route::any('{any}', function($any = 'home') {
    return 'Caught admin route: ' . $any;
})->where('any', '.*');