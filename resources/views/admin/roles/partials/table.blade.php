<div class="overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead>
            <tr class="border-b border-line text-xs text-ink/50">
                <th class="px-5 py-3 font-medium">{{ __('نام نقش') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('توضیحات نقش') }}</th>
                <th class="px-5 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($roles as $role)
                <tr class="hover:bg-surface/60">
                    <td class="px-5 py-3.5 font-medium text-ink">{{ $role->name }}</td>
                    <td class="px-5 py-3.5 text-ink/60">{{ $role->desk }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.roles.permissions', $role) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('دسترسی‌ها') }}</a>
                            <a href="{{ route('admin.roles.edit', $role) }}" class="open-modal rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink/70 hover:bg-surface">{{ __('ویرایش') }}</a>
                            @unless ($role->is_default)
                                <button
                                    class="btn-remove rounded-lg border border-brick/20 bg-brick-100 px-3 py-1.5 text-xs font-medium text-brick hover:bg-brick/10"
                                    data-url="{{ route('admin.roles.destroy', $role) }}"
                                >{{ __('حذف') }}</button>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-5 py-10 text-center text-ink/40">{{ __('موردی یافت نشد') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="border-t border-line px-5 py-3">
    {{ $roles->links() }}
</div>
