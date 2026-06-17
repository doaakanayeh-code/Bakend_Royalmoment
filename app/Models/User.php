<?php

namespace App\Models;
use Laravel\Sanctum\HasApiTokens;
//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
//use Laravel\Passport\HasApiTokens;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as AuthCanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail, AuthCanResetPassword
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable,SoftDeletes, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>000000
     */
    protected $fillable = [
    'username',
    'email',
    'phone',
    'id_img_front',
    'id_img_back',
    'password',
    'role',    // <--- أضيفي هذا السطر هنا
    'google_id',
    'google_token',
     'ocr_text',
     'is_blocked',
    //'status',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'is_blocked'        => 'boolean',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // داخل ملف User.php
public function services()
{
    return $this->hasMany(Service::class, 'user_id');
}
}
