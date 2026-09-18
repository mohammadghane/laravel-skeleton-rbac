<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موقعیت‌های جغرافیایی ثبت‌شده روی نقشه.
 *
 * مختصات به‌صورت decimal ذخیره می‌شود نه float: مقایسه و یکتایی با float قابل
 * اتکا نیست. دقت ۷ رقم اعشار ≈ ۱ سانتی‌متر که برای هر کاربرد سازمانی کافی است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
