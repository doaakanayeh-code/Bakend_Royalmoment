<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    //
    protected $fillable = [
    'identifier',
    'email',
    'otp',
    'used',
    'expires_at'
];
}
