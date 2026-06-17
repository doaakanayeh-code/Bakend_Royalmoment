<?php

namespace App\Http\Controllers\Api;
use App\Models\Service;
use App\Http\Controllers\Controller; // هذا السطر ناقص عندك وهو سبب الخطأ الأحمر
use Illuminate\Support\Facades\Auth;

class favoriteController extends Controller
{
    /**
     * إضافة خدمة للمفضلة
     */
    public function toggleFavorite($serviceId)
    {
        /** @var \App\Models\User $user */ // هذا السطر سيخفي الخطأ الأحمر في VS Code
        $user = Auth::user();

        // التأكد أن الخدمة موجودة
        $service = Service::findOrFail($serviceId);

        // استخدام toggle بدلاً من sync ليكون المسار واحد (أضف/احذف)
       
     // أو خليك على الـ syncWithoutDetaching إذا بدك زر منفصل للإضافة
        $status = $user->favoriteServices()->toggle($serviceId);

        if (count($status['attached']) > 0) {
            return response()->json(['message' => 'تمت الإضافة للمفضلة'], 200);
        }

        return response()->json(['message' => 'تمت الإزالة من المفضلة'], 200);
    }

    /**
     * عرض قائمة مفضلاتي
     */
    public function myFavorites()
    {
        /** @var \App\Models\User $user */ // هذا السطر سيخفي الخطأ الأحمر في VS Code
        $user = Auth::user();

        // جلب الخدمات المفضلة مع صورها والمزود الخاص بها
        $favorites = $user->favoriteServices()->with(['images', 'provider'])->latest()->get();

        return response()->json([
            'data' => $favorites
        ]);
    }
}
