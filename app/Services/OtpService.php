<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private string $apiKey = 'dYyLwBDEQb2VGGN5OpLpMw:APA91bE0NdQrJlR-IT95CVHvwW5KfPKqRGVTlO8Xz12ycIlKHbKzjQtdEmAIH5OIMPY7AvqGgKI6t9mDOjKCcZVrtAtM_1b-LOVy9xGZSTNj72sZGgN71Yo';
    private string $apiUrl = 'https://www.traccar.org/sms/';

    // 1. إرسال الـ OTP وحفظه
    public function sendOtp(string $phone): bool
    {
        $otp = rand(10000, 99999);

        // الحفظ الآن مباشر ومضمون لأن العمود أصبح موجوداً
        Otp::create([
            'identifier' => $phone, 
            'otp'        => $otp,
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        $message = "كود التحقق الخاص بك هو: $otp. يرجى عدم مشاركته مع أي شخص.";

        Log::info('[SMS][sendOtp] Sending OTP.', ['to' => $phone]);

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post($this->apiUrl, [
            'to'      => $phone,
            'message' => $message,
        ]);

        return $response->successful();
    }

    // 2. التحقق من الـ OTP
    public function verifyOtp(string $phone, string $code): array|string
    {
        // البحث في عمود identifier الموحد
        $otp = Otp::where('identifier', $phone)
            ->where('otp', $code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return 'OTP is invalid or expired';
        }

        $otp->update(['used' => true]);

        // جلب المستخدم (سواء كان المعرف إيميل أو هاتف)
        $user = User::where('phone', $phone)
                    ->orWhere('email', $phone)
                    ->firstOrFail();

        return [
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user'  => $user,
        ];
    }

    // 3. إنشاء الـ OTP (لعملية نسيت كلمة المرور)
    public function createOtp(string $phone): string
    {
        $otp = (string) rand(10000, 99999);

        Otp::create([
            'identifier' => $phone,
            'otp'        => $otp,
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        return $otp;
    }

    // 4. محاولة الإرسال عبر cURL
    public function attemptSendOtp(string $phone, string $otp): bool
    {
        // الكود الحالي لديكِ ممتاز ولا يحتاج لتغيير هنا
        // لأنه يعتمد على المتغيرات الممررة له مباشرة
        return $this->sendMessage($phone, "كود التحقق الخاص بك هو: $otp");
    }

    public function sendMessage(string $phone, string $message): bool
    {
        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post($this->apiUrl, [
            'to'      => $phone,
            'message' => $message,
        ]);

        return $response->successful();
    }
}