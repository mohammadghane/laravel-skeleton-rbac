<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\SpecialItemRequest;
use App\Models\SpecialItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpecialItemController extends Controller
{

    use BulkDeletes;

    public function index(Request $request): View
    {
        $items = SpecialItem::withCount('eligiblePersonnel')
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('title')->get();

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.overtime.special-items.partials.table', compact('items'));
        }

        return view('admin.overtime.special-items.index', compact('items'));
    }

    public function edit(Request $request, ?SpecialItem $specialItem = null): View
    {
        $data = ['item' => $specialItem];

        if ($request->ajax()) {
            return view('admin.overtime.special-items.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $specialItem?->exists ? __('ویرایش آیتم ویژه') : __('آیتم ویژه‌ی جدید'),
            'view' => 'admin.overtime.special-items.form',
            'data' => $data,
        ]);
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

    /** حذف گروهی - همان قواعد حفاظتیِ destroy() برای هر رکورد بررسی می‌شود. */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->bulkDelete(
            $request,
            SpecialItem::class,
            fn (SpecialItem $i) => $i->eligiblePersonnel()->exists() ? __('برای پرسنلی تعریف شده است') : null,
            __('آیتم ویژه'),
        );
    }
}
