<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
     Schema::create('service_images', function (Blueprint $table) {
        $table->id();
        // الربط مع جدول الخدمات الذي سننشئه
        $table->foreignId('service_id')->constrained()->onDelete('cascade'); 
        $table->string('image_path'); // مسار الصورة في السيرفر
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_images');
    }
};
