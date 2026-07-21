<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // لاگ کامل انتقال state گردش کار اضافه‌کار - جدا از AuditLog عمومی نگه داشته می‌شود
    // چون دامنه‌ی خاص خودش را دارد (چه کسی، چه زمانی، از چه وضعیتی به چه وضعیتی، با چه یادداشتی).
    public function up(): void
    {
        Schema::create('overtime_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_period_id')->constrained('overtime_periods')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_status_history');
    }
};
