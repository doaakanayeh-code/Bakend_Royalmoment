<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Provider;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\ContactMessage; 
use Illuminate\Support\Facades\Mail;
use App\Services\OtpService;
use Illuminate\Support\Facades\Cache;
class AdminController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    //عرض كل المستخدمين
public function index()
{
    $users = User::
    // where('is_blocked', false)
                 where('role', 'user') 
                 ->select(
                     'id',
                     'username',
                     'email',
                     'phone',
                     'role',
                     'is_blocked',
                     'created_at'
                 )
                 ->orderBy('id', 'asc')
                 ->get();
    
    return response()->json([
        'users' => $users
    ], 200);
}

/// تعديل بيانات مستخدم من قبل الأدمن
public function updateuser(Request $request, $id)
{
    $user = User::where('role', 'user')->findOrFail($id);

    $data = $request->validate([
        'phone' => 'nullable|string|unique:users,phone,' . $user->id,
        'role'  => 'required|in:user,provider,admin',
    ]);

    if (
        ($data['phone'] ?? $user->phone) == $user->phone &&
        $data['role'] == $user->role
    ) {
        return response()->json([
            'message' => 'لم يتم إجراء أي تعديل على بيانات المستخدم'
        ], 400);
    }

    $user->update([
        'phone' => $data['phone'] ?? $user->phone,
        'role'  => $data['role'],
    ]);

    return response()->json([
        'message' => 'تم تحديث بيانات المستخدم بنجاح',
        'user' => [
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'phone'    => $user->phone,
        ]
    ]);
}

/// حذف مستخدم نهائياً من المنصة
public function destroy($id)
{
    $user = User::withTrashed()->where('role', 'user')->find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'المستخدم غير موجود أو ليس برتبة مستخدم عادي'
        ], 404);
    }

    if (auth()->id() == $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكنك حذف حسابك الشخصي كمشرف من هنا!'
        ], 400);
    }

    if ($user->trashed()) {
        return response()->json([
            'success' => false,
            'message' => 'هذا المستخدم محذوف مسبقاً'
        ], 400);
    }

    $user->tokens()->delete();
    $user->forceDelete();

    return response()->json([
        'success' => true,
        'message' => 'تم حذف المستخدم نهائياً من المنصة'
    ], 200);
}

/// حذف ناعم للمستخدم من المنصة (Soft Delete)
public function softDelete($id)
{
    $user = User::where('role', 'user')->findOrFail($id);

    if (auth()->id() == $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكنك حذف حسابك الشخصي كمشرف من هنا!'
        ], 400);
    }

    $user->tokens()->delete();
    $user->delete();

    return response()->json([
        'success' => true,
        'message' => 'تم نقل المستخدم إلى سلة المحذوفات بنجاح وحمايته من الظهور بالتطبيق'
    ], 200);
}

// استرجاع الحساب من سلة المحذوفات
public function restoreUser($id)
{
    // التأكد من استرجاع الحسابات المحذوفة التي تملك رول 'user' فقط
    $user = User::onlyTrashed()->where('role', 'user')->findOrFail($id);
    
    $user->restore();

    return response()->json([
        'success' => true,
        'message' => 'تم استعادة حساب المستخدم بنجاح وإعادته للمنصة',
        'user'    => $user
    ], 200);
}
//حظر مستخدم
public function blockUser($id)
{
    $user = User::findOrFail($id);

    if (auth()->id() == $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكنك حظر حسابك الشخصي!'
        ], 400);
    }

    $user->update([
        'is_blocked' => true
    ]);

    $user->tokens()->delete();

    return response()->json([
        'success' => true,
        'message' => 'تم حظر المستخدم بنجاح',
        'user' => $user
    ], 200);
}
//الغاء الحظر
public function unblock($id)
{
    $user = User::withTrashed()->findOrFail($id);

    $user->is_blocked = false;
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'تم إلغاء الحظر بنجاح'
    ]);
}

