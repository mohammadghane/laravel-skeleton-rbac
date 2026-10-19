@extends('layouts.admin')

@section('title', __('اضافه‌کار') . ' — ' . trim($personnel->name . ' ' . $personnel->family))

@section('content')
    @php
        $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20';
        $label = 'mb-1.5 block text-sm font-medium text-ink/70';
        $card = 'rounded-2xl border border-line bg-white p-5 shadow-card';

        $status = $period?->status;
        $specialHours = $period?->specialItemLines->where('unit_snap', 'hour')->sum('hours') ?? $specialItems->where('unit', 'hour')->sum('default_value');
        $normalStatusEditable = !$period || $status->editableByManager() || ($isDeputy && $status->allowsDeputyExcessEntry());
        $canManagerEdit = ($isManager || $isDeputy) && $windowOpen && $normalStatusEditable;
        $canDeputyReview = $isDeputy && $period && $status->editableByDeputy();
        $canDeputyEditExcess = $isDeputy && $period && $status->allowsDeputyExcessEntry();
        $canCeoReview = $isCeo && $period && $status->editableByCeo();
        $exceeds = $period?->exceedsOverallCeiling() ?? false;
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => trim($personnel->name . ' ' . $personnel->family),
        'subtitle' => implode(' — ', array_filter([
            $personnel->contractType?->title,
            $personnel->position?->title,
            $personnel->unit?->title ?? $personnel->deputy?->title,
            \App\Support\Jalali::periodLabel($year, $month),
        ])),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.overtime.periods.index', ['jalali_year' => $year, 'jalali_month' => $month]) . '" class="rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink/70 transition hover:bg-surface">' . __('بازگشت') . '</a>'
        ),
    ])

    {{-- وضعیت پنجره‌ی ثبت --}}
    @if (!$ceilingVersion)
        <div class="mb-4 rounded-xl border border-brick/30 bg-brick-100 px-4 py-3 text-sm text-brick">
            {{ __('برای نوع قرارداد این پرسنل در این ماه، سقف و روز فعال‌سازی تعریف نشده است؛ ثبت اضافه‌کار ممکن نیست.') }}
        </div>
    @elseif (!$windowOpen)
        <div class="mb-4 rounded-xl border border-gold/40 bg-gold-100 px-4 py-3 text-sm text-ink/80">
            {{ __('پنجره‌ی ثبت اضافه‌کار برای این ماه باز نیست. روز فعال‌سازی:') }}
            <span class="font-mono">{{ $ceilingVersion->activation_day }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ستون راست: خلاصه‌ی ساعت‌ها --}}
        <div class="space-y-4 lg:col-span-1">
            <div class="{{ $card }}">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('خلاصه‌ی ساعت‌ها') }}</h3>

                <dl class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('اضافه‌کار عادی') }}</dt>
                        <dd class="font-mono text-ink">{{ $period?->normal_hours ?? '0' }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('اضافه‌کار مازاد') }}</dt>
                        <dd class="font-mono text-ink">{{ $period?->excess_hours ?? '0' }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('آیتم‌های ویژه (ساعت)') }}</dt>
                        <dd class="font-mono text-ink">{{ number_format((int) $specialHours) }}</dd>
                    </div>

                    <div class="flex items-center justify-between border-t border-line pt-2.5">
                        <dt class="font-medium text-ink/70">{{ __('جمع کل (خام)') }}</dt>
                        <dd class="font-mono font-medium text-ink">{{ $period?->totalHours() ?? number_format((int) $specialHours) }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-ink/60">{{ __('سقف کلی قرارداد') }}</dt>
                        <dd class="font-mono text-ink/70">{{ $period?->overallCeiling() ?? ($ceilingVersion?->overall_ceiling ?? __('بدون سقف')) }}</dd>
                    </div>

                    {{-- عدد نهایی ارسالی به واحد مالی --}}
                    <div class="flex items-center justify-between rounded-lg {{ $exceeds ? 'bg-brick-100' : 'bg-pine-100' }} px-3 py-2">
                        <dt class="font-medium {{ $exceeds ? 'text-brick' : 'text-pine' }}">{{ __('قابل پرداخت') }}</dt>
                        <dd class="font-mono font-bold {{ $exceeds ? 'text-brick' : 'text-pine' }}">{{ $period?->payableHours() ?? number_format((int) $specialHours) }}</dd>
                    </div>
                </dl>

                @if ($exceeds)
                    <p class="mt-3 rounded-lg border border-brick/20 bg-brick-100 px-3 py-2 text-xs text-brick">
                        {{ __('مجموع ثبت‌شده از سقف کلی قرارداد بیشتر است؛ فقط مقدار سقف به واحد مالی ارسال می‌شود.') }}
                        ({{ __('کسر') }}: <span class="font-mono">{{ $period->cappedHours() }}</span> {{ __('ساعت') }})
                    </p>
                @endif

                @if ($period)
                    <div class="mt-4 border-t border-line pt-3">
                        <span class="text-xs text-ink/50">{{ __('وضعیت فعلی') }}:</span>
                        <div class="mt-1.5">@include('admin.overtime.partials.status-badge', ['status' => $period->status])</div>
                    </div>
                @endif
            </div>

            {{-- پارامترهای مستقل مالی --}}
            @if ($period)
                <div class="{{ $card }}">
                    <h3 class="mb-3 text-sm font-semibold text-ink">{{ __('پارامترهای مستقل') }}</h3>
                    <dl class="space-y-2 text-xs">
                        <div class="flex justify-between"><dt class="text-ink/50">{{ __('کسر کار') }}</dt><dd class="font-mono text-ink/70">{{ $period->deduction_hours ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink/50">{{ __('ماموریت') }}</dt><dd class="font-mono text-ink/70">{{ $period->mission_hours ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink/50">{{ __('ضریب ریالی') }}</dt><dd class="font-mono text-ink/70">{{ $period->rial_coefficient ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink/50">{{ __('روز کارکرد') }}</dt><dd class="font-mono text-ink/70">{{ $period->worked_days_count ?? '—' }}</dd></div>
                    </dl>
                    <p class="mt-2 text-[11px] text-ink/40">{{ __('کسر کار و ماموریت فقط از طریق فایل اکسل وارد می‌شوند.') }}</p>
                </div>
            @endif
        </div>

        {{-- ستون چپ: اقدام‌ها --}}
        <div class="space-y-4 lg:col-span-2">

            {{-- مدیر واحد و معاونت هر دو می‌توانند برای پرسنل زیرمجموعه ثبت عادی کنند. --}}
            @if ($isManager || $isDeputy)
                <div class="{{ $card }}">
                    <h3 class="mb-4 text-base font-semibold text-ink">{{ __('ثبت اضافه‌کار عادی') }}</h3>

                    @if ($canManagerEdit)
                        <form class="js-ajax-form space-y-4" method="POST" action="{{ route('admin.overtime.periods.normal.store', $personnel) }}" data-callback="reloadPeriod">
                            @csrf
                            <input type="hidden" name="jalali_year" value="{{ $year }}">
                            <input type="hidden" name="jalali_month" value="{{ $month }}">

                            <div>
                                <label class="{{ $label }}">{{ __('ساعت اضافه‌کار عادی') }}</label>
                                <input class="{{ $input }} font-mono" name="normal_hours" type="number" step="1" min="0" required value="{{ $period?->normal_hours ?? '' }}">
                                <span class="mt-1 block text-xs text-ink/40">
                                    {{ __('ثبت بیش از سقف فردی مجاز است؛ محدودیت فقط روی مجموع گروه اعمال می‌شود.') }}
                                </span>
                            </div>

                            <button type="submit" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('ذخیره') }}</button>
                        </form>

                        @if ($period && $period->status->editableByManager())
                            <form class="js-ajax-form mt-4 border-t border-line pt-4" method="POST" action="{{ route('admin.overtime.periods.submit', $period) }}" data-callback="reloadPeriod">
                                @csrf
                                <p class="mb-3 text-xs text-ink/50">{{ __('پس از ارسال برای معاونت، امکان ویرایش برای شما قفل می‌شود.') }}</p>
                                <button type="submit" class="rounded-lg border border-pine bg-pine-100 px-4 py-2.5 text-sm font-medium text-pine hover:bg-pine/10">{{ __('ارسال برای تایید معاونت') }}</button>
                            </form>
                        @endif
                    @else
                        {{-- توضیح دقیقِ دلیل: پیام مبهم باعث می‌شد کاربر فکر کند صفحه فقط
                             نمایشی است، در حالی که علت مشخصی داشت. --}}
                        <div class="rounded-lg border border-gold/40 bg-gold-100 px-3 py-2.5 text-sm text-ink/80">
                            @if (!$ceilingVersion)
                                {{ __('برای نوع قرارداد این پرسنل در این ماه سقفی تعریف نشده است؛ تا زمانی که منابع انسانی سقف و روز فعال‌سازی را ثبت نکند، امکان ثبت اضافه‌کار وجود ندارد.') }}
                            @elseif (!$windowOpen)
                                {{ __('پنجره‌ی ثبت اضافه‌کار برای این ماه باز نیست.') }}
                                <span class="font-mono">({{ __('روز فعال‌سازی') }}: {{ $ceilingVersion->activation_day }})</span>
                            @else
                                {{ __('این دوره در وضعیت «:status» است و برای ثبت اضافه‌کار عادی قابل ویرایش نیست.', ['status' => $period?->status->label()]) }}
                            @endif
                        </div>
                    @endif
                </div>
            @else
                {{-- کاربری که مدیرِ این پرسنل نیست: به‌جای صفحه‌ی صرفاً نمایشی و بی‌توضیح،
                     صریح می‌گوییم چرا فرم ثبت را نمی‌بیند و چه کسی می‌تواند ثبت کند. --}}
                <div class="{{ $card }}">
                    <h3 class="mb-3 text-base font-semibold text-ink">{{ __('ثبت اضافه‌کار') }}</h3>

                    <div class="rounded-lg border border-gold/40 bg-gold-100 px-3 py-2.5 text-sm text-ink/80">
                        @if (!$personnel->unit_id && !$personnel->deputy_id)
                            {{ __('این پرسنل به هیچ واحد یا معاونتی تخصیص نیافته است. تا زمانی که جایگاه سازمانی او مشخص نشود، هیچ‌کس نمی‌تواند برایش اضافه‌کار ثبت کند.') }}
                            @permission('admin/users')
                                <a href="{{ route('admin.users.edit', $personnel) }}" class="open-modal ms-1 text-pine underline">{{ __('تخصیص واحد') }}</a>
                            @endpermission
                        @elseif ($personnel->unit && !$personnel->unit->manager_user_id)
                            {{ __('برای واحد «:unit» مدیر تعیین نشده است؛ ثبت اضافه‌کار بر عهده‌ی مدیر واحد است.', ['unit' => $personnel->unit->title]) }}
                            @permission('overtime/org-chart-manage')
                                <a href="{{ route('admin.overtime.units.index') }}" class="ms-1 text-pine underline">{{ __('تعیین مدیر واحد') }}</a>
                            @endpermission
                        @else
                            {{ __('ثبت اضافه‌کار عادی بر عهده‌ی مدیر واحد «:manager» است؛ شما فقط دسترسی مشاهده دارید.', [
                                'manager' => $personnel->unit?->manager
                                    ? trim($personnel->unit->manager->name . ' ' . $personnel->unit->manager->family)
                                    : __('مربوطه'),
                            ]) }}
                        @endif
                    </div>
                </div>
            @endif

            {{-- معاونت: اضافه‌کار مازاد + تایید/رد --}}
            @if ($canDeputyReview || $canDeputyEditExcess)
                <div class="{{ $card }}">
                    <h3 class="mb-4 text-base font-semibold text-ink">{{ $canDeputyReview ? __('بررسی معاونت') : __('ثبت اضافه‌کار مازاد معاونت') }}</h3>

                    <form class="js-ajax-form mb-4 space-y-3 border-b border-line pb-4" method="POST" action="{{ route('admin.overtime.periods.excess.store', $period) }}" data-callback="reloadPeriod">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="{{ $label }}">{{ __('ثبت اضافه‌کار مازاد') }}</label>
                            <input class="{{ $input }} font-mono" name="excess_hours" type="number" step="1" min="0" required value="{{ $period->excess_hours }}">
                            <span class="mt-1 block text-xs text-ink/40">{{ __('می‌توانید مازاد را در پیش‌نویس یا پس از ارسال دوره ذخیره کنید؛ این کار به‌تنهایی دوره را تأیید نمی‌کند.') }}</span>
                        </div>
                        <button type="submit" class="rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink/70 hover:bg-surface">{{ __('ذخیره‌ی مازاد بدون تأیید دوره') }}</button>
                    </form>

                    @if ($canDeputyReview)
                    <form class="js-ajax-form space-y-4" method="POST" action="{{ route('admin.overtime.periods.deputy-review', $period) }}" data-callback="reloadPeriod">
                        @csrf
                        <div>
                            <label class="{{ $label }}">{{ __('توضیح (اختیاری)') }}</label>
                            <input class="{{ $input }}" name="note" value="">
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="approve" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('تایید و ارسال به مدیرعامل') }}</button>
                            <button type="submit" name="decision" value="reject" class="rounded-lg border border-brick/30 bg-brick-100 px-4 py-2.5 text-sm font-medium text-brick hover:bg-brick/10">{{ __('رد و بازگشت به مدیر واحد') }}</button>
                        </div>
                    </form>
                    @endif
                </div>
            @endif

            {{-- مدیرعامل: تایید/رد نهایی --}}
            @if ($canCeoReview)
                <div class="{{ $card }}">
                    <h3 class="mb-4 text-base font-semibold text-ink">{{ __('تایید نهایی مدیرعامل') }}</h3>

                    <form class="js-ajax-form space-y-4" method="POST" action="{{ route('admin.overtime.periods.ceo-review', $period) }}" data-callback="reloadPeriod">
                        @csrf

                        <div>
                            <label class="{{ $label }}">{{ __('توضیح (اختیاری)') }}</label>
                            <input class="{{ $input }}" name="note" value="">
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="approve" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600">{{ __('تایید نهایی') }}</button>
                            <button type="submit" name="decision" value="reject" class="rounded-lg border border-brick/30 bg-brick-100 px-4 py-2.5 text-sm font-medium text-brick hover:bg-brick/10">{{ __('رد و بازگشت به معاونت') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- آیتم‌های ویژه --}}
            <div class="{{ $card }}">
                <h3 class="mb-4 text-base font-semibold text-ink">{{ __('آیتم‌های ویژه') }}</h3>

                @if ($period && $period->specialItemLines->isNotEmpty())
                    <div class="mb-4 overflow-x-auto">
                        <table class="w-full text-start text-sm">
                            <thead>
                                <tr class="border-b border-line text-xs text-ink/50">
                                    <th class="px-3 py-2 font-medium">{{ __('عنوان') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('مقدار') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('واحد') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('وضعیت تایید') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($period->specialItemLines as $line)
                                    <tr>
                                        <td class="px-3 py-2 text-ink">{{ $line->title_snap }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-ink/70">{{ $line->hours }}</td>
                                        <td class="px-3 py-2 text-xs text-ink/60">{{ $line->unit_snap === 'day' ? __('روز') : __('ساعت') }}</td>
                                        <td class="px-3 py-2">
                                            @if ($line->deputy_approved)
                                                <span class="rounded-full border border-pine/30 bg-pine-100 px-2 py-0.5 text-[11px] font-medium text-pine">{{ __('تاییدشده') }}</span>
                                            @else
                                                <span class="rounded-full border border-gold/40 bg-gold-100 px-2 py-0.5 text-[11px] font-medium text-ink/80">{{ __('در انتظار تایید معاونت') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    @if ($specialItems->isNotEmpty())
                        <div class="mb-4 overflow-x-auto">
                            <table class="w-full text-start text-sm"><thead><tr class="border-b border-line text-xs text-ink/50"><th class="px-3 py-2">{{ __('عنوان') }}</th><th class="px-3 py-2">{{ __('مقدار') }}</th><th class="px-3 py-2">{{ __('واحد') }}</th><th class="px-3 py-2">{{ __('وضعیت تایید') }}</th></tr></thead>
                                <tbody class="divide-y divide-line">@foreach ($specialItems as $item)<tr><td class="px-3 py-2">{{ $item->title }}</td><td class="px-3 py-2 font-mono">{{ $item->default_value }}</td><td class="px-3 py-2 text-xs text-ink/60">{{ $item->unit === 'day' ? __('روز') : __('ساعت') }}</td><td class="px-3 py-2 text-xs text-pine">{{ __('تاییدشده') }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    @else
                        <p class="mb-4 text-sm text-ink/40">{{ __('آیتم ویژه‌ای ثبت نشده است.') }}</p>
                    @endif
                @endif

                @if ($specialItems->isNotEmpty())
                    <div class="border-t border-line pt-3 text-xs text-ink/60">
                        {{ __('آیتم‌های مشمول این پرسنل با مقدار ثابت تعیین‌شده توسط منابع انسانی در بالا نمایش داده شده‌اند؛ مدیر و معاون امکان ویرایش مقدار را ندارند.') }}
                    </div>
                @endif
            </div>

            {{-- تاریخچه‌ی گردش کار (الزام ردیابی بند ۵) --}}
            @if ($period && $period->statusHistory->isNotEmpty())
                <div class="{{ $card }}">
                    <h3 class="mb-4 text-base font-semibold text-ink">{{ __('تاریخچه‌ی گردش کار') }}</h3>

                    <ol class="space-y-3">
                        @foreach ($period->statusHistory as $history)
                            <li class="flex items-start gap-3 text-sm">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-pine"></span>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @include('admin.overtime.partials.status-badge', ['status' => $history->to_status])
                                        <span class="text-xs text-ink/40">{{ $history->created_at?->format('Y/m/d H:i') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-ink/50">
                                        {{ $history->actor ? trim($history->actor->name . ' ' . $history->actor->family) : __('سیستم') }}
                                        @if ($history->note)
                                            — {{ $history->note }}
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadPeriod() { window.location.reload(); }
</script>
@endpush
