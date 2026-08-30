<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\DeputyRequest;
use App\Models\Deputy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD معاونت‌ها، دقیقاً با الگوی UserController/RoleController پروژه:
 * index یک صفحه‌ی Blade می‌دهد (یا با ajax=rows_only فقط partial جدول)، فرم ایجاد/ویرایش
 * داخل مودال مشترک `#admin-modal` رندر می‌شود و store/update/destroy پاسخ JSON
 * `{success, message}` برمی‌گردانند تا public/js/acl-admin.js آن را مدیریت کند.
 */
class DeputyController extends Controller
{

    use BulkDeletes;

    public function index(Request $request): View
    {
        $deputies = Deputy::with(['head', 'units'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('title')
            ->get();

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.overtime.deputies.partials.table', compact('deputies'));
        }

        return view('admin.overtime.deputies.index', compact('deputies'));
    }

    /** فرم ایجاد/ویرایش - داخل مودال مشترک پنل باز می‌شود. */
    public function edit(Request $request, ?Deputy $deputy = null): View
    {
        $data = ['deputy' => $deputy, 'candidates' => $this->headCandidates()];

        if ($request->ajax()) {
            return view('admin.overtime.deputies.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $deputy?->exists ? __('ویرایش معاونت') : __('معاونت جدید'),
            'view' => 'admin.overtime.deputies.form',
            'data' => $data,
        ]);
    }

    /** کاربرانی که می‌توانند به‌عنوان معاون انتخاب شوند. */
    private function headCandidates()
    {
        return User::orderBy('name')->get(['id', 'name', 'family', 'username']);
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

    /** حذف گروهی - همان قواعد حفاظتیِ destroy() برای هر رکورد بررسی می‌شود. */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->bulkDelete(
            $request,
            Deputy::class,
            fn (Deputy $d) => ($d->units()->exists() || $d->directPersonnel()->exists())
                ? __('دارای واحد یا پرسنل زیرمجموعه') : null,
            __('معاونت'),
        );
    }
}
