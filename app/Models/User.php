<?php

namespace App\Models;
use Laravel\Sanctum\HasApiTokens;
//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
//use Laravel\Passport\HasApiTokens;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
