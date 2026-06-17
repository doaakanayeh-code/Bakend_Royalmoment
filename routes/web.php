<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialiteController;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
Route::get('/', function () {
    return view('welcome');
});



Route::get('/test-gemini', function () {
    $result = Gemini::generativeModel(model: 'gemini-2.0-flash')
    ->generateContent('Hello');

$result = Gemini::generativeModel(model: 'gemini-2.0-flash')
    ->generateContent([
        'What is this picture?',
        new Blob(
            mimeType: MimeType::IMAGE_JPEG,
            data: base64_encode(
                file_get_contents('https://storage.googleapis.com/generativeai-downloads/images/scones.jpg')
            )
        ,
    )]);

$chat = Gemini::chat(model: 'gemini-2.0-flash')->startChat();
});























// رابط توجيه المستخدم لجوجل
Route::get('auth/google', [SocialiteController::class, 'redirectToGoogle']);

// رابط الرجوع من جوجل (Callback)
Route::get('auth/google/callback', [SocialiteController::class, 'handleGoogleCallback']);