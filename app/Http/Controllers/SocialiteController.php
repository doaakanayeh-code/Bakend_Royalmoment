<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SocialiteController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // البحث عن المستخدم باستخدام الحقول اللي أنتِ عرفتيها
            $user = User::where('provider_id', $googleUser->id)
                        ->where('provider_name', 'google')
                        ->first();

            if (!$user) {
                // إذا لم يوجد، نبحث عن طريق الإيميل
                $user = User::where('email', $googleUser->email)->first();

                if (!$user) {
                    // إنشاء مستخدم جديد بنفس أسماء حقول الميغريشن تبعك
                    $user = User::create([
                        'username'      => $googleUser->name, // استخدمنا username بدل name
                        'email'         => $googleUser->email,
                        'provider_name' => 'google',
                        'provider_id'   => $googleUser->id,
                        'password'      => Hash::make(uniqid()), // كلمة مرور عشوائية
                        
                        // ملاحظة: حقول الهوية لازم تكون nullable في الميغريشن 
                        // أو نعطيها قيمة افتراضية هنا إذا كانت إجبارية
                        'id_img_front'  => 'google_auth_no_image.jpg', 
                        'id_img_back'   => 'google_auth_no_image.jpg',
                        'role'          => 'user', 
                    ]);
                } else {
                    // إذا الحساب موجود بالإيميل، نحدث حقول جوجل فقط
                    $user->update([
                        'provider_name' => 'google',
                        'provider_id'   => $googleUser->id,
                    ]);
                }
            }

            // توليد التوكن (استخدمي plainTextToken إذا كنتِ تستخدمين Sanctum)
            $token = $user->createToken('auth_token')->plainTextToken;

            // التوجيه للفرونت إند (React/Vue)
            return redirect("http://localhost:3000/google-callback?token=" . $token);

        } catch (\Exception $e) {
            \Log::error('Google Error: ' . $e->getMessage());
            return redirect("http://localhost:3000/login?error=auth_failed");
        }
    }
}