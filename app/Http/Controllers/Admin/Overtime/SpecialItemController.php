<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\SpecialItemRequest;
use App\Models\SpecialItem;
use App\Models\User;
use App\Models\PersonnelSpecialItem;
use App\Services\OvertimePeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class SpecialItemController extends Controller
{

    use BulkDeletes;

    public function index(Request $request): View
    {
        $items = SpecialItem::withCount(['eligiblePersonnel as eligible_personnel_count' => fn ($q) => $q->where('is_eligible', true)])
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
        $item = SpecialItem::create($request->validated() + [
            'submitter_role' => SpecialItem::SUBMITTER_HR,
        ]);

        return response()->json(['success' => true, 'message' => __('آیتم ویژه با موفقیت تعریف شد.'), 'data' => $item]);
    }

    public function update(SpecialItemRequest $request, SpecialItem $specialItem): JsonResponse
    {
        $specialItem->update($request->validated() + [
            'submitter_role' => SpecialItem::SUBMITTER_HR,
        ]);
        app(OvertimePeriodService::class)->syncSpecialItem($specialItem);

        return response()->json(['success' => true, 'message' => __('آیتم ویژه با موفقیت ویرایش شد.'), 'data' => $specialItem]);
    }

    public function personnel(Request $request, SpecialItem $specialItem): View
    {
        $query = User::query()->with(['unit', 'deputy'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('family', 'like', $term)->orWhere('username', 'like', $term)
                    ->orWhere('personnel_code', 'like', $term));
            })->orderBy('name')->orderBy('family');

        $personnel = $query->paginate(100)->withQueryString();
        $eligibleIds = PersonnelSpecialItem::where('special_item_id', $specialItem->id)
            ->where('is_eligible', true)->pluck('personnel_id')->all();

        return view('admin.overtime.special-items.personnel', compact('specialItem', 'personnel', 'eligibleIds'));
    }

    public function syncPersonnel(Request $request, SpecialItem $specialItem): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'personnel_ids' => ['array'], 'personnel_ids.*' => ['integer', 'exists:users,id'],
            'retained_ids' => ['array'], 'retained_ids.*' => ['integer', 'exists:users,id'],
        ]);
        $ids = array_unique(array_map('intval', array_merge($data['personnel_ids'] ?? [], $data['retained_ids'] ?? [])));
        $pageIds = User::whereIn('id', $ids)->pluck('id')->all();

        // Bulk assignments set the complete eligible roster, so omitted personnel become ineligible.
        DB::transaction(function () use ($specialItem, $pageIds, $request) {
            PersonnelSpecialItem::where('special_item_id', $specialItem->id)->delete();
            foreach ($pageIds as $personnelId) {
                PersonnelSpecialItem::create([
                    'personnel_id' => $personnelId,
                    'special_item_id' => $specialItem->id,
                    'is_eligible' => true,
                    'assigned_by' => $request->user()->id,
                ]);
            }
        });
        app(OvertimePeriodService::class)->syncSpecialItem($specialItem);

        if (!$request->expectsJson()) {
            return redirect()->route('admin.overtime.special-items.index')->with('success', __('فهرست پرسنل مشمول ذخیره شد.'));
        }

        return response()->json(['success' => true, 'message' => __('فهرست پرسنل مشمول ذخیره شد.')]);
    }

    public function destroy(SpecialItem $specialItem): JsonResponse
    {
        if ($specialItem->eligiblePersonnel()->where('is_eligible', true)->exists()) {
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
            fn (SpecialItem $i) => $i->eligiblePersonnel()->where('is_eligible', true)->exists() ? __('برای پرسنلی تعریف شده است') : null,
            __('آیتم ویژه'),
        );
    }
}
