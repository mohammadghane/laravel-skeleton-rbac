<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // انواع قرارداد استخدامی: رسمی، شرکتی، حجمی، طرح، کار معین، متصدی ایستگاه و ...
    // سقف اضافه‌کار عادی (گروهی) بر اساس این جدول محاسبه می‌شود (نگاه کنید به contract_type_ceiling_versions).
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
