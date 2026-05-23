<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ServiceImage extends Model
{
   use HasFactory;

    protected $fillable = ['service_id', 'image_path'];

    // الصورة تتبع لخدمة معينة
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