//تقارير
public function getUsersStatistics()
{
$activeUsers = User::where('role', 'user')->where('is_blocked', false)->count(); 
$blockedUsers = User::where('role', 'user')->where('is_blocked', true)->count(); 
$deletedUsers = User::where('role', 'user')->onlyTrashed()->count(); 
$totalUsers = User::where('role', 'user')->withTrashed()->count();
$totalProviders = User::where('role', 'provider')->withTrashed()->count();
$activeProviders = User::where('role', 'provider')->where('is_blocked', false)->count();
$blockedProviders = User::where('role', 'provider')->where('is_blocked', true)->count();
$deletedProviders = User::where('role', 'provider')->onlyTrashed()->count();

    return response()->json([
        'success' => true,
        'users_stats' => [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'blocked' => $blockedUsers,
            'deleted' => $deletedUsers,
        ],
        'providers_stats' => [
            'total' => $totalProviders,
            'active' => $activeProviders,
            'blocked' => $blockedProviders,
            'deleted' => $deletedProviders,
        ]
    ], 200);
}
//عرض مزودي الخدمة
    public function showProviders()
    {
    
        $providers = User::where('role', 'provider')->get();

        return response()->json($providers);
    }

   
//حذف مزود الخدمة
    /**
     * حذف المزود
     */
public function destroyProvider($id)
{
    $provider = User::where('role', 'provider')->find($id);

    if (!$provider) {
        return response()->json([
            'message' => 'عذراً، مزود الخدمة المطلوب غير موجود أو تم حذفه مسبقاً.'
        ], 404);
    }

    $provider->forceDelete();

    return response()->json([
        'message' => 'تم حذف مزود الخدمة بنجاح'
    ]);
}

//تعديل مزود الخدمة
    public function updateProvider(Request $request, $id)
{
    $provider = User::where('role', 'provider')->findOrFail($id);

    $currentColumn = !empty($provider->email) ? 'email' : 'phone';
    $currentValue = $provider->$currentColumn;

    $rules = [
        'username' => 'required|string|max:255',
    ];

    if ($currentColumn === 'email') {
        $rules['identifier'] = 'required|email|in:' . $currentValue;
    } else {
        $rules['identifier'] = 'required|string|unique:users,phone,' . $id;
    }

    if ($request->has('username') && $request->username !== $provider->username) {
        $rules['id_img_front'] = 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
        $rules['id_img_back']  = 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
    } else {
        $rules['id_img_front'] = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
        $rules['id_img_back']  = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
    }

    $validated = $request->validate($rules);

    if ($provider->username === $validated['username'] && 
        $currentValue === $validated['identifier'] && 
        !$request->hasFile('id_img_front') && 
        !$request->hasFile('id_img_back')) {
        
        return response()->json([
            'message' => 'لم تقم بإجراء أي تغييرات، هذه البيانات محدثة بالفعل مسبقاً.'
        ], 200);
    }

    $updateData = [
        'username' => $validated['username']
    ];

    if ($currentColumn === 'phone') {
        $updateData['phone'] = $validated['identifier'];
    }

    if ($request->hasFile('id_img_front')) {
        $updateData['id_img_front'] = $request->file('id_img_front')->store('ids/front', 'public');
    }
    if ($request->hasFile('id_img_back')) {
        $updateData['id_img_back'] = $request->file('id_img_back')->store('ids/back', 'public');
    }

    $provider->update($updateData);

    return response()->json([
        'message' => 'تم تحديث بيانات مزود الخدمة بنجاح وفق الشروط المحددة',
        'data'    => $provider->only(['id', 'username', 'email', 'phone', 'id_img_front', 'id_img_back', 'role'])
    ]);
}

