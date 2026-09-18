<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نظرهای ثبت‌شده روی نقشه.
 *
 * مختصاتِ انتخابی کاربر و نتیجه‌ی بررسی مجاز بودن هر دو ذخیره می‌شوند - نه فقط
 * ارجاع به موقعیت: شعاع یا مختصات یک موقعیت ممکن است بعداً عوض شود و آن‌وقت
 * دیگر نمی‌شد فهمید نظر در لحظه‌ی ثبت مجاز بوده یا نه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('body');

            // اسنپ‌شات لحظه‌ی ثبت
            $table->boolean('was_allowed');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('distance_meters')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_comments');
    }
};
