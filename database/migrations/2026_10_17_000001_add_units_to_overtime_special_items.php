<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_items', function (Blueprint $table) {
            $table->string('unit', 10)->default('hour')->after('default_value');
        });

        Schema::table('overtime_special_item_lines', function (Blueprint $table) {
            $table->string('unit_snap', 10)->default('hour')->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_special_item_lines', function (Blueprint $table) {
            $table->dropColumn('unit_snap');
        });

        Schema::table('special_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
