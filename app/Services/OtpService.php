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
    public function sendOtp(string $phone): bool
    {
        $otp = rand(10000, 99999);

        Otp::create([
            'identifier' => $phone,
            'otp' => $otp,
            'used' => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        $message = "كود التحقق الخاص بك هو: $otp. يرجى عدم مشاركته مع أي شخص.";

        Log::info('[SMS][sendOtp] Sending OTP.', [
            'to' => $phone,
        ]);

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl, [
            'to' => $phone,
            'message' => $message,
        ]);

        Log::info('[SMS][sendOtp] Response.', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->successful();
    }
    public function verifyOtp(string $phone, string $code): array|string
    {
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

        $user = User::where('phone', $phone)->firstOrFail();

        return [
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $user,
        ];
    }

    public function sendMessage(string $phone, string $message): bool
    {
        Log::info('[SMS][sendMessage] Sending message.', [
            'to' => $phone,
        ]);

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl, [
            'to' => $phone,
            'message' => $message,
        ]);

        return $response->successful();
    }
    public function createOtp(string $phone): string
    {
        $otp = (string) rand(10000, 99999);

        Otp::create([
            'identifier' => $phone,
            'otp'        => $otp,
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        Log::channel('single')->info('[OTP] OTP created.', [
            'identifier' => $phone,
        ]);

        return $otp;
    }
    public function attemptSendOtp(string $phone, string $otp): bool
    {
        try {
            $message = "كود التحقق الخاص بك هو: $otp. يرجى عدم مشاركته مع أي شخص.";

            Log::info('[SMS] Sending OTP SMS.', [
                'to' => $phone,
            ]);

            $postData = json_encode([
                'to'      => $phone,
                'message' => $message
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postData,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);


            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            Log::info('[SMS] Response.', [
                'to'     => $phone,
                'status' => $httpCode,
                'body'   => $response,
            ]);

            if ($error) {
                Log::error('[SMS] cURL Error.', [
                    'to'    => $phone,
                    'error' => $error,
                ]);
                return false;
            }

            return $httpCode >= 200 && $httpCode < 300;

        } catch (\Throwable $e) {
            Log::error('[SMS] Exception.', [
                'to'    => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

}
