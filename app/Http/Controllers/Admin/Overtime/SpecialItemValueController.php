<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\SpecialItemValueRequest;
use App\Models\PersonnelSpecialItem;
use App\Models\SpecialItem;
use App\Models\SpecialItemMonthlyValue;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * ثبت مقدار ماهانه‌ی یک آیتم ویژه برای یک پرسنل (فقط توسط HR - نگاه کنید به
 * SpecialItemValueRequest::authorize). ثبت شدن این مقدار به معنای اعمال آن روی
 * گردش کار اضافه‌کار نیست؛ آن کار توسط OvertimePeriodController::applySpecialItem
 * انجام می‌شود (که این مقدار را به عنوان پیش‌فرض می‌خواند).
 */
class SpecialItemValueController extends Controller
{

    public function store(SpecialItemValueRequest $request, User $personnel, SpecialItem $specialItem): JsonResponse
    {
        $eligible = PersonnelSpecialItem::where('personnel_id', $personnel->id)
            ->where('special_item_id', $specialItem->id)
            ->where('is_eligible', true)
            ->exists();

        abort_unless($eligible, 422, __('این پرسنل مشمول این آیتم ویژه نیست.'));

        $value = SpecialItemMonthlyValue::updateOrCreate(
            [
                'special_item_id' => $specialItem->id,
                'personnel_id' => $personnel->id,
                'jalali_year' => $request->input('jalali_year'),
                'jalali_month' => $request->input('jalali_month'),
            ],
            ['value' => $request->input('value'), 'entered_by' => $request->user()->id]
        );

        return response()->json(['success' => true, 'message' => __('مقدار آیتم ویژه ثبت شد.'), 'data' => $value]);
    }
}
