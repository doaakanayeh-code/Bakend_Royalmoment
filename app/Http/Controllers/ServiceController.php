<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
   public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title'       => 'required|string|max:255',
            'description' => 'required',
            'price_start' => 'required|numeric',
            'city'        => 'required',
            'address'     => 'required',
            'images'      => 'required|array',
            'images.*'    => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $service = Service::create([
                'user_id'     => auth()->id(),
                'category_id' => $request->category_id,
                'title'       => $request->title,
                'description' => $request->description,
                'price_start' => $request->price_start,
                'city'        => $request->city,
                'address'     => $request->address,
                'capacity'    => $request->capacity,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $path = $file->store('services_gallery', 'public');
                    ServiceImage::create([
                        'service_id' => $service->id,
                        'image_path' => $path,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'تم حفظ الخدمة بنجاح مبرمجة رغد'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'فشل الحفظ: ' . $e->getMessage()], 500);
        }
    }
    
public function index()
    {
        $services = Service::where('user_id', auth()->id())->with('images')->get();
        return response()->json($services, 200);
    }
    public function update(Request $request, $id)
    {
        $service = Service::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'category_id' => 'exists:categories,id',
            'title'       => 'string|max:255',
            'price_start' => 'numeric',
            'images.*'    => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $service->update($request->only([
                'category_id', 'title', 'description', 'price_start', 'city', 'address', 'capacity'
            ]));

            // إذا أرسل المزود صوراً جديدة، نقوم بإضافتها
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $path = $file->store('services_gallery', 'public');
                    ServiceImage::create([
                        'service_id' => $service->id,
                        'image_path' => $path,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'تم تحديث الخدمة بنجاح مبرمجة رغد'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'فشل التحديث: ' . $e->getMessage()], 500);
        }
    }

    // 4. حذف خدمة مع صورها من القاعدة ومن التخزين
    public function destroy($id)
    {
        try {
            $service = Service::where('user_id', auth()->id())->with('images')->findOrFail($id);

            DB::beginTransaction();

            // حذف الملفات الفيزيائية للصور من السيرفر
            foreach ($service->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            // حذف السجل من القاعدة (سيحذف الصور تلقائياً إذا كان هناك On Delete Cascade)
            $service->delete();

            DB::commit();
            return response()->json(['message' => 'تم حذف الخدمة وصورها نهائياً مبرمجة رغد'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'فشل الحذف: ' . $e->getMessage()], 500);
        }
    }

}