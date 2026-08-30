@extends('layouts.admin')

@section('title', __('آیتم‌های ویژه'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('آیتم‌های امتیازی ویژه'),
        'subtitle' => __('آیتم‌هایی مانند ذیحسابی، ایثارگری، سرپرست خانوار و بازنشستگی که به‌صورت ساعت اضافه‌کار برای پرسنل واجد شرایط تعریف می‌شوند (بند ۴.۲).'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.special-items.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('آیتم جدید') . '</a>'
        ),
    ])

    <div class="mb-4">
        @include('admin.overtime.partials.search-box', ['route' => 'admin.overtime.special-items.index', 'placeholder' => __('عنوان آیتم…')])
    </div>

    <div x-data="bulkDelete(
        '{{ route('admin.overtime.special-items.bulk-destroy') }}',
        '{{ __('حذف :count آیتم انتخاب‌شده؟ آیتم‌هایی که برای پرسنلی تعریف شده‌اند حذف نمی‌شوند.') }}'
    )">
        @include('admin.overtime.partials.bulk-delete', ['label' => __('آیتم ویژه')])

    <div id="special-items-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.overtime.special-items.partials.table', ['items' => $items])
    </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadSpecialItemGrid() { window.location.reload(); }
</script>
@endpush
