<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();

            // جعلنا رقم الهاتف nullable وأضفنا الإيميل nullable 
            // لكي يعمل الجدول مع الطريقتين (هاتف أو إيميل) بنفس المنطق
            $table->string('identifier')->nullable();
            $table->string('email')->nullable();

            $table->string('otp');
            $table->boolean('used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
