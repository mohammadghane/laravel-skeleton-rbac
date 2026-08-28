<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium">{{ __('نام و نام خانوادگی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('نام کاربری') }}</th>
                {{-- کد پرسنلی جایگزین «ساناکد» شد: با همگام‌سازی وب‌سرویس مقداردهی می‌شود
                     و برای تطبیق فایل‌های اکسل (کسر کار/ماموریت) هم همین کد مبناست. --}}
                <th class="px-5 py-3 font-medium">{{ __('کد پرسنلی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('سمت سازمانی') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('موبایل') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('تاریخ ثبت') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($users as $user)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $user->name }} {{ $user->family }}</td>
                    <td class="px-5 py-3.5 text-ink/60">{{ $user->username }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-ink/50">{{ $user->personnel_code ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-xs text-ink/60">{{ $user->position?->title ?: '—' }}</td>
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
                <tr><td colspan="7" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="border-t border-line px-5 py-3">
    {{ $users->links() }}
</div>
