<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $otpService;
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }
    private function normalize($text)
{
    $text = trim($text);
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
    $text = preg_replace('/\s+/u', ' ', $text);

    $text = str_replace(['أ','إ','آ'], 'ا', $text);
    $text = str_replace('ى', 'ي', $text);
    $text = str_replace('ة', 'ه', $text);

    return $text;
}

public function register(Request $request, \App\Services\GoogleIdentityService $identityService)
    {
        try {
            $data = $request->validate([
                'username'     => 'required|string',
                'identifier'   => 'required|string',
                'id_img_front' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'id_img_back'  => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'role'         => 'required|in:user,provider',
                'password'     => 'required|string|min:8|confirmed',
            ]);

            $identifierType = filter_var($data['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            $exists = User::where($identifierType, $data['identifier'])->exists();
            if ($exists) {
                $errorMessage = $identifierType === 'email' 
                    ? 'هذا البريد الإلكتروني مسجل مسبقاً.' 
                    : 'رقم الهاتف هذا مسجل مسبقاً.';
                    
                return response()->json(['message' => $errorMessage], 422);
            }

            $pathFront = $request->file('id_img_front')->store('ids/front', 'public');
            $pathBack  = $request->file('id_img_back')->store('ids/back', 'public');
 
            $frontImagePath = storage_path(str_replace('/', DIRECTORY_SEPARATOR, 'app/public/' . $pathFront));

            Log::info('جاري إرسال الصورة المعالجة إلى Google OCR من المسار: ' . $frontImagePath);

            $ocrResult = $identityService->verifyId($frontImagePath);

            if (!$ocrResult['success']) {
                Log::error('فشل الـ OCR من طرف سيرفس جوجل:', [
                    'error_message' => $ocrResult['message'] ?? 'لا يوجد رسالة خطأ محددة',
                    'image_path'    => $frontImagePath,
                    'input_data'    => $request->except(['password', 'password_confirmation', 'id_img_front', 'id_img_back'])
                ]);

                return response()->json([
                    'message' => 'فشل في قراءة الهوية من طرف الخادم', 
                    'error'   => $ocrResult['message'] ?? 'Unknown OCR Service Error'
                ], 500);
            }

            $ocrText = $ocrResult['text'];

            $extractedName = '';
            $lines = explode("\n", $ocrText);
            
            foreach ($lines as $index => $line) {
                $cleanLine = trim($line);
                
                if (mb_strpos($cleanLine, 'الاسم') !== false || mb_strpos($cleanLine, 'الاسسيم') !== false || mb_strpos($cleanLine, 'الاسسم') !== false) {
                    $extractedName = $line;
                    break;
                }
                
                if ((mb_strpos($cleanLine, 'الاب') !== false || mb_strpos($cleanLine, 'النسبه') !== false) && $index > 0) {
                    $extractedName = $lines[$index - 1];
                    break;
                }
            }

            if (empty($extractedName)) {
                foreach ($lines as $line) {
                    $cleanLine = trim(preg_replace('/[a-zA-Z\d\W_]+/u', ' ', $line));
                    if (mb_strlen($cleanLine) > 3 && mb_strpos($line, 'الجمهوريه') === false && mb_strpos($line, 'وزاره') === false && mb_strpos($line, 'الداخلية') === false) {
                        $extractedName = $line;
                        break;
                    }
                }
            }
            
            $cleanInputName = $identityService->cleanText($data['username']);
            $cleanOcrName = $identityService->cleanText($extractedName);

            if (strpos($cleanOcrName, ':') !== false) {
                $parts = explode(':', $cleanOcrName);
                $cleanOcrName = end($parts); 
            }

            $stopWords = ['الاسم', 'الاسسيم', 'الاسسمسم', 'الاسسم', 'الاسس', 'النسبه', 'النسسبه', 'اسم', 'الاب', 'ام', 'ونسبه', 'محل', 'وتاريخ', 'الولادة', 'الجمهوريه', 'العربيه', 'السوريه', 'وزاره', 'الداخليه', 'سويريه'];
            foreach ($stopWords as $word) {
                $cleanOcrName = mb_ereg_replace($word, '', $cleanOcrName);
            }

            $cleanOcrName = preg_replace('/[a-zA-Z\d\W_]+/u', ' ', $cleanOcrName);
            $cleanOcrName = trim(preg_replace('/[ \t]+/u', ' ', $cleanOcrName));

            similar_text($cleanInputName, $cleanOcrName, $percent);

            if ($percent < 70) {
                return response()->json([
                    'message'          => 'الاسم المدخل لا يطابق الاسم الموجود في الهوية',
                    'match_percent'    => $percent,
                    'input_name_clean' => $cleanInputName, 
                    'ocr_name_clean'   => $cleanOcrName,   
                    'full_ocr_text'    => $lines          
                ], 422);
            }

            $userData = [
                'username'     => $data['username'],
                'id_img_front' => $pathFront,
                'id_img_back'  => $pathBack,
                'role'         => $data['role'],
                'password'     => Hash::make($data['password']),
            ];

            $userData[$identifierType] = $data['identifier'];

            $user = User::create($userData);

            if ($identifierType === 'email') {
                event(new \Illuminate\Auth\Events\Registered($user));
            }

            $token = $user->createToken('UserToken')->plainTextToken;

            return response()->json([
                'user'    => $user,
                'token'   => $token,
                'message' => 'تم إنشاء الحساب بنجاح'
            ], 201);

        } catch (\Exception $e) {
            Log::critical('خطأ غير متوقع أثناء عملية التسجيل (Exception):', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'حدث خطأ داخلي في الخادم، يرجى مراجعة ملفات السجل.',
                'exception_message' => $e->getMessage()
            ], 500);
        }
    }

    public function login2(Request $request)
{
    // 1. التعديل في التحقق: نستخدم identifier ونفحص عمود phone
    $request->validate(
        [
            'identifier' => 'required|string', 
            'password'   => 'required|string',
        ],
        [
            'identifier.required' => __('messages.identifier_required'),
            'password.required'   => __('messages.password_required'),
        ]
    );

    // 2. البحث في عمود phone أو email (الأسماء الجديدة في الجدول)
    $user = User::where('phone', $request->identifier)
                ->orWhere('email', $request->identifier)
                ->first();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => __('messages.account_not_found'),
            'data'    => []
        ], 404);
    }

   
    if ($user->status === 'pending') {
        return response()->json([
            'status'  => false,
            'message' => __('messages.Your_request_is_being_processed'),
            'data'    => []
        ], 403);
    }

    if ($user->status === 'rejected') {
        return response()->json([
            'status'  => false,
            'message' => __('messages.account_rejected_body'),
            'data'    => []
        ], 403);
    }

    // 4. التحقق من كلمة المرور (يدوياً لأننا لا نستخدم guard التقليدي مع الهاتف)
    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'status'  => false,
            'message' => __('messages.invalid_credentials'),
            'data'    => []
        ], 401);
    }

    // 5. إنشاء التوكن (تأكدي من حل مشكلة الـ Trait في الموديل أولاً)
    $token = $user->createToken('UserToken')->plainTextToken;

    return response()->json([
        'status'  => true,
        'message' => __('messages.login_success'),
        'data'    => [
            'user'  => $user,
            'token' => $token
        ]
    ]);
}
public function logout()
{
    // التأكد أولاً أن المستخدم مسجل دخول (حماية إضافية)
    if (Auth::check()) {
        // حذف التوكن الحالي المستخدم في الجلسة
        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 1,
            'message' => __('messages.logout_success'),
            'data'    => [],
        ]);
    }

    return response()->json([
        'status'  => 0,
        'message' => 'User not authenticated',
        'data'    => [],
    ], 401);
}
    //عرض البروفايل
   public function showProfile()
{
    // الحصول على بيانات المستخدم المسجل دخوله حالياً
    $user = Auth::user();

    // تجهيز البيانات الجديدة بناءً على تعديلات الجداول
    $profileData = [
        'username'     => $user->username,      // بدلاً من الاسم الأول والأخير
        'email'        => $user->email,         // أضفنا الإيميل
        'phone'        => $user->phone,
        'id_img_front' => $user->id_img_front,  // الهوية وجه أمامي
        'id_img_back'  => $user->id_img_back,   // الهوية وجه خلفي
        'status'       => $user->status,        // حالة الحساب (مفعل أم لا)
    ];

    return response()->json([
        'status'  => 1,
        'message' => __('messages.your_profile'),
        'data'    => $profileData
    ]);
}
      //كود التحقق 
   public function verifyOtp(Request $request)
{
    $request->validate(
        [
            // غيرنا الاسم لـ identifier وأزلنا digits:10 ليدعم الإيميل
            'identifier' => 'required|string', 
            'otp'        => 'required|string',
        ],
        [
            'identifier.required' => __('messages.identifier_required'),
            'otp.required'        => __('messages.otp_required'),
        ]
    );
    
        $result = $this->otpService->verifyOtp($request->identifier, $request->otp);
        return response()->json($result);
}

    //دالة تعيين كلمة السر 
  public function resetPassword(Request $request)
{
    $request->validate([
        'identifier' => 'required|string',
        'password'   => 'required|string|min:8|confirmed',
    ]);

    $user = User::where('phone', $request->identifier)
                ->orWhere('email', $request->identifier)
                ->first();

    if (!$user) {
        return response()->json(['message' => __('messages.phone_not_registered')], 404);
    }

    $user->update([
        'password' => Hash::make($request->password),
    ]);

    return response()->json(['message' => __('messages.password_reset_successfully')]);
}
public function verifyForgotOtp(Request $request)
{
    $request->validate([
        'identifier' => 'required|string', 
        'otp'        => 'required|string',
    ]);

    // البحث في جدول الـ otps الجديد باستخدام عمود identifier الموحد مباشرة
    $otp = Otp::where('identifier', $request->identifier)
        ->where('otp', $request->otp)
        ->where('used', false)
        ->latest()
        ->first();

    if (!$otp) {
        return response()->json(['message' => __('messages.Verification_code_is_invalid_or_expired')], 400);
    }

    $otp->update(['used' => true]);

    return response()->json([
        'message'  => __('messages.Verification_successful_You_can_now_set_a_new_password'),
        'verified' => true,
    ]);
}
    //دالة نسيان كلمة لسر 

   public function forgotPassword(Request $request)
{
    $request->validate([
        'identifier' => 'required|string',
    ]);

    // البحث عن المستخدم بالايميل أو الهاتف
    $user = User::where('phone', $request->identifier)
                ->orWhere('email', $request->identifier)
                ->first();

    if (!$user) {
        return response()->json(['message' => __('messages.phone_not_registered')], 404);
    }

    try {
        // نرسل الـ OTP للمعرف الموجود (سواء كان هاتف أو ايميل)
        $receiver = $user->phone ?? $user->email;
        $otp = $this->otpService->createOtp($receiver);
        $this->otpService->attemptSendOtp($receiver, $otp);

        return response()->json([
            'success' => true,
            'message' => __('messages.A_verification_code_has_been_sent_to_reset_the_password'),
        ], 200);

    } catch (\Exception $e) {
        Log::error('[FORGOT PASSWORD] Error: '.$e->getMessage());
        return response()->json(['success' => false, 'message' => __('messages.An_unexpected_error_occurred')], 500);
    }
}