//عرض خدمات مزود الخدمة 
public function getProviderServices($provider_id)
{
    $provider = User::where('role', 'provider')->find($provider_id);

    if (!$provider) {
        return response()->json([
            'message' => 'عذراً، مزود الخدمة المطلوب غير موجود أو تم حذفه.'
        ], 404);
    }

    $services = $provider->services; 

    if ($services->isEmpty()) {
        return response()->json([
            'message' => 'لا يوجد خدمات مضافة لهذا المزود حالياً.',
            'provider_name' => $provider->username,
            'data' => []
        ], 200);
    }

    return response()->json([
        'message' => 'تم جلب خدمات مزود الخدمة بنجاح.',
        'provider_name' => $provider->username,
        'data' => $services
    ], 200);
}
//عرض الاعدادات
public function getSettings()
{
    $settings = Setting::pluck('value', 'key');

    $getLogoUrl = function($path) {
        if (!$path) return asset('Royal.png'); 
        return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . $path);
    };

    $getBannerUrl = function($path) {
        if (!$path) return asset('defaults/banner.png'); 
        return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . $path);
    };

    return response()->json([
        'success' => true,
        'settings' => [
            'site_name'  => $settings['site_name'] ?? 'Royal Moments',
            'site_logo'  => $getLogoUrl($settings['site_logo'] ?? null),
            'hero_image' => $getBannerUrl($settings['hero_image'] ?? null),
        ]
    ], 200);
}
//تعديل الاعدادات
public function updateSettings(Request $request)
{
    $request->validate([
        'site_name'  => 'nullable|string|max:255',
        'site_logo'  => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        'hero_image' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
    ]);

    if ($request->has('site_name')) {
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => $request->site_name]);
    }

    if ($request->hasFile('site_logo')) {
        $oldLogo = Setting::where('key', 'site_logo')->value('value');
        if ($oldLogo) Storage::disk('public')->delete($oldLogo);

        $logoPath = $request->file('site_logo')->store('settings', 'public');
        Setting::updateOrCreate(['key' => 'site_logo'], ['value' => $logoPath]);
    }

    if ($request->hasFile('hero_image')) {
        $oldBanner = Setting::where('key', 'hero_image')->value('value');
        if ($oldBanner) Storage::disk('public')->delete($oldBanner);

        $bannerPath = $request->file('hero_image')->store('settings', 'public');
        Setting::updateOrCreate(['key' => 'hero_image'], ['value' => $bannerPath]);
    }
    return $this->getSettings();
}
//عرض كل التعليقات+التقييمات
public function getAllComments()
{
    $comments = Comment::whereHas('user', function ($query) {
        $query->where('role', 'user');
    })
    ->with(['user:id,username'])
    ->orderBy('created_at', 'desc')
    ->get();

    return response()->json([
        'success' => true,
        'comments' => $comments
    ], 200);
}
//حذف التعليقات 
public function destroyComment($id)
{
    $comment = Comment::whereHas('user', function ($query) {
        $query->where('role', 'user');
    })->find($id);

    if (!$comment) {
        return response()->json([
            'success' => false,
            'message' => 'التعليق غير موجود، أو تم حذفه مسبقاً، أو لا يعود لمستخدم عادي.'
        ], 404);
    }

    $comment->delete();

    return response()->json([
        'success' => true,
        'message' => 'تم حذف تعليق المستخدم بنجاح من قبل الإدارة.'
    ], 200);
}
//التفاعل مع التعليقات 
public function toggleCommentLike($id)
{
    $comment = Comment::whereHas('user', function ($query) {
        $query->where('role', 'user');
    })->find($id);

    if (!$comment) {
        return response()->json([
            'success' => false,
            'message' => 'التعليق غير موجود أو لا يعود لمستخدم عادي.'
        ], 404);
    }

    $adminId = auth()->id();
    $hasLiked = $comment->likes()->where('user_id', $adminId)->exists();

    if ($hasLiked) {
        $comment->likes()->where('user_id', $adminId)->delete();
        $message = 'تم إزالة التفاعل من التعليق.';
    } else {
        $comment->likes()->create(['user_id' => $adminId]);
        $message = 'تم التفاعل مع التعليق بنجاح.';
    }

    return response()->json([
        'success' => true,
        'message' => $message
    ], 200);
}
//انشاء تقييم او تعليق
public function storeComment(Request $request)
{
    $request->validate([
        'provider_id' => 'required|exists:users,id',
        'content'     => 'required|string|max:500',
    ]);

    $userId = auth()->id();

    $hasCompletedBooking = Booking::where('user_id', $userId)
                                  ->where('provider_id', $request->provider_id)
                                  ->where('status', 'completed')
                                  ->exists();

    if (!$hasCompletedBooking) {
        return response()->json([
            'success' => false,
            'message' => 'عذراً، لا يمكنك التعليق أو التقييم إلا بعد إتمام حجز مؤكد ومكتمل لدى مزود الخدمة هذا.'
        ], 403);
    }

    $alreadyCommented = Comment::where('user_id', $userId)
                               ->where('provider_id', $request->provider_id)
                               ->exists();

    if ($alreadyCommented) {
        return response()->json([
            'success' => false,
            'message' => 'لقد قمت بإضافة تعليق وتقييم مسبقاً لهذا المزود، لا يمكن إضافة أكثر من تعليق.'
        ], 400);
    }

    $comment = Comment::create([
        'user_id'     => $userId,
        'provider_id' => $request->provider_id,
        'content'     => $request->content,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'تم نشر تعليقك وتقييمك بنجاح.',
        'comment' => $comment
    ], 201);


}
//فلترة الحجوزات
public function getFilteredBookings(Request $request)
{
    $query = Booking::query()->with(['user:id,username', 'provider:id,username']);

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('provider_id')) {
        $query->where('provider_id', $request->provider_id);
    }

    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->filled('date')) {
        $query->whereDate('booking_date', $request->date);
    }

    if ($request->filled('date_from') && $request->filled('date_to')) {
        $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
    }

    $bookings = $query->orderBy('booking_date', 'desc')->get();

    return response()->json([
        'success' => true,
        'count' => $bookings->count(),
        'bookings' => $bookings
    ], 200);
}
//فلترة اليوزرات
public function getFilteredUsers(Request $request)
{
    $query = User::where('role', 'user');

    if ($request->filled('status')) {
        $isBlocked = $request->status === 'blocked' ? true : false;
        $query->where('is_blocked', $isBlocked);
    }

    if ($request->filled('trash') && $request->trash === 'only') {
        $query->onlyTrashed();
    } else {
        $query->withTrashed(); 
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('username', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    $users = $query->orderBy('id', 'asc')->get();

    return response()->json([
        'success' => true,
        'count' => $users->count(),
        'users' => $users
    ], 200);
}
//فلترة المزودين
public function getFilteredProviders(Request $request)
{
    $query = User::where('role', 'provider');

    if ($request->filled('status')) {
        $isBlocked = $request->status === 'blocked' ? true : false;
        $query->where('is_blocked', $isBlocked);
    }

    if ($request->filled('category_id')) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('username', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    $providers = $query->orderBy('id', 'asc')->get();

    return response()->json([
        'success' => true,
        'count' => $providers->count(),
        'providers' => $providers
    ], 200);
}
//فلترة التعليقات
public function getFilteredComments(Request $request)
{
    $query = Comment::whereHas('user', function ($q) {
        $q->where('role', 'user');
    })->with(['user:id,username', 'provider:id,username']);

    if ($request->filled('rating')) {
        $query->where('rating', $request->rating);
    }

    if ($request->filled('provider_id')) {
        $query->where('provider_id', $request->provider_id);
    }

    if ($request->filled('type')) {
        if ($request->type === 'text_only') {
            $query->whereNotNull('content');
        } elseif ($request->type === 'rating_only') {
            $query->whereNull('content');
        }
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('content', 'LIKE', "%{$search}%")
              ->orWhereHas('user', function ($u) use ($search) {
                  $u->where('username', 'LIKE', "%{$search}%");
              });
        });
    }

    $comments = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'count' => $comments->count(),
        'comments' => $comments
    ], 200);
}
//اضافة زبون
public function addUser(Request $request)
{
    $request->validate([
        'username'     => 'required|string|max:255',
        'identifier'   => 'required|string',
        'password'     => 'required|string|min:8',
        'id_img_front' => 'required|image|mimes:jpeg,png,jpg|max:2048', // صورة الوجه الأمامي
        'id_img_back'  => 'required|image|mimes:jpeg,png,jpg|max:2048',  // صورة الوجه الخلفي
    ]);

    $identifier = $request->identifier;

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $email = $identifier;
        $phone = null;
        
        if (User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'البريد الإلكتروني مستخدم مسبقاً.'], 422);
        }
    } else {
        $phone = $identifier;
        $email = null;
        
        if (User::where('phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'رقم الهاتف مستخدم مسبقاً.'], 422);
        }
    }

    $frontImgPath = null;
    $backImgPath = null;

    if ($request->hasFile('id_img_front')) {
        $frontImgPath = $request->file('id_img_front')->store('settings', 'public');
    }
    if ($request->hasFile('id_img_back')) {
        $backImgPath = $request->file('id_img_back')->store('settings', 'public');
    }

    $user = User::create([
        'username'     => $request->username,
        'email'        => $email,
        'phone'        => $phone,
        'password'     => Hash::make($request->password),
        'role'         => 'user', 
        'is_blocked'   => false,  
        'id_img_front' => $frontImgPath, 
        'id_img_back'  => $backImgPath,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'تمت اضافة مستخدم بنجاح',
        'user'    => $user
    ], 201);
}
//اضافة مزود خدمة 
public function addProvider(Request $request)
{
    $request->validate([
        'username'     => 'required|string|max:255',
        'identifier'   => 'required|string', // إيميل أو هاتف
        'password'     => 'required|string|min:8',
        'id_img_front' => 'required|image|mimes:jpeg,png,jpg|max:2048', // صورة الوجه الأمامي
        'id_img_back'  => 'required|image|mimes:jpeg,png,jpg|max:2048',  // صورة الوجه الخلفي
    ]);

    $identifier = $request->identifier;

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $email = $identifier;
        $phone = null;
        
        if (User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'البريد الإلكتروني مستخدم مسبقاً.'], 422);
        }
    } else {
        $phone = $identifier;
        $email = null;
        
        if (User::where('phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'رقم الهاتف مستخدم مسبقاً.'], 422);
        }
    }

    $frontImgPath = null;
    $backImgPath = null;

    if ($request->hasFile('id_img_front')) {
        $frontImgPath = $request->file('id_img_front')->store('settings', 'public');
    }

    if ($request->hasFile('id_img_back')) {
        $backImgPath = $request->file('id_img_back')->store('settings', 'public');
    }

    $user = User::create([
        'username'     => $request->username,
        'email'        => $email,
        'phone'        => $phone,
        'password'     => Hash::make($request->password),
        'role'         => 'provider', 
        'is_blocked'   => false,  
        'id_img_front' => $frontImgPath, 
        'id_img_back'  => $backImgPath,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'تمت اضافة مستخدم بنجاح',
        'user'    => $user
    ], 201);
}
//عرض كل الحجوزات
public function getAllBookings(Request $request)
    {
        $query = Booking::query()->with(['user:id,username', 'provider:id,username']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date')) {
            $query->whereDate('booking_date', $request->date);
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
        }

        $bookings = $query->orderBy('booking_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $bookings->count(),
            'bookings' => $bookings
        ], 200);
    }

    // تعديل حالة أي حجز (مع إلغاء التضارب عند القبول)
