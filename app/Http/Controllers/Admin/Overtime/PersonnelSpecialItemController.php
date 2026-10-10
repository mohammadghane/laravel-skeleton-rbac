<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\PersonnelSpecialItemRequest;
use App\Models\SpecialItem;
use App\Models\User;
use App\Services\OvertimePeriodService;
use Illuminate\Http\JsonResponse;

/** تعیین می‌کند کدام پرسنل مشمول کدام آیتم ویژه است - از پروفایل پرسنلی فراخوانی می‌شود. */
class PersonnelSpecialItemController extends Controller
{

    public function index(User $personnel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $personnel->load('unit')->only(['id']) + [
                'items' => \App\Models\PersonnelSpecialItem::with('specialItem')->where('personnel_id', $personnel->id)->get(),
            ],
        ]);
    }

    public function update(PersonnelSpecialItemRequest $request, User $personnel, SpecialItem $specialItem): JsonResponse
    {
        $record = \App\Models\PersonnelSpecialItem::updateOrCreate(
            ['personnel_id' => $personnel->id, 'special_item_id' => $specialItem->id],
            ['is_eligible' => $request->boolean('is_eligible'), 'assigned_by' => $request->user()->id]
        );
        app(OvertimePeriodService::class)->syncSpecialItem($specialItem);

        return response()->json(['success' => true, 'message' => __('مشمولیت آیتم ویژه به‌روزرسانی شد.'), 'data' => $record]);
    }
}
