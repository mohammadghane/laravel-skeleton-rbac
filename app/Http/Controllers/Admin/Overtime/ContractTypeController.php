<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\ContractTypeRequest;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;

class ContractTypeController extends Controller
{

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ContractType::orderBy('title')->get()]);
    }

    public function store(ContractTypeRequest $request): JsonResponse
    {
        $contractType = ContractType::create($request->validated());

        return response()->json(['success' => true, 'message' => __('نوع قرارداد با موفقیت ثبت شد.'), 'data' => $contractType]);
    }

    public function update(ContractTypeRequest $request, ContractType $contractType): JsonResponse
    {
        $contractType->update($request->validated());

        return response()->json(['success' => true, 'message' => __('نوع قرارداد با موفقیت ویرایش شد.'), 'data' => $contractType]);
    }

    public function destroy(ContractType $contractType): JsonResponse
    {
        if ($contractType->personnel()->exists()) {
            return response()->json(['success' => false, 'message' => __('پرسنلی با این نوع قرارداد وجود دارد و قابل حذف نیست.')], 422);
        }

        $contractType->delete();

        return response()->json(['success' => true, 'message' => __('نوع قرارداد حذف شد.')]);
    }
}
