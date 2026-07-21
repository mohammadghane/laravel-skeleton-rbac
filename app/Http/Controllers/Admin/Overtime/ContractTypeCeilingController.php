<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\ContractTypeCeilingRequest;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;

/**
 * ثبت سقف اضافه‌کار عادی + روز فعال‌سازی برای یک نوع قرارداد در یک ماه شمسی مشخص.
 * فقط مدیر منابع انسانی. هر (contract_type_id, jalali_year, jalali_month) یکتاست -
 * ثبت مجدد همان دوره یعنی ویرایش نسخه‌ی همان ماه (updateOrCreate)، نه نسخه‌ی جدید.
 */
class ContractTypeCeilingController extends Controller
{

    public function index(ContractType $contractType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $contractType->ceilingVersions()->orderByDesc('jalali_year')->orderByDesc('jalali_month')->get(),
        ]);
    }

    public function store(ContractTypeCeilingRequest $request, ContractType $contractType): JsonResponse
    {
        $version = $contractType->ceilingVersions()->updateOrCreate(
            ['jalali_year' => $request->input('jalali_year'), 'jalali_month' => $request->input('jalali_month')],
            [
                'ceiling_per_person' => $request->input('ceiling_per_person'),
                'activation_day' => $request->input('activation_day'),
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json(['success' => true, 'message' => __('سقف نوع قرارداد ثبت شد.'), 'data' => $version]);
    }
}
