<h3 class="mb-4 text-base font-semibold text-ink">
    {{ isset($user) ? __('ویرایش پرسنل') : __('پرسنل جدید') }}
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
            <input
                class="{{ $input }} font-mono"
                name="cell"
                inputmode="numeric"
                maxlength="11"
                placeholder="{{ __('اختیاری — 09121111111') }}"
                value="{{ old('cell', $user->cell ?? '') }}"
            >
            <span class="mt-1 block text-xs text-ink/40">{{ __('اختیاری؛ در صورت ورود باید ۱۱ رقم و با ۰۹ شروع شود.') }}</span>
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

    {{-- فیلدهای آدرس (استان/شهر/کد پستی/آدرس) عمداً از فرم حذف شده‌اند: در این سامانه
         کاربردی ندارند. ستون‌ها در دیتابیس باقی مانده‌اند و قوانین اعتبارسنجی‌شان هم
         nullable است، پس مقادیر قبلی پاک نمی‌شوند. --}}

    {{-- چارت سازمانی - لازمِ ماژول اضافه‌کار (بند ۴.۱ شرح خدمات: تخصیص هر پرسنل به یکی از
         انواع قرارداد). فقط برای کسی که اجازه‌ی مدیریت چارت دارد نمایش داده می‌شود، نه در
         حالت ویرایش پروفایل شخصی. --}}
    @permission('overtime/org-chart-manage')
        @if (!($editingSelf ?? false))
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="mb-3 text-sm font-medium text-ink/70">{{ __('جایگاه سازمانی (اضافه‌کار)') }}</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('واحد سازمانی') }}</label>
                        <select name="unit_id" class="{{ $input }}">
                            <option value="">{{ __('— بدون واحد —') }}</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected((int) old('unit_id', $user->unit_id ?? 0) === $unit->id)>
                                    {{ $unit->title }}@if ($unit->deputy) ({{ $unit->deputy->title }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('معاونت مستقیم') }}</label>
                        <select name="deputy_id" class="{{ $input }}">
                            <option value="">{{ __('— ندارد —') }}</option>
                            @foreach ($deputies as $deputy)
                                <option value="{{ $deputy->id }}" @selected((int) old('deputy_id', $user->deputy_id ?? 0) === $deputy->id)>{{ $deputy->title }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs text-ink/40">{{ __('فقط برای پرسنلی که زیرمجموعه‌ی هیچ واحدی نیستند.') }}</span>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('سمت سازمانی') }}</label>
                        <select name="position_id" class="{{ $input }}">
                            <option value="">{{ __('— انتخاب نشده —') }}</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected((int) old('position_id', $user->position_id ?? 0) === $position->id)>{{ $position->title }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs text-ink/40">{{ __('مبنای محاسبه‌ی سقف اضافه‌کار مازاد.') }}</span>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('نوع قرارداد') }}</label>
                        <select name="contract_type_id" class="{{ $input }}">
                            <option value="">{{ __('— انتخاب نشده —') }}</option>
                            @foreach ($contractTypes as $contractType)
                                <option value="{{ $contractType->id }}" @selected((int) old('contract_type_id', $user->contract_type_id ?? 0) === $contractType->id)>{{ $contractType->title }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs text-ink/40">{{ __('بدون نوع قرارداد، ثبت اضافه‌کار عادی ممکن نیست.') }}</span>
                    </div>
                </div>
            </div>
        @endif
    @endpermission

    {{-- در حالت پروفایل شخصی، نقش‌ها فقط نمایش داده می‌شوند و قابل ویرایش نیستند:
         کاربر نباید بتواند دسترسی خودش را تغییر دهد. پیش‌تر همین بخش، انتخابگرِ
         خالی رندر می‌کرد که هم گمراه‌کننده بود («نقشی ندارم») و هم با ذخیره‌ی
         پروفایل، نقش‌ها را پاک می‌کرد. --}}
    @if ($editingSelf ?? false)
        <div>
            <label class="{{ $label }}">{{ __('نقش سازمانی') }}</label>
            <div class="flex min-h-[42px] w-full flex-wrap items-center gap-1.5 rounded-lg border border-line bg-surface px-2 py-1.5">
                @forelse ($user?->roles ?? [] as $role)
                    <span class="inline-flex items-center rounded-md bg-pine-100 px-2 py-1 text-xs font-medium text-pine">
                        {{ $role->name }}
                    </span>
                @empty
                    <span class="px-1 text-xs text-ink/40">{{ __('نقشی به شما اختصاص داده نشده است.') }}</span>
                @endforelse
            </div>
            <p class="mt-1.5 text-xs text-ink/40">
                {{ __('تغییر نقش سازمانی فقط توسط مدیر سیستم و از فهرست پرسنل امکان‌پذیر است.') }}
            </p>
        </div>
    @else
    @permission('admin/users')
        {{-- انتخاب چندگانه‌ی نقش‌ها: به‌جای <select multiple> که در موبایل و RTL تجربه‌ی
             بدی دارد (نیاز به Ctrl+Click)، یک کامپوننت جست‌وجوپذیر با Alpine ساخته شده
             که همان پشته‌ی موجود پروژه است - بدون افزودن jQuery/Select2. --}}
        <div
            x-data="rolePicker({{ Js::from($roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])) }}, {{ Js::from(array_map('intval', $currentRoleIds ?? [])) }})"
            class="relative"
            @click.outside="open = false"
        >
            <label class="{{ $label }}">{{ __('نقش سازمانی') }}</label>

            {{-- مقادیر واقعی که به سرور ارسال می‌شوند --}}
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="roles[]" :value="id">
            </template>

            <div
                class="flex min-h-[42px] w-full flex-wrap items-center gap-1.5 rounded-lg border border-line px-2 py-1.5 focus-within:border-pine focus-within:ring-2 focus-within:ring-pine/20"
                @click="open = true; $refs.search.focus()"
            >
                <template x-for="id in selected" :key="id">
                    <span class="inline-flex items-center gap-1 rounded-md bg-pine-100 px-2 py-1 text-xs font-medium text-pine">
                        <span x-text="labelOf(id)"></span>
                        <button type="button" class="opacity-60 hover:opacity-100" @click.stop="toggle(id)" aria-label="{{ __('حذف') }}">✕</button>
                    </span>
                </template>

                <input
                    x-ref="search"
                    x-model="query"
                    @focus="open = true"
                    @keydown.escape="open = false"
                    @keydown.backspace="if (query === '' && selected.length) selected.pop()"
                    type="text"
                    class="min-w-24 flex-1 border-0 bg-transparent p-1 text-sm focus:outline-none focus:ring-0"
                    :placeholder="selected.length ? '' : '{{ __('انتخاب کنید…') }}'"
                >
            </div>

            <div x-show="open" x-transition class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-line bg-white shadow-card">
                <template x-for="role in filtered()" :key="role.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-start text-sm hover:bg-surface"
                        :class="selected.includes(role.id) ? 'text-pine' : 'text-ink/70'"
                        @click="toggle(role.id)"
                    >
                        <span x-text="role.name"></span>
                        <span x-show="selected.includes(role.id)">✓</span>
                    </button>
                </template>

                <p x-show="!filtered().length" class="px-3 py-3 text-center text-xs text-ink/40">{{ __('موردی یافت نشد') }}</p>
            </div>

            <span class="mt-1 block text-xs text-ink/40">{{ __('می‌توانید چند نقش را هم‌زمان انتخاب کنید.') }}</span>
        </div>
    @endpermission
    @endif

    <div>
        <label class="{{ $label }}">{{ __('رمز عبور جدید') }}</label>
        <input class="{{ $input }}" type="password" name="password">
        <span class="mt-1 block text-xs text-ink/40">{{ __('در صورت عدم تمایل به تغییر، این فیلد را خالی بگذارید') }}</span>
    </div>

    <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white hover:bg-pine-600 sm:w-auto">{{ __('ذخیره') }}</button>
</form>
