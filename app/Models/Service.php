<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'category_id', 
        'title', 
        'description', 
        'price_start', 
        'city', 
        'address', 
        'capacity', 
        'status'
    ];

    // الخدمة تتبع لمستخدم (المزود)
    public function provider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // الخدمة تتبع لتصنيف معين (مثلاً: تصوير)
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // الخدمة الواحدة لها ألبوم صور (جدول الصور)
    public function images()
    {
        return $this->hasMany(ServiceImage::class);
    }
}
