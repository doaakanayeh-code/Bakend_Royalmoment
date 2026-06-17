<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('bookings', function (Blueprint $table) {
        $table->id();
        // ربط الحجز بالمستخدم
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
        // ربط الحجز بالخدمة (السيارة أو الصالة)
        $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
        
    // في ملف الـ migration الخاص بـ bookings
$table->dateTime('start_time'); 
$table->dateTime('end_time');

        $table->decimal('total_price', 10, 2); // السعر الإجمالي
        
        // حالة الحجز: (pending, confirmed, cancelled)
        $table->string('status')->default('pending');
        
        // معلومات الدفع (سنتركها فارغة حالياً لربط Stripe لاحقاً)
        $table->string('payment_method')->nullable(); // stripe, cash, transfer
        $table->string('transaction_id')->nullable(); // المعرف القادم من Stripe
        $table->json('options')->nullable();
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
