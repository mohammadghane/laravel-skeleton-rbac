<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\UnitRequest;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $units = Unit::with(['deputy', 'manager'])
            ->when($request->filled('deputy_id'), fn ($q) => $q->where('deputy_id', $request->input('deputy_id')))
            ->orderBy('title')
            ->get();

        return response()->json(['success' => true, 'data' => $units]);
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $this->assertManagerNotAlreadyAssigned($request->input('manager_user_id'));

        $unit = Unit::create($request->validated());

        return response()->json(['success' => true, 'message' => __('واحد با موفقیت ثبت شد.'), 'data' => $unit]);
    }

    public function update(UnitRequest $request, Unit $unit): JsonResponse
    {
        $this->assertManagerNotAlreadyAssigned($request->input('manager_user_id'), excludeUnitId: $unit->id);

        $unit->update($request->validated());

        return response()->json(['success' => true, 'message' => __('واحد با موفقیت ویرایش شد.'), 'data' => $unit]);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->personnel()->exists()) {
            return response()->json(['success' => false, 'message' => __('این واحد دارای پرسنل زیرمجموعه است و قابل حذف نیست.')], 422);
        }

        $unit->delete();

        return response()->json(['success' => true, 'message' => __('واحد حذف شد.')]);
    }

    /** هر کاربر فقط می‌تواند مدیرِ دقیقاً یک واحد باشد. */
    private function assertManagerNotAlreadyAssigned(?int $managerUserId, ?int $excludeUnitId = null): void
    {
        if (!$managerUserId) {
            return;
        }

        $exists = Unit::where('manager_user_id', $managerUserId)
            ->when($excludeUnitId, fn ($q) => $q->where('id', '!=', $excludeUnitId))
            ->exists();

        abort_if($exists, 422, __('این کاربر هم‌اکنون مدیر یک واحد دیگر است.'));
    }
}
