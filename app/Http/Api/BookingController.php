<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Exception;

class BookingController extends Controller
{
    public function book(Request $request)
    {
        // 1. التحقق الأولي من البيانات
        $rules = [
            'service_id'     => 'required|exists:services,id',
            'start_time'     => 'required|date',
            'payment_method' => 'required|in:cash,stripe',
        ];

        // جلب الخدمة مع فئتها
        $serviceCheck = Service::with('category')->findOrFail($request->service_id);
        $categorySlug = trim(strtolower(optional($serviceCheck->category)->slug));

        // تصنيف الأقسام
        $fixedCategories = ['cake', 'packages', 'sweets'];
        $isFixedPrice = in_array($categorySlug, $fixedCategories);

        // الخدمات التي تسمح بحجوزات متعددة بنفس الوقت (مثل الحفلات العامة / التذاكر)
        $multiBookingCategories = ['events', 'concerts', 'shows']; 
        $allowsMultiBooking = in_array($categorySlug, $multiBookingCategories);

        if (!$isFixedPrice) {
            $rules['end_time'] = 'required|date|after:start_time';
        }

        $validated = $request->validate($rules);

        // تثبيت الـ user_id في متغير لمنع التضارب بين Stripe والداتا
        $userId = auth()->id() ?? 1;

        // توحيد صيغ الوقت
        $startTimeString = Carbon::parse($request->start_time)->toDateTimeString();
        $endTimeString   = $request->filled('end_time') 
            ? Carbon::parse($request->end_time)->toDateTimeString() 
            : $startTimeString;

        try {
            // 2. فتح الترانزاكشن والتخزين
            $booking = DB::transaction(function () use ($request, $validated, $serviceCheck, $isFixedPrice, $allowsMultiBooking, $startTimeString, $endTimeString, $userId) {
                
                // قفل سجل الخدمة لمنع التضارب التزامني
                $service = Service::where('id', $serviceCheck->id)->lockForUpdate()->first();

                // فحص التضارب: يتم فقط للأقسام الزمنية التي لا تقبل التعدد (كالصالات والكوشات)
                if (!$isFixedPrice && !$allowsMultiBooking) {
                    $isServiceBusy = Booking::where('service_id', $service->id)
                        ->whereIn('status', ['pending', 'approved', 'confirmed', 'pending_payment'])
                        ->where('start_time', '<', $endTimeString)
                        ->where('end_time', '>', $startTimeString)
                        ->lockForUpdate()
                        ->exists();

                    if ($isServiceBusy) {
                        throw new Exception('عذراً، هذه الخدمة أو الصالة محجوزة مسبقاً في هذه الفترة الزمنية.');
                    }
                }

                // حساب السعر
                // if ($isFixedPrice) {
                //     $totalPrice = $service->price_start;
                // } else { 
                //     $minutes = Carbon::parse($startTimeString)->diffInMinutes(Carbon::parse($endTimeString));
                //     $hours = ceil($minutes / 60);
                //     $hours = $hours <= 0 ? 1 : $hours;

                //     $totalPrice = $hours * $service->price_start;
                // }

                // // بوابة Stripe
                // $status = 'pending';
                // $transactionId = null;
                // $paymentIntentId = null;

                // if ($validated['payment_method'] === 'stripe') {
                //     try {
                //         Stripe::setApiKey(config('services.stripe.secret'));

                //         $paymentIntent = PaymentIntent::create([
                //             'amount'               => (int) round($totalPrice * 100),
                //             'currency'             => 'usd', 
                //             'payment_method_types' => ['card'],
                //             'metadata'             => [
                //                 'service_id' => $service->id,
                //                 'user_id'    => $userId,
                //             ],
                //         ]);

                //         $transactionId   = $paymentIntent->id;
                //         $paymentIntentId = $paymentIntent->client_secret; 
                //         $status          = 'pending_payment'; 

                //     } catch (Exception $e) {
                //         throw new Exception('فشلت تهيئة بوابة الدفع الإلكتروني: ' . $e->getMessage());
                //     }
                // }
                // حساب السعر الأساسي للخدمة
                if ($isFixedPrice) {
                    $totalPrice = $service->price_start;
                } else { 
                    $minutes = Carbon::parse($startTimeString)->diffInMinutes(Carbon::parse($endTimeString));
                    $hours = ceil($minutes / 60);
                    $hours = $hours <= 0 ? 1 : $hours;

                    $totalPrice = $hours * $service->price_start;
                }

                // 🌟 التعديل الجديد: جلب الـ specifications وفحص البكجات الإضافية (Addons) التابعة لواجهة الكوشة
                $extraOptions = $request->input('specifications', []);
                $addonsPrice = 0;

                if (isset($extraOptions['addons']) && is_array($extraOptions['addons'])) {
                    foreach ($extraOptions['addons'] as $addon) {
                        // جمع أسعار البكجات الفرعية المحددة بالواجهة
                        $addonsPrice += (float) ($addon['price'] ?? 0);
                    }
                }

                // إضافة أسعار البكجات الإضافية إلى المجموع النهائي
                $totalPrice += $addonsPrice;

                // بوابة Stripe
                $status = 'pending';
                $transactionId = null;
                $paymentIntentId = null;

                if ($validated['payment_method'] === 'stripe') {
                    try {
                        Stripe::setApiKey(config('services.stripe.secret'));

                        $paymentIntent = PaymentIntent::create([
                            // الـ totalPrice هنا أصبح يحتوي على (سعر الكوشة + سعر الإضافات المحددة)
                            'amount'               => (int) round($totalPrice * 100),
                            'currency'             => 'usd', 
                            'payment_method_types' => ['card'],
                            'metadata'             => [
                                'service_id' => $service->id,
                                'user_id'    => $userId,
                            ],
                        ]);

                        $transactionId   = $paymentIntent->id;
                        $paymentIntentId = $paymentIntent->client_secret; 
                        $status          = 'pending_payment'; 

                    } catch (Exception $e) {
                        throw new Exception('فشلت تهيئة بوابة الدفع الإلكتروني: ' . $e->getMessage());
                    }
                }

                // مصفوفة الخيارات الإضافية نظيفة ومستعدة لأي داتا مستقبلية من الواجهات
              // بدلاً من المصفوفة الفارغة القديمة: $extraOptions = [];
// نكتب هذا السطر الذكي:
$extraOptions = $request->input('specifications', []);
                // حفظ الحجز ببيانات متوافقة ومؤمنة
                return Booking::create([
                    'user_id'           => $userId,
                    'service_id'        => $service->id,
                    'start_time'        => $startTimeString,
                    'end_time'          => $endTimeString,
                    'total_price'       => $totalPrice, 
                    'payment_method'    => $validated['payment_method'],
                    'status'            => $status,
                    'transaction_id'    => $transactionId,
                    'payment_intent_id' => $paymentIntentId,
                    'options'           => $extraOptions, 
                ]);
            }); 

            // إرجاع رد النجاح للفرونت إند
            return response()->json([
                'message' => 'تم تسجيل طلب الحجز بنجاح وتأمين الموعد.',
                'booking' => $booking
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}