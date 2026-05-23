<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        // مؤقتاً سنعيد رسالة نصية لنتأكد أن المسار يعمل
        return response()->json([
            'status' => true,
            'message' => 'Welcome Guest! This is the events list.',
            'data' => [] 
        ]);
    }
}