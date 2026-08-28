{{--
    سربرگ مشترک صفحات ماژول اضافه‌کار - همان ساختار سربرگ صفحه‌ی کاربران
    (resources/views/admin/users/index.blade.php) تا کل پنل یکدست بماند.

    پارامترها:
      $title    عنوان صفحه
      $subtitle توضیح یک‌خطی زیر عنوان (اختیاری)
      $action   اسلات دکمه‌ی سمت چپ سربرگ (اختیاری) - معمولاً «+ مورد جدید»
--}}
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-xl font-bold text-ink">{{ $title }}</h2>
        @isset($subtitle)
            <p class="mt-1 text-sm text-ink/50">{{ $subtitle }}</p>
        @endisset
    </div>

    @isset($action)
        <div class="flex items-center gap-2">{{ $action }}</div>
    @endisset
</div>
