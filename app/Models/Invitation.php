<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;

    // هذه الحقول هي التي سيسمح لارافل بتعبئتها دفعة واحدة
    protected $fillable = [
        'guest_name',
        'code',
        'is_scanned',
        'scanned_at'
    ];
}