public function updateBookingStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,confirmed,cancelled,completed'
        ]);

        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'الحجز غير موجود.'], 404);
        }

        if ($request->status === 'confirmed') {
            // إلغاء الحجوزات المتضاربة لنفس المزود ونفس الوقت
            Booking::where('provider_id', $booking->provider_id)
                ->where('id', '!=', $booking->id)
                ->where('booking_date', $booking->booking_date)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }

        $booking->status = $request->status;
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => $request->status === 'confirmed' 
                ? 'تم تأكيد الحجز من قبل الأدمن، وإلغاء الحجوزات المتضاربة تلقائياً.' 
                : 'تم تحديث حالة الحجز بنجاح.',
            'booking' => $booking
        ], 200);
    }

// 3. الحذف النهائي من قاعدة البيانات (Force Delete)
public function forceDeleteBooking($id)
{
    $booking = Booking::withTrashed()->find($id);

    if (!$booking) {
        return response()->json(['success' => false, 'message' => 'الحجز غير موجود مطلقاً.'], 404);
    }

    $booking->forceDelete(); // حظر وحذف نهائي لا يمكن التراجع عنه

    return response()->json([
        'success' => true,
        'message' => 'تم حذف الحجز نهائياً من قاعدة البيانات.'
    ], 200);
}

