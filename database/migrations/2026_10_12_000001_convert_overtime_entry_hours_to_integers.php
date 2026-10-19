<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $hourColumns = [
        'overtime_periods' => ['normal_hours', 'excess_hours', 'normal_ceiling_snapshot', 'excess_ceiling_snapshot'],
        'overtime_special_item_lines' => ['hours'],
        'special_items' => ['default_value'],
        'special_item_monthly_values' => ['value'],
        'position_ceiling_versions' => ['ceiling_per_person', 'manager_ceiling_per_person'],
        'contract_type_ceiling_versions' => ['ceiling_per_person', 'overall_ceiling'],
        'deputy_capacity_transfers' => ['hours'],
    ];

    public function up(): void
    {
        foreach ($this->hourColumns as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->update([$column => DB::raw("ROUND($column)")]);
            }
        }

        Schema::table('overtime_periods', function (Blueprint $table) {
            $table->unsignedInteger('normal_hours')->default(0)->change();
            $table->unsignedInteger('excess_hours')->default(0)->change();
            $table->unsignedInteger('normal_ceiling_snapshot')->nullable()->change();
            $table->unsignedInteger('excess_ceiling_snapshot')->nullable()->change();
        });
        Schema::table('overtime_special_item_lines', function (Blueprint $table) {
            $table->unsignedInteger('hours')->change();
        });
        Schema::table('special_items', function (Blueprint $table) {
            $table->unsignedInteger('default_value')->default(0)->change();
        });
        Schema::table('special_item_monthly_values', function (Blueprint $table) {
            $table->unsignedInteger('value')->change();
        });
        Schema::table('position_ceiling_versions', function (Blueprint $table) {
            $table->unsignedInteger('ceiling_per_person')->change();
            $table->unsignedInteger('manager_ceiling_per_person')->nullable()->change();
        });
        Schema::table('contract_type_ceiling_versions', function (Blueprint $table) {
            $table->unsignedInteger('ceiling_per_person')->change();
            $table->unsignedInteger('overall_ceiling')->nullable()->change();
        });
        Schema::table('deputy_capacity_transfers', function (Blueprint $table) {
            $table->unsignedInteger('hours')->change();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_periods', function (Blueprint $table) {
            $table->decimal('normal_hours', 8, 2)->default(0)->change();
            $table->decimal('excess_hours', 8, 2)->default(0)->change();
            $table->decimal('normal_ceiling_snapshot', 8, 2)->nullable()->change();
            $table->decimal('excess_ceiling_snapshot', 8, 2)->nullable()->change();
        });
        Schema::table('overtime_special_item_lines', function (Blueprint $table) {
            $table->decimal('hours', 8, 2)->change();
        });
        Schema::table('special_items', function (Blueprint $table) {
            $table->decimal('default_value', 8, 2)->default(0)->change();
        });
        Schema::table('special_item_monthly_values', function (Blueprint $table) {
            $table->decimal('value', 8, 2)->change();
        });
        Schema::table('position_ceiling_versions', function (Blueprint $table) {
            $table->decimal('ceiling_per_person', 8, 2)->change();
            $table->decimal('manager_ceiling_per_person', 8, 2)->nullable()->change();
        });
        Schema::table('contract_type_ceiling_versions', function (Blueprint $table) {
            $table->decimal('ceiling_per_person', 8, 2)->change();
            $table->decimal('overall_ceiling', 8, 2)->nullable()->change();
        });
        Schema::table('deputy_capacity_transfers', function (Blueprint $table) {
            $table->decimal('hours', 8, 2)->change();
        });
    }
};
