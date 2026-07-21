<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // سقف اضافه‌کار عادی به ازای هر نوع قرارداد - نسخه‌بندی‌شده بر اساس سال/ماه شمسی، چون
    // ممکن است ماه‌به‌ماه فرق کند. اگر برای یک ماه نسخه‌ی جدید ثبت نشود، آخرین نسخه‌ی قبلی
    // (fallback) به عنوان مقدار جاری در نظر گرفته می‌شود - این fallback در سرویس اعمال می‌شود.
    //
    // activation_day: از این روز از ماه شمسی به بعد، پنجره‌ی ثبت اضافه‌کار برای این نوع
    // قرارداد باز می‌شود (تا آخرین روز همان ماه).
    public function up(): void
    {
        Schema::create('contract_type_ceiling_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');
            $table->decimal('ceiling_per_person', 8, 2); // ساعت - اعشاری برای جلوگیری از خطای رند کردن
            $table->unsignedTinyInteger('activation_day');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_type_id', 'jalali_year', 'jalali_month'], 'contract_ceiling_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_type_ceiling_versions');
    }
};
