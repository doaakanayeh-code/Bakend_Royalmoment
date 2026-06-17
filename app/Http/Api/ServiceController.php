<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // 1. تابع العرض العام (انديكس) - لجلب كل الخدمات
  public function index()
{
    $services = Service::with('images')->latest()->paginate(10);
    
    // هكذا نرسل البيانات بشكل احترافي
    return ServiceResource::collection($services);
}

    // 2. تابع العرض التفصيلي (شاو) - لما يضغط المستخدم على خدمة معينة
 public function show($id)
{
    // 1. جلب الخدمة مع العلاقات المطلوبة
    $service = Service::with(['images', 'provider'])->find($id);

    // 2. التحقق من الوجود
    if (!$service) {
        return response()->json([
            'status' => false,
            'message' => 'الخدمة غير موجودة'
        ], 404);
    }

    // 3. التعديل الجوهري: استخدام الـ Resource هنا!
    return new ServiceResource($service);
}
}