@extends('layouts.admin')

@section('title', __('معاونت‌ها'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('معاونت‌ها'),
        'subtitle' => __('بالاترین سطح چارت سازمانی؛ هر واحد و هر پرسنل مستقیم زیرمجموعه‌ی یک معاونت است.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.deputies.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('معاونت جدید') . '</a>'
        ),
    ])

    <div class="mb-4">
        @include('admin.overtime.partials.search-box', ['route' => 'admin.overtime.deputies.index', 'placeholder' => __('عنوان معاونت…')])
    </div>

    <div id="deputies-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.overtime.deputies.partials.table', ['deputies' => $deputies])
    </div>
@endsection

@push('scripts')
<script>
    function reloadDeputyGrid() { window.location.reload(); }
</script>
@endpush
