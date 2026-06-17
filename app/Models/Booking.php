<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    // تم تنظيف الفواصل الزائدة وترتيب الحقول
    protected $fillable = [
        'user_id', 
        'service_id', 
        'start_time', 
        'end_time', 
        'total_price', 
        'status', 
        'options',
        'payment_method', 
        'transaction_id'
    ];
    protected $casts = [
    'start_time' => 'datetime',
    'end_time' => 'datetime',
    'options' => 'array',
];

    public function user() 
    {
        return $this->belongsTo(User::class);
    }

    public function service() 
    {
        return $this->belongsTo(Service::class);
    }
}