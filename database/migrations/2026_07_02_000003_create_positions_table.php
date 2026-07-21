<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // سمت‌های سازمانی: کارشناس، مدیر، معاون و ... . سقف اضافه‌کار مازاد بر اساس این جدول محاسبه می‌شود
    // (نگاه کنید به position_ceiling_versions).
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
