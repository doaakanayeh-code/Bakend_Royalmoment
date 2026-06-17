<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;

Route::get('/settings', [AdminController::class, 'getSettings']);

Route::post('/save-speech-text', [AIController::class, 'store']);

// التسجيل وإنشاء حساب جديد
Route::post('/register', [AuthController::class, 'register']);

// تسجيل الدخول (للحسابات المفعلة)
Route::post('/login', [AuthController::class, 'login2']);

// التحقق من كود الـ OTP لتفعيل الحساب بعد التسجيل
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// طلب كود نسيان كلمة السر
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

// التحقق من كود نسيان كلمة السر
Route::post('/verify-forgot-otp', [AuthController::class, 'verifyForgotOtp']);

// تعيين كلمة سر جديدة
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// إعادة إرسال كود الـ OTP
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

// 1. مسارات الزائر (عامة - لا تحتاج توكن)
Route::get('/events', [EventController::class, 'index']); // الزائر يرى الفعاليات هنا

// 2. مسارات تحتاج تسجيل دخول
Route::middleware('auth:sanctum')->group(function () {

    // مسارات خاصة بمزود الخدمة فقط (مثلاً إضافة فعالية)
    Route::post('/events/create', [EventController::class, 'store'])->middleware('checkRole:provider');

    // مسارات خاصة بالمستخدم العادي (مثلاً الحجز)
    // Route::post('/bookings/reserve', [BookingController::class, 'reserve'])->middleware('checkRole:user');

});

// مجموعة المسارات المحمية بـ Sanctum والـ Middleware الخاص بكِ
Route::middleware(['auth:sanctum', 'IsProvider'])->group(function () {
    
    // هذا مسار وهمي فقط لاختبار شغلك
    Route::get('/test-provider', function () {
        return response()->json([
            'status' => true,
            'message' => 'Success! Your Middleware is working. You are recognized as a Provider.'
        ]);
    });

});

Route::middleware('auth:sanctum')->group(function () {

Route::post('/comments', [CommentController::class, 'storeComment']);

    // عرض بيانات البروفايل الشخصي
    Route::get('/profile', [AuthController::class, 'showProfile']);

    // تسجيل الخروج وحذف التوكن
    Route::post('/logout', [AuthController::class, 'logout']);

    // تحديث توكن الجهاز (Firebase Token) 
    // مهم جداً لكي يعمل FirebaseNotificationService بشكل صحيح
    Route::post('/update-device-token', [AuthController::class, 'updateDeviceToken']);

});


// هذه المجموعة تضمن أن المستخدم مسجل دخول (auth) وأنه "مزود خدمة" (IsProvider)
Route::middleware(['auth:sanctum', 'IsProvider'])->group(function () {

    // مسار إضافة خدمة جديدة (الكنترولر الشامل الذي كتبناه)
    Route::post('/services/store', [ServiceController::class, 'store']);

    // مسار لعرض الخدمات الخاصة بهذا المزود فقط
    Route::get('/services/my-all', [ServiceController::class, 'index']); 
    
    // مسار لحذف خدمة (إذا أراد المزود إزالة صورة أو خدمة)
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

});
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
         ->name('verification.send');
   Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
     ->name('verification.verify'); 
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.reset');


Route::middleware(['auth:sanctum', 'admin'])->group(function () {
Route::get('/admin/users', [AdminController::class, 'index']);
Route::post('/admin/users/{id}', [AdminController::class, 'updateuser']);
Route::delete('/admin/users/{id}', [AdminController::class, 'destroy']);
Route::delete('/admin/users/{id}/soft-delete', [AdminController::class, 'softDelete']);
Route::post('/admin/users/{id}/restore', [AdminController::class, 'restoreUser']);
Route::post('/admin/users/{id}/block', [AdminController::class, 'blockUser']);
Route::post('/admin/users/{id}/unblock', [AdminController::class, 'unblock']);
Route::post('/app/settings/update', [AdminController::class, 'updateSettings']);
Route::get('/providers', [AdminController::class, 'showProviders']);
Route::post('/providers/{id}', [AdminController::class, 'updateProvider']);
Route::delete('/providers/{id}', [AdminController::class, 'destroyProvider']);
Route::get('/providers/{provider_id}/services', [AdminController::class, 'getProviderServices']);
Route::get('/admin/comments', [AdminController::class, 'getAllComments']);
Route::delete('/admin/comments/{id}', [AdminController::class, 'destroyComment']);
Route::post('/admin/comments/{id}/toggle-like', [AdminController::class, 'toggleCommentLike']);
Route::get('/admin/filtered-comments', [AdminController::class, 'getFilteredComments']);
Route::get('/admin/filtered-users', [AdminController::class, 'getFilteredUsers']);
Route::get('/admin/filtered-providers', [AdminController::class, 'getFilteredProviders']);
Route::get('/admin/users-statistics', [AdminController::class, 'getUsersStatistics']);
Route::post('admin/providers/add', [AdminController::class, 'addProvider']);
Route::post('/users/add', [AdminController::class, 'addUser']);
Route::get('/users/export', [UserController::class, 'export']);
Route::post('/users/import', [UserController::class, 'import']);


});


