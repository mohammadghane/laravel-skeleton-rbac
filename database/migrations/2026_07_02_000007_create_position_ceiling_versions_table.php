<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // سقف اضافه‌کار مازاد به ازای هر سمت سازمانی - نسخه‌بندی‌شده بر اساس سال/ماه شمسی،
    // مشابه contract_type_ceiling_versions. کنترل و ثبت این سقف در اختیار معاونت است،
    // نه مدیر واحد.
    public function up(): void
    {
        Schema::create('position_ceiling_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');
            $table->decimal('ceiling_per_person', 8, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['position_id', 'jalali_year', 'jalali_month'], 'position_ceiling_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_ceiling_versions');
    }
};
