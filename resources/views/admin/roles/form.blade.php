<h3 class="mb-4 text-base font-semibold text-ink">
    {{ isset($role) ? __('ویرایش نقش سازمانی') : __('نقش سازمانی جدید') }}
</h3>

<form id="role-form" method="POST" action="{{ route('admin.roles.store') }}" data-callback="reloadRoleGrid" class="space-y-4">
    @csrf
    <input type="hidden" name="id" value="{{ $role->id ?? '' }}">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink/70">{{ __('نام گروه کاری') }}</label>
            <input
                type="text" name="name" required
                value="{{ old('name', $role->name ?? '') }}"
                class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
            >
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-ink/70">{{ __('توضیحات') }}</label>
            <input
                type="text" name="desk" required
                value="{{ old('desk', $role->desk ?? '') }}"
                class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20"
            >
        </div>
    </div>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
