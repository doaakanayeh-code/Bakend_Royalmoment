<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invitation;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvitationController extends Controller
{
    // دالة توليد الباركود
    public function generate($id)
    {
        $guest = Invitation::findOrFail($id);

        // الرابط الذي سيتم ترميزه (يجب أن يكون رابطاً متاحاً في التطبيق)
        $url = url('/api/verify-guest/' . $guest->code);

        // توليد الباركود كصورة
        return QrCode::size(300)->generate($url);
    }
public function verifyGuest($code)
{
    // 1. البحث عن الضيف
    $guest = Invitation::where('code', $code)->first();

    // 2. التحقق من وجوده
    if (!$guest) {
        return response()->json([
            'status' => 'error',
            'message' => 'الباركود غير موجود في نظامنا!'
        ], 404);
    }

    // 3. التحقق من كونه قد دخل مسبقاً
    if ($guest->is_scanned) {
        return response()->json([
            'status' => 'warning',
            'message' => 'تم استخدام هذه الدعوة مسبقاً من قبل ' . $guest->guest_name . '!',
            'scanned_at' => $guest->scanned_at
        ], 409); // 409 Conflict
    }

    // 4. تحديث الحالة
    $guest->update([
        'is_scanned' => true,
        'scanned_at' => now()
    ]);

    // 5. الرد الناجح
    return response()->json([
        'status' => 'success',
        'message' => 'أهلاً بك يا ' . $guest->guest_name . '، دخول موفق!'
    ], 200);
}
public function index()
{
    // فريق الرياكت يحتاجون بيانات فقط، لا يحتاجون صفحات HTML
    $guests = Invitation::all();
    
    return response()->json([
        'data' => $guests
    ]);
}
}