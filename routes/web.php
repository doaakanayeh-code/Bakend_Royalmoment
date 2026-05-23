<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialiteController;
Route::get('/', function () {
    return view('welcome');
});



// رابط توجيه المستخدم لجوجل
Route::get('auth/google', [SocialiteController::class, 'redirectToGoogle']);

// رابط الرجوع من جوجل (Callback)
Route::get('auth/google/callback', [SocialiteController::class, 'handleGoogleCallback']);