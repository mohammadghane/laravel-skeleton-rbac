<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // خطوط آیتم ویژه‌ی متصل به یک overtime_period مشخص (همان گردش کار اضافه‌کار عادی/مازاد).
    // title_snap چون عنوان آیتم ویژه ممکن است بعداً توسط HR ویرایش شود.
    // requires_deputy_approval از special_items.submitter_role در لحظه‌ی ثبت مشتق و کپی می‌شود
    // (submitter_role = unit_manager => true).
    public function up(): void
    {
        Schema::create('overtime_special_item_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_period_id')->constrained('overtime_periods')->cascadeOnDelete();
            $table->foreignId('special_item_id')->constrained()->cascadeOnDelete();
            $table->string('title_snap');
            $table->decimal('hours', 8, 2);
            $table->boolean('requires_deputy_approval')->default(false);
            $table->boolean('deputy_approved')->default(false);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_special_item_lines');
    }
};
