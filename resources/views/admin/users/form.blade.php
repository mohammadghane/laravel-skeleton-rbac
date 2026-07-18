{{--
    NOTE: province/city <select> options were sourced from a separate `Province` model/table
    in the old app, which wasn't part of the ACL zip. Pass $provinces / $cities into this view
    from wherever that lookup now lives, or swap these two fields for your actual location data.
--}}
<h3 class="mb-4 text-base font-semibold text-ink">
    {{ isset($user) ? __('ویرایش کاربر') : __('کاربر جدید') }}
</h3>

<form id="user-form" method="POST" action="{{ route(($editingSelf ?? false) ? 'admin.profile.store' : 'admin.users.store') }}" data-callback="reloadUserGrid" class="space-y-4">
    @csrf
    <input type="hidden" name="id" value="{{ $user->id ?? '' }}">

    @php $input = 'w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-pine focus:outline-none focus:ring-2 focus:ring-pine/20'; @endphp
    @php $label = 'mb-1.5 block text-sm font-medium text-ink/70'; @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label class="{{ $label }}">{{ __('جنسیت') }}</label>
            <select name="gender" required class="{{ $input }}">
                <option value="1" @selected(($user->gender ?? null) == 1)>{{ __('مرد') }}</option>
                <option value="2" @selected(($user->gender ?? null) == 2)>{{ __('زن') }}</option>
            </select>
        </div>
        <div>
            <label class="{{ $label }}">{{ __('نام') }}</label>
            <input class="{{ $input }}" name="name" required value="{{ old('name', $user->name ?? '') }}">
        </div>
        <div>
            <label class="{{ $label }}">{{ __('نام خانوادگی') }}</label>
            <input class="{{ $input }}" name="family" value="{{ old('family', $user->family ?? '') }}">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label class="{{ $label }}">{{ __('نام کاربری') }}</label>
            <input class="{{ $input }}" name="username" required value="{{ old('username', $user->username ?? '') }}">
        </div>
        <div>
            <label class="{{ $label }}">{{ __('تلفن همراه') }}</label>
            <input class="{{ $input }} font-mono" name="cell" required value="{{ old('cell', $user->cell ?? '') }}">
        </div>
        <div>
            <label class="{{ $label }}">{{ __('کد ملی') }}</label>
            <input class="{{ $input }} font-mono" name="national_code" value="{{ old('national_code', $user->national_code ?? '') }}">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="{{ $label }}">{{ __('ایمیل') }}</label>
            <input class="{{ $input }}" name="email" value="{{ old('email', $user->email ?? '') }}">
        </div>
        <div>
            <label class="{{ $label }}">{{ __('تلفن ثابت') }}</label>
            <input class="{{ $input }} font-mono" name="tell" value="{{ old('tell', $user->tell ?? '') }}">
        </div>
    </div>

    <div>
        <label class="{{ $label }}">{{ __('آدرس') }}</label>
        <input class="{{ $input }}" name="addr" value="{{ old('addr', $user->addr ?? '') }}">
    </div>

    @permission('admin/users')
        <div>
            <label class="{{ $label }}">{{ __('نقش سازمانی') }}</label>
            <select multiple name="roles[]" class="{{ $input }} h-28">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(in_array($role->id, $currentRoleIds ?? []))>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
    @endpermission

    <div>
        <label class="{{ $label }}">{{ __('رمز عبور جدید') }}</label>
        <input class="{{ $input }}" type="password" name="password">
        <span class="mt-1 block text-xs text-ink/40">{{ __('در صورت عدم تمایل به تغییر، این فیلد را خالی بگذارید') }}</span>
    </div>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
