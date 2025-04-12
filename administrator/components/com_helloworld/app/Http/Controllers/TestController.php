<?php

namespace Jaravel\Component\HelloWorld\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TestController extends Controller
{
    /**
     * Show the test view
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('test.index', [
            'title' => 'Test Page',
            'message' => 'Hello from Jaravel test controller!'
        ]);
    }

    /**
     * Show details for a specific test item
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        return view('test.show', [
            'id' => $id,
            'title' => 'Test Item #' . $id,
            'details' => 'This is test item ' . $id
        ]);
    }
} 