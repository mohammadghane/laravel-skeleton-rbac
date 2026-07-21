<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // مقدار واقعی هر آیتم ویژه برای هر پرسنل در هر ماه شمسی - تاریخچه‌ی کامل نگه داشته
    // می‌شود (یک رکورد به ازای هر پرسنل-آیتم-ماه)، نه فقط آخرین مقدار. اگر برای یک ماه
    // مقدار جدید ثبت نشود، مقدار پیش‌فرض آیتم (special_items.default_value) اعمال می‌شود.
    public function up(): void
    {
        Schema::create('special_item_monthly_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');
            $table->decimal('value', 8, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['special_item_id', 'personnel_id', 'jalali_year', 'jalali_month'],
                'special_item_value_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_item_monthly_values');
    }
};
