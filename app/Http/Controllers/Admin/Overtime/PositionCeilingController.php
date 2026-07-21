<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\PositionCeilingRequest;
use App\Models\Position;
use Illuminate\Http\JsonResponse;

/** ثبت سقف اضافه‌کار مازاد برای یک سمت سازمانی در یک ماه شمسی - فقط مدیر منابع انسانی. */
class PositionCeilingController extends Controller
{

    public function index(Position $position): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $position->ceilingVersions()->orderByDesc('jalali_year')->orderByDesc('jalali_month')->get(),
        ]);
    }

    public function store(PositionCeilingRequest $request, Position $position): JsonResponse
    {
        $version = $position->ceilingVersions()->updateOrCreate(
            ['jalali_year' => $request->input('jalali_year'), 'jalali_month' => $request->input('jalali_month')],
            [
                'ceiling_per_person' => $request->input('ceiling_per_person'),
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json(['success' => true, 'message' => __('سقف سمت سازمانی ثبت شد.'), 'data' => $version]);
    }
}
