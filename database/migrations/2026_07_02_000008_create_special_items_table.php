<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // آیتم‌های امتیازی ویژه پرسنل - به صورت داینامیک توسط مدیر منابع انسانی تعریف می‌شوند
    // (ذیحسابی، ایثارگری، سرپرست خانوار، بازنشستگی، رفاهی و هر آیتم آینده).
    //
    // submitter_role تعیین می‌کند این آیتم در گردش تایید اضافه‌کار به چه شکل رفتار کند:
    //   'hr'           -> مستقیم اعمال می‌شود، نیازی به تایید مدیر/معاون ندارد (حتی روی
    //                     رکورد قفل‌شده هم می‌توان آن را زد).
    //   'unit_manager' -> باید توسط معاونت تایید شود (overtime_special_item_lines.requires_deputy_approval).
    public function up(): void
    {
        Schema::create('special_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_variable_per_personnel')->default(true); // false = مقدار یکسان برای همه پرسنل مشمول
            $table->decimal('default_value', 8, 2)->default(0);
            $table->enum('submitter_role', ['hr', 'unit_manager']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_items');
    }
};