// أضيفي هذه الدالة داخل AuthController

public function resendOtp(Request $request)
{
    // 1. التحقق من وجود المعرف (هاتف أو إيميل)
    $request->validate([
        'identifier' => 'required|string',
    ], [
        'identifier.required' => __('messages.identifier_required'),
    ]);

    // 2. التأكد أن المستخدم موجود فعلاً في قاعدة البيانات
    $user = User::where('phone', $request->identifier)
                ->orWhere('email', $request->identifier)
                ->first();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => __('messages.account_not_found')
        ], 404);
    }

    try {
        // 3. استخدام الخدمة المحقونة لإرسال كود جديد
        // الخدمة ستتولى إنشاء OTP جديد وإرساله
        $this->otpService->sendOtp($request->identifier);

        return response()->json([
            'status'  => true,
            'message' => __('messages.otp_resent_successfully'), // تأكدي من إضافة هذا المفتاح في ملف اللغات
        ], 200);

    } catch (\Exception $e) {
        Log::error('[RESEND OTP] Error: ' . $e->getMessage());
        return response()->json([
            'status'  => false, 
            'message' => __('messages.An_unexpected_error_occurred')
        ], 500);
    }
}
public function updateDeviceToken(Request $request)
{
    $request->validate([
        'device_token' => 'required|string',
    ]);

    // تحديث أو إنشاء توكن جديد للجهاز المرتبط بهذا المستخدم
    \App\Models\UserDevice::updateOrCreate(
        [
            'user_id'      => auth()->id(),
            'device_token' => $request->device_token
        ]
    );

    return response()->json([
        'status'  => true,
        'message' => 'Device token updated successfully',
    ]);
}
}

