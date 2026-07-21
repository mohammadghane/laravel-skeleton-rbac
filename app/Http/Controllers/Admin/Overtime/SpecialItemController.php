<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\SpecialItemRequest;
use App\Models\SpecialItem;
use Illuminate\Http\JsonResponse;

class SpecialItemController extends Controller
{

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => SpecialItem::orderBy('title')->get()]);
    }

    public function store(SpecialItemRequest $request): JsonResponse
    {
        $item = SpecialItem::create($request->validated());

        return response()->json(['success' => true, 'message' => __('آیتم ویژه با موفقیت تعریف شد.'), 'data' => $item]);
    }

    public function update(SpecialItemRequest $request, SpecialItem $specialItem): JsonResponse
    {
        $specialItem->update($request->validated());

        return response()->json(['success' => true, 'message' => __('آیتم ویژه با موفقیت ویرایش شد.'), 'data' => $specialItem]);
    }

    public function destroy(SpecialItem $specialItem): JsonResponse
    {
        if ($specialItem->eligiblePersonnel()->exists()) {
            return response()->json(['success' => false, 'message' => __('این آیتم برای پرسنلی تعریف شده و قابل حذف نیست؛ به‌جای حذف، غیرفعالش کنید.')], 422);
        }

        $specialItem->delete();

        return response()->json(['success' => true, 'message' => __('آیتم ویژه حذف شد.')]);
    }
}
