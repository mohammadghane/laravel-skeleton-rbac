<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // واحدها - هر واحد دقیقاً زیرمجموعه یک معاونت است و دقیقاً یک مدیر دارد.
    // پرسنلِ بدون واحد (users.unit_id = null) مستقیم زیرمجموعه معاونت هستند.
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deputy_id')->constrained('deputies')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
