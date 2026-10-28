<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium w-10">
                    <input type="checkbox" class="select-all rounded border-line text-pine focus:ring-pine/20" @change="toggleAll($event.target.checked)">
                </th>
                <th class="px-5 py-3 font-medium">{{ __('نام و نام خانوادگی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('نام کاربری') }}</th>
                {{-- کد پرسنلی جایگزین «ساناکد» شد: با همگام‌سازی وب‌سرویس مقداردهی می‌شود
                     و برای تطبیق فایل‌های اکسل (کسر کار/ماموریت) هم همین کد مبناست. --}}
                <th class="px-5 py-3 font-medium">{{ __('کد پرسنلی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('سمت سازمانی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('واحد سازمانی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('معاونت') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('موبایل') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('تاریخ ثبت') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($users as $user)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5">
                        <input type="checkbox" class="row-select rounded border-line text-pine focus:ring-pine/20" value="{{ $user->id }}" @change="toggle({{ $user->id }}, $event.target.checked)">
                    </td>
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $user->name }} {{ $user->family }}</td>
                    <td class="px-5 py-3.5 text-ink/60">{{ $user->username }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/50">{{ $user->personnel_code ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-xs text-ink/60">{{ $user->position?->title ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-xs text-ink/60">{{ $user->unit?->title ?: '—' }}</td>
                    {{-- معاونتِ مؤثر: اگر deputy_id مستقیم نباشد، از واحد خوانده می‌شود. --}}
                    <td class="px-5 py-3.5 text-xs text-ink/60">{{ $user->effectiveDeputy()?->title ?: '—' }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/60">{{ $user->cell }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/50">{{ $user->created_at?->format('Y/m/d H:i') }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.users.edit', $user) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            <button class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10" data-url="{{ route('admin.users.destroy', $user) }}">{{ __('حذف') }}</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3">
    {{-- انتخاب تعداد رکورد در هر صفحه. با تغییر مقدار، فرم بلافاصله ارسال می‌شود و
         سایر پارامترها (جست‌وجو و فیلترها) از طریق hidden inputها حفظ می‌شوند. --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2">
        @foreach (request()->except(['per_page', 'page']) as $name => $value)
            @if (is_array($value))
                @foreach ($value as $item)
                    <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        <label class="text-xs text-ink/50">{{ __('نمایش') }}</label>
        <select
            name="per_page"
            onchange="this.form.submit()"
            class="rounded-lg border border-line bg-white px-2 py-1.5 text-xs focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
        >
            @foreach (($perPageOptions ?? [20, 50, 100, 200]) as $option)
                <option value="{{ $option }}" @selected(($perPage ?? 20) === $option)>{{ $option }}</option>
            @endforeach
        </select>
        <span class="text-xs text-ink/50">
            {{ __('از :total رکورد', ['total' => $users->total()]) }}
        </span>
    </form>

    <div>{{ $users->links() }}</div>
</div>
