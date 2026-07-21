<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\DeputyRequest;
use App\Models\Deputy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD معاونت‌ها. طبق الگوی موجود پروژه (RoleController/UserController)، پاسخ‌ها JSON
 * هستند و روی همان Blade مودال فعلی سوار می‌شوند - این کنترلر view مجزا رندر نمی‌کند.
 * قالب response را با الگوی دقیق کنترلرهای فعلی پروژه (success/message/data) هماهنگ کنید.
 */
class DeputyController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $deputies = Deputy::with(['head', 'units'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('title')
            ->get();

        return response()->json(['success' => true, 'data' => $deputies]);
    }

    public function store(DeputyRequest $request): JsonResponse
    {
        $deputy = Deputy::create($request->validated());

        return response()->json(['success' => true, 'message' => __('معاونت با موفقیت ثبت شد.'), 'data' => $deputy]);
    }

    public function update(DeputyRequest $request, Deputy $deputy): JsonResponse
    {
        $deputy->update($request->validated());

        return response()->json(['success' => true, 'message' => __('معاونت با موفقیت ویرایش شد.'), 'data' => $deputy]);
    }

    public function destroy(Deputy $deputy): JsonResponse
    {
        if ($deputy->units()->exists() || $deputy->directPersonnel()->exists()) {
            return response()->json(['success' => false, 'message' => __('این معاونت دارای واحد یا پرسنل زیرمجموعه است و قابل حذف نیست.')], 422);
        }

        $deputy->delete();

        return response()->json(['success' => true, 'message' => __('معاونت حذف شد.')]);
    }
}
