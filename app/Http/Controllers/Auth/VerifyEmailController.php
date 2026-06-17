<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User; // تأكد من استدعاء موديل المستخدم
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request; // استبدلنا التكنيك القديم بـ Request عادي لمرونة الـ API
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    /**
     * تفعيل حساب المستخدم عبر الـ API.
     */
    public function __invoke(Request $request, $id, $hash): JsonResponse
    {
        // 1. جلب المستخدم من الـ id الممرر في الرابط مباشرة دون الاعتماد على الـ session
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        // 2. التحقق من الـ Hash للتأكد من أن الإيميل لم يتم التلاعب به
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'رابط التفعيل غير صالح.'], 403);
        }

        // 3. إذا كان الحساب مفعلاً مسبقاً
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'تم تفعيل الحساب مسبقاً.'], 200);
        }

        // 4. تفعيل الحساب وتفجير حدث التفعيل
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تفعيل حسابك بنجاح!'
        ], 200);
    }
}