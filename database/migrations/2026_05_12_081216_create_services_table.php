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
        Schema::create('services', function (Blueprint $table) {
        $table->id();
        // الربط مع المستخدم (المزود) الذي سجل دخول
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
        // الربط مع التصنيف (مصور، كيك، إلخ)
        $table->foreignId('category_id')->constrained()->onDelete('cascade');
        
        $table->string('title'); // اسم الخدمة (مثلاً: تصوير سيشن خارجي)
        $table->text('description'); // تفاصيل الخدمة
        $table->decimal('price_start', 10, 2); // السعر يبدأ من...
        
        // الموقع (مهم للقاعات والمنظمين)
        $table->string('city');
        $table->string('address');
        
        // حقل مرن (للقاعات نضع السعة، وللبقية يبقى فارغاً)
        $table->integer('capacity')->nullable(); 
        
        $table->enum('status', ['active', 'inactive'])->default('active');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
