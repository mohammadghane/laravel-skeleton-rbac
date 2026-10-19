@extends('layouts.admin')

@section('title', __('ثبت و تایید اضافه‌کار'))

@section('content')
    @php
        $roleLabels = [
            'manager' => __('مدیر واحد — ثبت اضافه‌کار عادی'),
            'deputy' => __('معاونت — بررسی، اضافه‌کار مازاد و تایید'),
            'ceo' => __('مدیرعامل — تایید نهایی'),
            'hr' => __('منابع انسانی — مشاهده و پارامترهای مالی'),
            'self' => __('اضافه‌کار من'),
        ];

        // مدیر واحد و معاونت هر دو می‌توانند اضافه‌کار عادی ثبت کنند: معاونت «در
        // صورت لزوم» برای واحدهای زیرمجموعه‌اش ثبت می‌کند (مثلاً واحدی که مدیر
        // ندارد). برای بقیه‌ی نقش‌ها جدول فقط‌خواندنی می‌ماند.
        $canEditInline = in_array($role, ['manager', 'deputy'], true);
    @endphp

    @include('admin.overtime.partials.page-header', [
        'title' => __('ثبت و تایید اضافه‌کار'),
        'subtitle' => $scopeTitle . ' — ' . ($roleLabels[$role] ?? ''),
    ])

    <div
        x-data="overtimeEntry({
            bulkUrl: '{{ route('admin.overtime.periods.bulk-normal') }}',
            submitUrl: '{{ route('admin.overtime.periods.bulk-submit') }}',
            year: {{ $year }},
            month: {{ $month }},
            role: '{{ $role }}',
        })"
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                @include('admin.overtime.partials.month-filter', [
                    'year' => $year, 'month' => $month, 'route' => 'admin.overtime.periods.index',
                    'extra' => ['q' => request('q')],
                ])

                @include('admin.overtime.partials.search-box', [
                    'route' => 'admin.overtime.periods.index',
                    'placeholder' => __('نام یا کد پرسنلی…'),
                    'extra' => ['jalali_year' => $year, 'jalali_month' => $month],
                ])
            </div>

            <span class="rounded-lg border border-line bg-white px-3 py-2 text-xs text-ink/50">
                {{ __('دوره‌ی جاری') }}: <span class="font-mono text-ink/70">{{ \App\Support\Jalali::periodLabel($year, $month) }}</span>
            </span>
        </div>

        @if ($canEditInline)
            {{-- ثبت گروهی: یک عدد برای همه‌ی زیرمجموعه یا فقط ردیف‌های انتخاب‌شده. --}}
            <div class="mb-4 rounded-2xl border border-line bg-white p-4 shadow-card">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-ink/60" for="bulk-hours">
                                {{ __('اضافه‌کار گروهی (ساعت)') }}
                            </label>
                            <input
                                id="bulk-hours"
                                type="number" step="1" min="0"
                                x-model="bulkHours"
                                @keydown.enter.prevent="applyBulk()"
                                placeholder="{{ __('مثلاً ۶۰') }}"
                                class="w-40 rounded-lg border border-line px-3 py-2 font-mono text-sm focus:border-pine focus:outline-none"
                            >
                        </div>

                        <button
                            type="button"
                            @click="applyBulk()"
                            :disabled="busy || bulkHours === ''"
                            class="rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white transition hover:bg-pine/90 disabled:opacity-60"
                        >
                            <span x-show="!busy">
                                <template x-if="selected.length">
                                    <span>{{ __('ثبت برای') }} <span x-text="selected.length"></span> {{ __('نفر انتخاب‌شده') }}</span>
                                </template>
                                <template x-if="!selected.length">
                                    <span>{{ __('ثبت برای همه‌ی زیرمجموعه') }}</span>
                                </template>
                            </span>
                            <span x-show="busy">{{ __('در حال ثبت…') }}</span>
                        </button>

                        <label class="flex items-center gap-2 text-xs text-ink/60">
                            <input type="checkbox" x-model="overwrite" class="rounded border-line text-pine focus:ring-pine">
                            {{ __('بازنویسی مقادیر ثبت‌شده‌ی قبلی') }}
                        </label>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- متن مقصد ارسال متناسب با نقش کاربر تغییر می‌کند. --}}
                        <button
                            type="button"
                            @click="submitToDeputy()"
                            :disabled="busy"
                            class="rounded-lg border border-pine bg-pine-100 px-4 py-2 text-sm font-medium text-pine transition hover:bg-pine/10 disabled:opacity-60"
                        >
                            <span x-show="!busy">
                                <template x-if="selected.length">
                                    <span>{{ __('ارسال') }} <span x-text="selected.length"></span> {{ $role === 'deputy' ? __('مورد به مدیرعامل') : __('مورد به معاونت') }}</span>
                                </template>
                                <template x-if="!selected.length">
                                    <span>{{ $role === 'deputy' ? __('ارسال همه به مدیرعامل') : __('ارسال همه به معاونت') }}</span>
                                </template>
                            </span>
                            <span x-show="busy">{{ __('در حال ارسال…') }}</span>
                        </button>

                        <template x-if="selected.length">
                            <button type="button" @click="clearSelection()" class="text-xs text-ink/50 underline">
                                {{ __('لغو انتخاب') }}
                            </button>
                        </template>
                    </div>
                </div>

                <p class="mt-2 text-xs text-ink/40">
                    {{ __('بدون انتخاب ردیف، عدد برای همه‌ی پرسنل در محدوده‌ی شما ثبت می‌شود. سقف گروهی و پنجره‌ی زمانی ثبت، مانند ثبت تکی بررسی می‌شود.') }}
                    <br>
                    {{ __('پس از ارسال به معاونت، امکان ویرایش آن دوره برای شما قفل می‌شود.') }}
                </p>

                {{-- گزارش مواردی که ثبت نشدند (دوره‌ی قفل‌شده، عبور از سقف و …) --}}
                <template x-if="skipped.length">
                    <div class="mt-3 border-t border-line pt-3">
                        <p class="mb-2 text-xs font-medium text-brick">{{ __('موارد ثبت‌نشده:') }}</p>
                        <ul class="max-h-40 space-y-1 overflow-y-auto">
                            <template x-for="(item, i) in skipped" :key="i">
                                <li class="rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs text-brick" x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        @endif

        @if ($role === 'deputy' && $deputyCapacitySummary)
            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-line bg-white p-4 shadow-card">
                    <p class="text-xs text-ink/50">{{ __('جمع کل ظرفیت اضافه‌کار مازاد (پایه و خالص واگذاری)') }}</p>
                    <p id="deputy-excess-capacity" class="mt-2 font-mono text-2xl font-semibold text-pine">{{ number_format((int) $deputyCapacitySummary['total_capacity']) }}</p>
                </div>
                <div class="rounded-2xl border border-line bg-white p-4 shadow-card">
                    <p class="text-xs text-ink/50">{{ __('مجموع اضافه‌کار مازاد اختصاص‌یافته') }}</p>
                    <p id="deputy-excess-assigned" class="mt-2 font-mono text-2xl font-semibold text-ink">{{ number_format((int) $deputyCapacitySummary['assigned_hours']) }}</p>
                    <p class="mt-1 text-xs text-ink/50">{{ __('باقی‌مانده ظرفیت') }}: <span id="deputy-excess-remaining" class="font-mono font-medium text-pine">{{ number_format((int) $deputyCapacitySummary['remaining_capacity']) }}</span> {{ __('ساعت') }}</p>
                </div>
            </div>
        @endif

        <div class="rounded-2xl border border-line bg-white shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-line text-xs text-ink/50">
                            @if ($canEditInline)
                                <th class="px-5 py-3 font-medium">
                                    <input type="checkbox" @change="toggleAll($event)" class="rounded border-line text-pine focus:ring-pine">
                                </th>
                            @endif
                            <th class="px-5 py-3 font-medium">{{ __('پرسنل') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('نوع قرارداد') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('ساعت پیش‌فرض سمت') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('عادی') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('مازاد') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('ویژه') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('جمع') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('قابل پرداخت') }}</th>
                            <th class="px-5 py-3 font-medium">{{ __('وضعیت') }}</th>
                            <th class="px-5 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($personnel as $person)
                            @php
                                $period = $periods->get($person->id);
                                $specialHours = $period?->specialItemLines->where('unit_snap', 'hour')->sum('hours') ?? 0;
                                $exceeds = $period?->exceedsOverallCeiling() ?? false;
                                // معاون برای دوره‌های زیرمجموعه‌ی خودش در وضعیت‌های در اختیار معاون نیز عادی را ویرایش می‌کند.
                                $rowEditable = $canEditInline && (!$period
                                    || $period->status->editableByManager()
                                    || ($role === 'deputy' && $deputyPersonnelIds->contains($person->id) && $period->status->allowsDeputyExcessEntry()));
                                $rowSelectable = $rowEditable || ($role === 'deputy' && $period && $period->status->editableByDeputy());
                            @endphp
                            <tr class="hover:bg-surface/60" data-row="{{ $person->id }}" x-data="{ saving: false, saved: false, error: '' }">
                                @if ($canEditInline)
                                    <td class="px-5 py-3.5">
                                        <input
                                            type="checkbox" value="{{ $person->id }}"
                                            x-model.number="selected"
                                            class="rounded border-line text-pine focus:ring-pine"
                                            @if (!$rowSelectable) disabled @endif
                                        >
                                    </td>
                                @endif

                                <td class="px-5 py-3.5">
                                    <div class="font-medium text-ink">{{ trim($person->name . ' ' . $person->family) }}</div>
                                    <div class="font-mono text-xs text-ink/40">{{ $person->username }}</div>
                                </td>

                                <td class="px-5 py-3.5 text-xs text-ink/60">
                                    {{ $person->contractType?->title ?? '—' }}
                                    @if ($person->position)
                                        <div class="text-ink/40">{{ $person->position->title }}</div>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 font-mono text-xs text-ink/70">
                                    {{ $person->position_id ? ($defaultHoursByPosition->get($person->position_id) ?? '—') : '—' }}
                                </td>

                                {{-- ستون «عادی»: ویرایش درون‌خطی به‌جای بازکردن فرم جداگانه. --}}
                                <td class="px-5 py-3.5" data-cell="normal-{{ $person->id }}" @if ($canEditInline) id="normal-cell-{{ $person->id }}" @endif>
                                    @if ($rowEditable)
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="number" step="1" min="0"
                                                value="{{ $period?->normal_hours }}"
                                                data-personnel="{{ $person->id }}"
                                                @keydown.enter.prevent="$el.blur()"
                                                @change="saveRow($el, { saving: v => saving = v, ok: () => { saved = true; error = ''; setTimeout(() => saved = false, 2000) }, fail: m => { error = m; saved = false } })"
                                                :disabled="saving"
                                                class="w-20 rounded-lg border border-line px-2 py-1 font-mono text-xs focus:border-pine focus:outline-none disabled:opacity-50"
                                                :class="error ? 'border-brick' : (saved ? 'border-pine' : '')"
                                            >
                                            <span x-show="saving" class="text-xs text-ink/40">…</span>
                                            <span x-show="saved" x-cloak class="text-xs text-pine">✓</span>
                                        </div>
                                        <p x-show="error" x-cloak x-text="error" class="mt-1 max-w-48 text-[11px] text-brick"></p>
                                    @else
                                        <span class="font-mono text-xs text-ink/70">{{ $period?->normal_hours ?? '—' }}</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 font-mono text-xs text-ink/70" data-cell="excess-{{ $person->id }}">
                                    @if ($role === 'deputy' && $period && $deputyPersonnelIds->contains($person->id) && $period->status->allowsDeputyExcessEntry())
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="number" step="1" min="0"
                                                value="{{ $period->excess_hours }}"
                                                data-excess-url="{{ route('admin.overtime.periods.excess.store', $period) }}"
                                                @keydown.enter.prevent="$el.blur()"
                                                @change="saveExcessRow($el, { saving: v => saving = v, ok: () => { saved = true; error = ''; setTimeout(() => saved = false, 2000) }, fail: m => { error = m; saved = false } })"
                                                :disabled="saving"
                                                class="w-20 rounded-lg border border-line px-2 py-1 font-mono text-xs focus:border-pine focus:outline-none disabled:opacity-50"
                                                :class="error ? 'border-brick' : (saved ? 'border-pine' : '')"
                                            >
                                            <span x-show="saving" class="text-xs text-ink/40">…</span>
                                            <span x-show="saved" x-cloak class="text-xs text-pine">✓</span>
                                            <p x-show="error" x-cloak x-text="error" class="mt-1 text-[11px] text-brick"></p>
                                        </div>
                                    @else
                                        {{ $period?->excess_hours ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs text-ink/70">{{ $period ? number_format((int) $specialHours) : '—' }}</td>

                                <td class="px-5 py-3.5 font-mono text-xs font-medium text-ink" data-cell="total-{{ $person->id }}">{{ $period?->totalHours() ?? '—' }}</td>

                                <td class="px-5 py-3.5" data-cell="payable-{{ $person->id }}">
                                    @if ($period)
                                        {{-- اگر مجموع از سقف کلی قرارداد بگذرد، عدد ارسالی به مالی
                                             همان سقف است. اختلاف را صریح نشان می‌دهیم تا کاربر غافلگیر نشود. --}}
                                        <span class="font-mono text-xs font-medium {{ $exceeds ? 'text-brick' : 'text-pine' }}">{{ $period->payableHours() }}</span>
                                        @if ($exceeds)
                                            <div class="text-[11px] text-brick">{{ __('کسر') }} {{ $period->cappedHours() }} {{ __('ساعت') }}</div>
                                        @endif
                                    @else
                                        <span class="text-ink/30">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5" data-cell="status-{{ $person->id }}">
                                    @if ($period)
                                        @include('admin.overtime.partials.status-badge', ['status' => $period->status])
                                    @else
                                        <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs text-ink/40">{{ __('ثبت نشده') }}</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5" data-cell="actions-{{ $person->id }}">
                                    <div class="flex justify-end gap-2">
                                        @if ($role === 'deputy' && $period && $period->status->editableByDeputy())
                                            <button
                                                type="button"
                                                @click="sendToCeo('{{ route('admin.overtime.periods.deputy-review', $period) }}')"
                                                :disabled="busy"
                                                class="rounded-lg border border-pine bg-pine-100 px-3 py-1.5 text-xs font-medium text-pine hover:bg-pine/10 disabled:opacity-60"
                                            >
                                                {{ __('ارسال به مدیرعامل') }}
                                            </button>
                                        @endif
                                        {{-- ارسال تکی: فقط وقتی دوره‌ای وجود دارد که هنوز دست مدیر است. --}}
                                        @if ($canEditInline && $period && $period->status->editableByManager())
                                            <button
                                                type="button"
                                                @click="submitToDeputy([{{ $person->id }}])"
                                                :disabled="busy"
                                                class="rounded-lg border border-pine bg-pine-100 px-3 py-1.5 text-xs font-medium text-pine hover:bg-pine/10 disabled:opacity-60"
                                            >
                                                {{ $role === 'deputy' ? __('ارسال به مدیرعامل') : __('ارسال به معاونت') }}
                                            </button>
                                        @endif

                                        <a
                                            href="{{ route('admin.overtime.periods.show', ['personnel' => $person->id, 'jalali_year' => $year, 'jalali_month' => $month]) }}"
                                            class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface"
                                        >
                                            {{ __('جزئیات') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canEditInline ? 10 : 9 }}" class="px-5 py-10 text-center text-ink/40">{{ __('پرسنلی در محدوده‌ی شما یافت نشد.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    /**
     * ثبت اضافه‌کار عادی: گروهی (یک عدد برای همه/انتخاب‌شده‌ها) و درون‌خطی (هر ردیف).
     *
     * ویرایش درون‌خطی روی رویداد change ذخیره می‌کند نه با دکمه‌ی جدا، تا مدیر بتواند
     * با Tab بین ردیف‌ها حرکت کند و عددها را پشت‌سرهم وارد کند.
     */
    function overtimeEntry(config) {
        return {
            bulkHours: '',
            overwrite: false,
            busy: false,
            selected: [],
            skipped: [],

            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            },

            // همان کانال toast لایه‌ی admin؛ مستقیم رویداد می‌فرستیم تا وابسته به
            // ترتیب بارگذاری acl-admin.js نباشیم.
            toast(message, type = 'error') {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
            },

            toggleAll(event) {
                const boxes = [...this.$root.querySelectorAll('tbody input[type=checkbox]:not(:disabled)')];
                this.selected = event.target.checked ? boxes.map(b => Number(b.value)) : [];
            },

            clearSelection() {
                this.selected = [];
                this.$root.querySelectorAll('input[type=checkbox]').forEach(b => { b.checked = false });
            },

            async applyBulk() {
                if (this.bulkHours === '' || this.busy) return;

                const scope = this.selected.length
                    ? `${this.selected.length} نفر انتخاب‌شده`
                    : 'همه‌ی پرسنل زیرمجموعه';

                if (!confirm(`ثبت ${this.bulkHours} ساعت اضافه‌کار برای ${scope}؟`)) return;

                this.busy = true;
                this.skipped = [];

                try {
                    const response = await fetch(config.bulkUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            normal_hours: this.bulkHours,
                            jalali_year: config.year,
                            jalali_month: config.month,
                            personnel_ids: this.selected,
                            overwrite: this.overwrite,
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        this.toast(data.message ?? 'خطا در ثبت گروهی.');
                        return;
                    }

                    this.skipped = data.skipped ?? [];
                    this.toast(data.message, data.applied > 0 ? 'success' : 'error');

                    // سطرهای موفق بلافاصله به‌روز می‌شوند، چه عملیات کامل موفق باشد
                    // چه جزئی. پیش‌تر در حالت جزئی هیچ به‌روزرسانی‌ای انجام نمی‌شد و
                    // کاربر تا رفرش دستی، اعداد قدیمی را می‌دید.
                    this.applyRows(data.rows ?? {});
                    this.clearSelection();
                } catch (error) {
                    this.toast('ارتباط با سرور برقرار نشد.');
                } finally {
                    this.busy = false;
                }
            },

            /**
             * ارسال به معاونت. با آرگومان ids برای یک ردیف، و بدون آن برای
             * انتخاب‌شده‌ها یا کل واحد.
             *
             * پس از ارسال حتماً reload می‌کنیم: وضعیت ردیف عوض شده و فیلدهای ویرایش
             * باید قفل شوند - نمایش فیلد بازِ غیرقابل‌ذخیره بدترین حالت ممکن است.
             */
            async submitToDeputy(ids = null) {
                if (this.busy) return;

                const target = ids ?? this.selected;
                const scope = target.length ? `${target.length} مورد` : 'همه‌ی دوره‌های آماده';

                if (!confirm(`ارسال ${scope} برای بررسی معاونت؟ پس از ارسال، ویرایش برای شما قفل می‌شود.`)) return;

                this.busy = true;
                this.skipped = [];

                try {
                    const response = await fetch(config.submitUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            jalali_year: config.year,
                            jalali_month: config.month,
                            personnel_ids: target,
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        this.toast(data.message ?? 'ارسال انجام نشد.');
                        return;
                    }

                    this.skipped = data.skipped ?? [];
                    this.toast(data.message, data.submitted > 0 ? 'success' : 'error');

                    // ارسال یعنی قفل‌شدن سطر؛ اگر جدول به‌روز نشود، فیلد ویرایشِ بازِ
                    // غیرقابل‌ذخیره روی صفحه می‌ماند - بدترین حالت ممکن.
                    this.applyRows(data.rows ?? {});
                    this.clearSelection();
                } catch (error) {
                    this.toast('ارتباط با سرور برقرار نشد.');
                } finally {
                    this.busy = false;
                }
            },

            async sendToCeo(url) {
                if (this.busy || !confirm('این دوره برای بررسی مدیرعامل ارسال شود؟')) return;

                this.busy = true;
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ decision: 'approve' }),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        this.toast(data.message ?? 'ارسال برای مدیرعامل انجام نشد.');
                        return;
                    }

                    this.toast(data.message, 'success');
                    window.location.reload();
                } catch (error) {
                    this.toast('ارتباط با سرور برقرار نشد.');
                } finally {
                    this.busy = false;
                }
            },

            async saveRow(input, hooks) {
                const id = input.dataset.personnel;
                const value = input.value;

                if (value === '') return;

                hooks.saving(true);

                try {
                    const response = await fetch(`{{ url('admin/overtime/personnel') }}/${id}/normal`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            normal_hours: value,
                            jalali_year: config.year,
                            jalali_month: config.month,
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        hooks.fail(data.message ?? 'ثبت نشد.');
                        return;
                    }

                    hooks.ok();
                    this.refreshRow(id, data.computed);

                    // اولین ثبت، وضعیت را از «ثبت نشده» به «پیش‌نویس» می‌برد و دکمه‌ی
                    // ارسال را معنادار می‌کند؛ بدون این، سطر تا رفرش قدیمی می‌ماند.
                    this.paintStatus(id, data.computed);
                    this.ensureSubmitButton(id, data.computed);
                } catch (error) {
                    hooks.fail('ارتباط با سرور برقرار نشد.');
                } finally {
                    hooks.saving(false);
                }
            },

            async saveExcessRow(input, hooks) {
                const id = input.closest('tr')?.dataset.row;
                const value = input.value;
                if (value === '' || !id) return;

                hooks.saving(true);
                try {
                    const response = await fetch(input.dataset.excessUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ excess_hours: value }),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        hooks.fail(data.message ?? 'ثبت اضافه‌کار مازاد انجام نشد.');
                        return;
                    }

                    hooks.ok();
                    this.refreshRow(id, data.computed);
                    this.refreshDeputyCapacity(data.capacity_summary);
                    this.toast(data.message ?? 'اضافه‌کار مازاد ثبت شد.', 'success');
                } catch (error) {
                    hooks.fail('ارتباط با سرور برقرار نشد.');
                } finally {
                    hooks.saving(false);
                }
            },

            refreshDeputyCapacity(summary) {
                if (!summary) return;

                const total = document.getElementById('deputy-excess-capacity');
                const assigned = document.getElementById('deputy-excess-assigned');
                const remaining = document.getElementById('deputy-excess-remaining');
                if (total) total.textContent = Number(summary.total_capacity).toLocaleString('en-US');
                if (assigned) assigned.textContent = Number(summary.assigned_hours).toLocaleString('en-US');
                if (remaining) remaining.textContent = Number(summary.remaining_capacity).toLocaleString('en-US');
            },

            /**
             * نتیجه‌ی یک عملیات گروهی را روی سطرهای مربوطه اعمال می‌کند.
             *
             * کلید هر ورودی شناسه‌ی پرسنل است و مقدارش همان چیزی که سرور محاسبه کرده.
             * این متد قلبِ رفعِ آن باگ است: هر عملیات - چه کامل موفق چه جزئی - سطرهای
             * موفق را بلافاصله به‌روز می‌کند، پس کاربر هیچ‌وقت داده‌ی قدیمی نمی‌بیند.
             */
            applyRows(rows) {
                Object.entries(rows).forEach(([id, row]) => {
                    if (row.normal_hours !== undefined) {
                        const input = document.querySelector(`[data-cell="normal-${id}"] input`);
                        if (input) input.value = row.normal_hours;
                    }
                    if (row.excess_hours !== undefined) {
                        const cell = document.querySelector(`[data-cell="excess-${id}"]`);
                        if (cell) cell.textContent = row.excess_hours;
                    }

                    this.refreshRow(id, row);
                    this.paintStatus(id, row);

                    // سطری که دیگر دست مدیر نیست باید همان‌جا قفل شود.
                    if (row.editable === false) this.lockRow(id);
                });
            },

            /**
             * پس از اولین ثبت روی یک سطر، دکمه‌ی «ارسال به معاونت» باید ظاهر شود -
             * در رندر اولیه وجود نداشت چون هنوز دوره‌ای نبود.
             */
            ensureSubmitButton(id, row) {
                if (!row || row.editable === false || !['draft', 'deputy_rejected'].includes(row.status)) return;

                const cell = document.querySelector(`[data-cell="actions-${id}"] div`);
                if (!cell || cell.querySelector('button')) return;

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'rounded-lg border border-pine bg-pine-100 px-3 py-1.5 text-xs font-medium text-pine hover:bg-pine/10 disabled:opacity-60';
                button.textContent = config.role === 'deputy' ? 'ارسال به مدیرعامل' : 'ارسال به معاونت';
                button.addEventListener('click', () => this.submitToDeputy([Number(id)]));

                cell.prepend(button);
            },

            /** نشان وضعیت را با همان رنگ‌بندی partials/status-badge بازسازی می‌کند. */
            paintStatus(id, row) {
                if (!row.status) return;

                const cell = document.querySelector(`[data-cell="status-${id}"]`);
                if (!cell) return;

                const tones = {
                    finalized: 'border-pine/30 bg-pine-100 text-pine',
                    ceo_approved: 'border-pine/30 bg-pine-100 text-pine',
                    deputy_rejected: 'border-brick/30 bg-brick-100 text-brick',
                    ceo_rejected: 'border-brick/30 bg-brick-100 text-brick',
                    manager_submitted: 'border-gold/40 bg-gold-100 text-ink/80',
                    deputy_approved: 'border-gold/40 bg-gold-100 text-ink/80',
                };

                const tone = tones[row.status] || 'border-line bg-surface text-ink/50';

                cell.innerHTML = `<span class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium ${tone}">${row.status_label}</span>`;
            },

            /**
             * سطر را از حالت قابل‌ویرایش خارج می‌کند: فیلد ساعت به متن ساده تبدیل
             * می‌شود، چک‌باکس و دکمه‌ی ارسال برداشته می‌شوند.
             */
            lockRow(id) {
                const cell = document.querySelector(`[data-cell="normal-${id}"]`);

                if (cell) {
                    const input = cell.querySelector('input');
                    const value = input ? input.value : cell.textContent.trim();
                    cell.innerHTML = `<span class="font-mono text-xs text-ink/70">${value}</span>`;
                }

                const box = document.querySelector(`tr[data-row="${id}"] input[type=checkbox]`);
                if (box) {
                    box.checked = false;
                    box.disabled = true;
                }

                // دکمه‌ی «ارسال به معاونت» دیگر معنایی ندارد؛ «جزئیات» می‌ماند.
                document.querySelector(`[data-cell="actions-${id}"] button`)?.remove();

                this.selected = this.selected.filter(x => Number(x) !== Number(id));
            },

            /**
             * ستون‌های محاسباتیِ همان ردیف را با اعدادی که سرور برگردانده به‌روز
             * می‌کند. محاسبه‌ی سقف عمداً اینجا تکرار نمی‌شود؛ همان مقادیر
             * bcmath سمت سرور نشان داده می‌شود تا اختلاف گرد کردن پیش نیاید.
             */
            refreshRow(id, computed) {
                if (!computed) return;

                const total = document.querySelector(`[data-cell="total-${id}"]`);
                if (total) total.textContent = computed.total;

                const payable = document.querySelector(`[data-cell="payable-${id}"]`);
                if (!payable) return;

                const color = computed.exceeds ? 'text-brick' : 'text-pine';
                let html = `<span class="font-mono text-xs font-medium ${color}">${computed.payable}</span>`;

                if (computed.exceeds) {
                    html += `<div class="text-[11px] text-brick">کسر ${computed.capped} ساعت</div>`;
                }

                payable.innerHTML = html;
            },
        }
    }
</script>
@endpush
