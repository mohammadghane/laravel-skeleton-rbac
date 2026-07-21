<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // معاونت‌ها - سطح دوم چارت سازمانی (مدیرعامل -> معاونت -> واحد -> پرسنل).
    // هر معاونت می‌تواند چند Unit داشته باشد و همچنین پرسنلی که مستقیم زیرمجموعه
    // خودش هستند (بدون واحد میانی) - نگاه کنید به users.deputy_id.
    public function up(): void
    {
        Schema::create('deputies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deputies');
    }
};
