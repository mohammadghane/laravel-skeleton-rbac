<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\PositionRequest;
use App\Models\Position;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Position::orderBy('title')->get()]);
    }

    public function store(PositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());

        return response()->json(['success' => true, 'message' => __('سمت سازمانی با موفقیت ثبت شد.'), 'data' => $position]);
    }

    public function update(PositionRequest $request, Position $position): JsonResponse
    {
        $position->update($request->validated());

        return response()->json(['success' => true, 'message' => __('سمت سازمانی با موفقیت ویرایش شد.'), 'data' => $position]);
    }

    public function destroy(Position $position): JsonResponse
    {
        if ($position->personnel()->exists()) {
            return response()->json(['success' => false, 'message' => __('پرسنلی با این سمت وجود دارد و قابل حذف نیست.')], 422);
        }

        $position->delete();

        return response()->json(['success' => true, 'message' => __('سمت سازمانی حذف شد.')]);
    }
}