public function getMessages(Request $request)
    {
        $query = ContactMessage::query();

        // فلترة بحسب الحالة (read أو unread)
        if ($request->filled('status')) {
            $isRead = $request->status === 'read' ? true : false;
            $query->where('is_read', $isRead);
        }

        // 💡 تعديل: البحث باسم المرسل، إيميله، أو رقم هاتفه (مطابق للتصميم)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%"); // تم استبدال subject بـ phone
            });
        }

        $messages = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $messages->count(),
            'messages' => $messages
        ], 200);
    }

    // 2. عرض تفاصيل رسالة محددة وتحويلها تلقائياً إلى "مقروءة"
    public function showMessage($id)
    {
        $message = ContactMessage::find($id);

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'الرسالة غير موجودة.'], 404);
        }

        // تحويلها لمقروءة بمجرد فتحها
        $message->is_read = true;
        $message->save();

        return response()->json([
            'success' => true,
            'message' => $message
        ], 200);
    }

   
    // 4. حذف الرسالة
    public function destroyMessage($id)
    {
        $message = ContactMessage::find($id);

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'الرسالة غير موجودة.'], 404);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الرسالة بنجاح.'
        ], 200);
    }
//ارسال رسالة جماعية 
    public function sendBroadcastMessage(Request $request)
{
    $request->validate([
        'target' => 'required|string|in:all,users,providers',
        'type'   => 'required|string|in:email,sms,both',
        'title'  => 'required|string|min:5',
        'content'=> 'required|string|min:10',
    ]);
    $lockKey = 'broadcast_lock_' . md5($request->title . $request->content);
    if (Cache::has($lockKey)) {
        return response()->json([
            'success' => false,
            'message' => 'هذه الرسالة يتم إرسالها حالياً بالفعل! يرجى الانتظار حتى تنتهي العملية.'
        ], 429); 
    }
    Cache::put($lockKey, true, now()->addMinutes(5));
    $query = User::query();
    if ($request->target === 'users') {
        $query->where('role', 'user');
    } elseif ($request->target === 'providers') {
        $query->where('role', 'provider');
    }
    
    $recipients = $query->get();

    if ($recipients->isEmpty()) {
        Cache::forget($lockKey); 
        return response()->json(['success' => false, 'message' => 'لا يوجد مستخدمين لإرسال الرسالة إليهم.'], 404);
    }

    $emailCount = 0;
    $smsCount = 0;
    $uniqueEmails = [];
    $uniquePhones = [];

    foreach ($recipients as $recipient) {
            if (($request->type === 'email' || $request->type === 'both') && !empty($recipient->email)) {
            if (!in_array($recipient->email, $uniqueEmails)) {
                $userEmail = $recipient->email;
                $title = $request->title;
                $content = $request->content;

                Mail::raw($content, function ($mail) use ($userEmail, $title) {
                    $mail->to($userEmail)
                         ->subject("📢 إعلان هام: " . $title . " - Royal Moments");
                });

                $uniqueEmails[] = $recipient->email; 
                $emailCount++;
            }
        }
        if (($request->type === 'sms' || $request->type === 'both') && !empty($recipient->phone)) {
            if (!in_array($recipient->phone, $uniquePhones)) {
                $smsText = "📢 " . $request->title . "\n" . $request->content;
                
                $smsSuccess = $this->otpService->sendMessage($recipient->phone, $smsText);
                if ($smsSuccess) {
                    $uniquePhones[] = $recipient->phone; 
                    $smsCount++;
                }
            }
        }
    }
    Cache::forget($lockKey);

    return response()->json([
        'success' => true,
        'message' => "تمت عملية الإرسال الجماعي بنجاح وضمان عدم التكرار.",
        'details' => [
            'total_targets' => $recipients->count(),
            'unique_emails_sent' => $emailCount,
            'unique_sms_sent' => $smsCount
        ]
    ], 200);
}

