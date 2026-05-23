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
           Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // لتخزين (مصور، كيك، إلخ)
        $table->string('slug')->unique(); // للروابط البرمجية (photographer, cake)
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
