<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // تعیین می‌کند کدام پرسنل مشمول کدام آیتم ویژه است (بخش پروفایل پرسنلی).
    // مقدار ماهانه‌ی واقعی این مشمولیت در special_item_monthly_values ثبت می‌شود.
    public function up(): void
    {
        Schema::create('personnel_special_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('special_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_eligible')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['personnel_id', 'special_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_special_items');
    }
};