// public function storeMessage(Request $request)
// {
  
//     $request->validate([
//         'message' => 'required|string|min:5',
//     ]);

 
//     $user = auth()->user();

//     $contactMessage = ContactMessage::create([
//     'name'    => $user->username ?? 'مستخدم غير مسمى', 
//     'email'   => $user->email ?? null,
//     'phone'   => $user->phone ?? null,
//     'message' => $request->message,
//     'is_read' => false,
//     'status'  => 'pending',
// ]);
//     return response()->json([
//         'success' => true,
//         'message' => 'تم إرسال رسالتك بنجاح، شكرًا لتواصلك معنا!',
//         'data'    => $contactMessage
//     ], 201);
// }
// جلب الإيرادات والأرباح الإجمالية حسب الفترة
public function getRevenueReport(Request $request)
{
    $period = $request->query('period', 'all'); // الافتراضي يعرض الكل (طالما)
    $query = Booking::where('status', 'completed');

    if ($period === 'daily') {
        $query->whereDate('created_at', now()->today());
    } elseif ($period === 'monthly') {
        $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
    } elseif ($period === 'yearly') {
        $query->whereYear('created_at', now()->year);
    }

    return response()->json([
        'success' => true,
        'period'  => $period,
        'total_revenue'   => $query->sum('total_amount'), // كل المصاري الي دخلت السيستم
        'app_net_profit'  => $query->sum('app_commission'), // صافي ربح التطبيق من العمولات
    ], 200);
}

// جلب قائمة العمولات والمبالغ الخاصة بكل مزود خدمة
public function getProvidersCommissions()
{
    $providers = User::where('role', 'provider')
        ->select('id', 'name')
        ->withSum(['bookings as total_collected' => function($q) {
            $q->where('status', 'completed');
        }], 'total_amount')
        ->withSum(['bookings as app_commission_earned' => function($q) {
            $q->where('status', 'completed');
        }], 'app_commission')
        ->get()
        ->map(function ($provider) {
            // حساب كم يتبقى للمزود صافي بعد خصم عمولة التطبيق
            $provider->provider_net_profit = ($provider->total_collected ?? 0) - ($provider->app_commission_earned ?? 0);
            return $provider;
        });

    return response()->json([
        'success' => true,
        'providers_commissions' => $providers
    ], 200);
}



}

