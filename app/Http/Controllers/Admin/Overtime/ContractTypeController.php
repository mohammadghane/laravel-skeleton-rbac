<?php

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\ContractTypeRequest;
use App\Models\ContractType;
use App\Support\Jalali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractTypeController extends Controller
{

    public function index(Request $request): View
    {
        // سقف ماه جاری (سقف هر نفر + سقف کلی + روز فعال‌سازی) کنار هر نوع قرارداد نمایش
        // داده می‌شود - این همان صفحه‌ای است که مدیر منابع انسانی بیشترین کار را با آن دارد.
        $year = (int) $request->input('jalali_year', Jalali::currentYear());
        $month = (int) $request->input('jalali_month', Jalali::currentMonth());

        // current_ceiling ممکن است نسخه‌ی همین ماه نباشد، بلکه از ماه‌های قبل به ارث رسیده
        // باشد (ceilingFor آخرین نسخه تا این ماه را برمی‌گرداند). این تمایز در UI مهم است،
        // پس علاوه بر خودِ نسخه، مشخص می‌کنیم که «برای همین ماه ثبت شده» یا «ارث‌بری شده».
        $contractTypes = ContractType::withCount('personnel')
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('title')->get()
            ->each(function (ContractType $t) use ($year, $month) {
                $ceiling = $t->ceilingFor($year, $month);
                $t->setAttribute('current_ceiling', $ceiling);
                $t->setAttribute('ceiling_is_inherited', $ceiling
                    && !($ceiling->jalali_year === $year && $ceiling->jalali_month === $month));
            });

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.overtime.contract-types.partials.table', compact('contractTypes', 'year', 'month'));
        }

        return view('admin.overtime.contract-types.index', compact('contractTypes', 'year', 'month'));
    }

    public function edit(Request $request, ?ContractType $contractType = null): View
    {
        $data = ['contractType' => $contractType];

        if ($request->ajax()) {
            return view('admin.overtime.contract-types.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $contractType?->exists ? __('ویرایش نوع قرارداد') : __('نوع قرارداد جدید'),
            'view' => 'admin.overtime.contract-types.form',
            'data' => $data,
        ]);
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
