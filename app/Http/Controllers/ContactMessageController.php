<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{
    public function store(Request $request)
    {
        // 1. التحقق من البيانات القادمة من الزائر أو المستخدم
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        // 2. حفظ الرسالة في جدول contact_messages
        // الحقول الإضافية (is_read و status) ستأخذ قيمها الافتراضية تلقائياً
        $contactMessage = ContactMessage::create($validated);

        // 3. إرسال إيميل فوري لكِ كأدمن لإعلامكِ بالرسالة الجديدة
        // سنقوم بإنشاء كلاس الإيميل (ContactMail) في الخطوة القادمة
        try {
            Mail::send('emails.admin_notification', ['msg' => $contactMessage], function ($message) use ($contactMessage) {
                $message->to('your-admin-email@gmail.com') // إيميلك الشخصي كأدمن
                        ->subject('رسالة تواصل جديدة من: ' . $contactMessage->name)
                        ->replyTo($contactMessage->email, $contactMessage->name); // ميزة الرد المباشر!
            });
        } catch (\Exception $e) {
            // حتى لو فشل سيرفر الإيميل، نضمن أن الرسالة حُفظت في قاعدة البيانات بنجاح
        }

        // 4. إرجاع رد نجاح للـ React
        return response()->json([
            'success' => true,
            'message' => 'تم استلام رسالتك بنجاح، سنرد عليك عبر الإيميل قريباً!'
        ], 201);
    }

public function reply(Request $request, $id)
{
    // 1. التحقق من وجود الرسالة أولاً
    $contactMessage = ContactMessage::find($id);

    if (!$contactMessage) {
        return response()->json([
            'success' => false,
            'message' => 'عذراً، هذه الرسالة غير موجودة أو تم حذفها سابقاً.'
        ], 404);
    }

    // 2. التحقق من صحة البيانات (بعد التأكد من وجود الرسالة)
    $request->validate(['admin_reply' => 'required|string']);

    // 3. تحديث الرسالة
    $contactMessage->update([
        'admin_reply' => $request->admin_reply,
        'status' => 'replied',
        'is_read' => true
    ]);

    // 4. محاولة الإرسال
    try {
        Mail::raw($request->admin_reply, function ($message) use ($contactMessage) {
            $message->to($contactMessage->email)
                    ->subject('رد من إدارة Royal Moments');
        });
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'فشل الإرسال: ' . $e->getMessage()
        ], 500);
    }

    return response()->json(['success' => true, 'message' => 'تم الرد بنجاح']);
}
public function deleteReply($id)
{
    $contactMessage = ContactMessage::findOrFail($id);

    // نقوم بتصفير حقل الرد وإعادة الحالة إلى "تم الاستلام" فقط
    $contactMessage->update([
        'admin_reply' => null,
        'status' => 'pending', 
        'is_read' => false
    ]);

    return response()->json(['success' => true, 'message' => 'تم حذف الرد من السجلات.']);
}
public function updateReply(Request $request, $id)
{
    $request->validate(['admin_reply' => 'required|string']);
    $contactMessage = ContactMessage::findOrFail($id);

    // 1. تحديث قاعدة البيانات
    $contactMessage->update([
        'admin_reply' => $request->admin_reply
    ]);

    // 2. إرسال الإيميل المعدل (إشعار بالتحديث)
    try {
        Mail::raw('عذراً، قمنا بتحديث ردنا السابق ليصبح: ' . "\n\n" . $request->admin_reply, function ($message) use ($contactMessage) {
            $message->to($contactMessage->email)
                    ->subject('تحديث للرد من إدارة Royal Moments');
        });
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'تم التحديث في القاعدة ولكن فشل إرسال الإيميل']);
    }

    return response()->json(['success' => true, 'message' => 'تم تعديل الرد وإرسال الإيميل للعميل.']);
}


}