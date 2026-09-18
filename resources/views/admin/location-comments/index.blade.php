@extends('layouts.admin')

@section('title', __('ثبت نظر'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('ثبت نظر'),
        'subtitle' => __('ثبت نظر روی نقشه — فقط در محدوده‌ی مجاز موقعیت‌های تعریف‌شده.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.location-comments.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('نظر جدید') . '</a>'
        ),
    ])

    <div class="mb-4">
        @include('admin.overtime.partials.search-box', [
            'route' => 'admin.location-comments.index',
            'placeholder' => __('متن نظر، نام کاربر یا موقعیت…'),
        ])
    </div>

    <div x-data="bulkDelete(
        '{{ route('admin.location-comments.bulk-destroy') }}',
        '{{ __('حذف :count نظر انتخاب‌شده؟') }}'
    )">
        @include('admin.overtime.partials.bulk-delete', ['label' => __('نظر')])

        <div id="comments-table" class="rounded-2xl border border-line bg-white shadow-card">
            @include('admin.location-comments.partials.table', ['comments' => $comments])
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadCommentGrid() { window.location.reload(); }
</script>
@endpush
