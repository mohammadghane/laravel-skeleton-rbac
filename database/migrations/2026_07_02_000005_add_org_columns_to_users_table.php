<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // چارت سازمانی این پروژه: مدیرعامل -> معاونت -> واحد -> پرسنل (بعضی پرسنل بی‌واسطه
    // زیرمجموعه معاونت‌اند). فیلدهای center_id/agency_id قدیمی برای این ماژول استفاده
    // نمی‌شوند - عمداً دست‌نخورده باقی می‌مانند تا بخش‌های دیگر اپ که به آن‌ها وابسته‌اند نشکنند.
    //
    // قاعده‌ی اعتبارسنجی (در UserRequest/سرویس مربوطه اعمال شود، نه در DB):
    // دقیقاً یکی از unit_id / deputy_id باید پر باشد، نه هر دو و نه هیچ‌کدام.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('agency_id')->constrained('units')->nullOnDelete();
            $table->foreignId('deputy_id')->nullable()->after('unit_id')->constrained('deputies')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('deputy_id')->constrained('positions')->nullOnDelete();
            $table->foreignId('contract_type_id')->nullable()->after('position_id')->constrained('contract_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('deputy_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('contract_type_id');
        });
    }
};
