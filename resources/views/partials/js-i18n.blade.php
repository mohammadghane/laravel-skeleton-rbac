@php
    $i18n = [
        'confirmDelete' => __('آیا از حذف این مورد اطمینان دارید؟'),
        'connectionFailed' => __('اتصال به سرور برقرار نشد.'),
        'submitFailed' => __('ثبت اطلاعات با خطا مواجه شد.'),
        'serverError' => __('خطا در برقراری ارتباط با سرور'),
        'unexpectedError' => __('خطای غیرمنتظره'),
    ];
@endphp
<script>
    window.i18n = @json($i18n);
</script>
