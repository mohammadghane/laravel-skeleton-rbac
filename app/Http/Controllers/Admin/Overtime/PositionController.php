<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\PositionRequest;
use App\Models\Position;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{

    use BulkDeletes;

    public function index(Request $request): View
    {
        // سقف مازادِ ماه جاری هر سمت کنار خودش نمایش داده می‌شود تا HR برای دیدن آن
        // مجبور به باز کردن صفحه‌ی جداگانه نباشد.
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        // مثل انواع قرارداد: سقف ممکن است از ماه‌های قبل ارث رسیده باشد، نه اینکه برای
        // همین ماه ثبت شده باشد. این تمایز در UI نمایش داده می‌شود.
        $positions = Position::withCount('personnel')
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('title')->get()
            ->each(function (Position $p) use ($year, $month) {
                $ceiling = $p->ceilingFor($year, $month);
                $p->setAttribute('current_ceiling', $ceiling);
                $p->setAttribute('ceiling_is_inherited', $ceiling
                    && !($ceiling->jalali_year === $year && $ceiling->jalali_month === $month));
            });

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.overtime.positions.partials.table', compact('positions', 'year', 'month'));
        }

        return view('admin.overtime.positions.index', compact('positions', 'year', 'month'));
    }

    public function edit(Request $request, ?Position $position = null): View
    {
        $data = ['position' => $position];

        if ($request->ajax()) {
            return view('admin.overtime.positions.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $position?->exists ? __('ویرایش سمت سازمانی') : __('سمت سازمانی جدید'),
            'view' => 'admin.overtime.positions.form',
            'data' => $data,
        ]);
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

    /** حذف گروهی - همان قواعد حفاظتیِ destroy() برای هر رکورد بررسی می‌شود. */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->bulkDelete(
            $request,
            Position::class,
            fn (Position $p) => $p->personnel()->exists() ? __('پرسنلی با این سمت وجود دارد') : null,
            __('سمت سازمانی'),
        );
    }
}
