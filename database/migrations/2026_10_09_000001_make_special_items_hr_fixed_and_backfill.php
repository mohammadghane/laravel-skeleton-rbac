<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('special_items')->update([
            'submitter_role' => 'hr',
            'is_variable_per_personnel' => false,
        ]);

        DB::table('overtime_periods')
            ->join('personnel_special_items', function ($join) {
                $join->on('personnel_special_items.personnel_id', '=', 'overtime_periods.personnel_id')
                    ->where('personnel_special_items.is_eligible', true);
            })
            ->join('special_items', function ($join) {
                $join->on('special_items.id', '=', 'personnel_special_items.special_item_id')
                    ->where('special_items.is_active', true);
            })
            ->select([
                'overtime_periods.id as period_id',
                'special_items.id as item_id',
                'special_items.title',
                'special_items.default_value',
            ])
            ->orderBy('overtime_periods.id')->orderBy('special_items.id')
            ->cursor()->each(function ($row) {
                DB::table('overtime_special_item_lines')->updateOrInsert(
                    ['overtime_period_id' => $row->period_id, 'special_item_id' => $row->item_id],
                    [
                        'title_snap' => $row->title,
                        'hours' => $row->default_value,
                        'requires_deputy_approval' => false,
                        'deputy_approved' => true,
                        'entered_by' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            });

        DB::table('overtime_special_item_lines')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('overtime_periods')
                    ->join('personnel_special_items', function ($join) {
                        $join->on('personnel_special_items.personnel_id', '=', 'overtime_periods.personnel_id')
                            ->where('personnel_special_items.is_eligible', true);
                    })
                    ->join('special_items', function ($join) {
                        $join->on('special_items.id', '=', 'personnel_special_items.special_item_id')
                            ->where('special_items.is_active', true);
                    })
                    ->whereColumn('overtime_periods.id', 'overtime_special_item_lines.overtime_period_id')
                    ->whereColumn('personnel_special_items.special_item_id', 'overtime_special_item_lines.special_item_id');
            })->delete();
    }

    public function down(): void
    {
        // Existing role/value data cannot be restored safely after normalization.
    }
};
