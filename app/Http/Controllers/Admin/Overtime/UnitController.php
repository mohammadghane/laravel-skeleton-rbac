<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\UnitRequest;
use App\Models\Deputy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{

    use BulkDeletes;

    public function index(Request $request): View
    {
        $units = Unit::with(['deputy', 'manager'])
            ->when($request->filled('deputy_id'), fn ($q) => $q->where('deputy_id', $request->input('deputy_id')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('title')
            ->get();

        $deputies = Deputy::orderBy('title')->get();

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.overtime.units.partials.table', compact('units'));
        }

        return view('admin.overtime.units.index', compact('units', 'deputies'));
    }

    public function edit(Request $request, ?Unit $unit = null): View
    {
        $data = [
            'unit' => $unit,
            'deputies' => Deputy::orderBy('title')->get(),
            'candidates' => User::orderBy('name')->get(['id', 'name', 'family', 'username']),
        ];

        if ($request->ajax()) {
            return view('admin.overtime.units.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $unit?->exists ? __('ویرایش واحد') : __('واحد جدید'),
            'view' => 'admin.overtime.units.form',
            'data' => $data,
        ]);
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

    /** حذف گروهی - همان قواعد حفاظتیِ destroy() برای هر رکورد بررسی می‌شود. */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->bulkDelete(
            $request,
            Unit::class,
            fn (Unit $u) => $u->personnel()->exists() ? __('دارای پرسنل زیرمجموعه') : null,
            __('واحد'),
        );
    }
}
