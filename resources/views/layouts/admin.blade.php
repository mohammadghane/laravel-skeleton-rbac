@php
    // 'fa' (and other RTL languages, if you add them) render dir="rtl"; everything else "ltr".
    // Add to this list if you add more RTL locales later.
    $dir = in_array(app()->getLocale(), config('locales.rtl')) ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('پنل مدیریت'))</title>

    {{-- استایل و اسکریپت هر دو محلی‌اند: پروژه هیچ فایلی از اینترنت نمی‌گیرد.
         Tailwind با Vite کامپایل می‌شود (توکن‌های رنگ/فونت در tailwind.config.js)
         و Alpine داخل همین بسته است. برای بازسازی: npm ci && npm run build --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- کامپوننت انتخاب چندگانه (فعلاً برای «نقش سازمانی» در فرم کاربر).
         باید پیش از بارگذاری Alpine تعریف شود تا وقتی فرم داخل مودال با fetch لود
         می‌شود، x-data بتواند آن را پیدا کند. --}}
    <script>
        function rolePicker(options, initial) {
            return {
                options: options,
                selected: initial,
                query: '',
                open: false,
                labelOf(id) {
                    const match = this.options.find(o => o.id === id);
                    return match ? match.name : id;
                },
                filtered() {
                    const q = this.query.trim().toLowerCase();
                    return q ? this.options.filter(o => o.name.toLowerCase().includes(q)) : this.options;
                },
                toggle(id) {
                    this.selected = this.selected.includes(id)
                        ? this.selected.filter(x => x !== id)
                        : [...this.selected, id];
                    this.query = '';
                },
            };
        }
    </script>

    {{-- حذف گروهی: مشترک بین همه‌ی فهرست‌ها.
         انتخاب ردیف‌ها با `.row-select` و چک‌باکس سربرگ با `.select-all` انجام می‌شود.
         سرور همان قواعد حفاظتیِ حذف تکی را اعمال می‌کند و موارد رد شده را برمی‌گرداند. --}}
    <script>
        function bulkDelete(url, confirmMessage) {
            return {
                url: url,
                selected: [],
                blocked: [],
                busy: false,

                toggle(id, checked) {
                    this.selected = checked
                        ? [...this.selected, id]
                        : this.selected.filter(x => x !== id);
                },

                toggleAll(checked) {
                    const boxes = document.querySelectorAll('.row-select');
                    boxes.forEach(b => { b.checked = checked; });
                    this.selected = checked ? [...boxes].map(b => Number(b.value)) : [];
                },

                clearSelection() {
                    document.querySelectorAll('.row-select, .select-all').forEach(b => { b.checked = false; });
                    this.selected = [];
                    this.blocked = [];
                },

                async destroySelected() {
                    const message = (confirmMessage || 'آیا از حذف موارد انتخاب‌شده اطمینان دارید؟')
                        .replace(':count', this.selected.length);

                    if (!confirm(message)) return;

                    this.busy = true;
                    this.blocked = [];

                    try {
                        const body = new FormData();
                        this.selected.forEach(id => body.append('ids[]', id));

                        const res = await fetch(this.url, {
                            method: 'POST',
                            body: body,
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });

                        const result = await res.json();

                        if (!res.ok || !result.success) {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: result.message || 'حذف با خطا مواجه شد.', type: 'error' },
                            }));
                            return;
                        }

                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: { message: result.message, type: result.deleted > 0 ? 'success' : 'error' },
                        }));

                        // سطرهای حذف‌شده بلافاصله از جدول برداشته می‌شوند - چه عملیات
                        // کامل موفق باشد چه جزئی. پیش‌تر در حالت جزئی فقط گزارشِ موارد
                        // رد شده نمایش داده می‌شد و سطرهای حذف‌شده تا رفرش دستیِ کاربر
                        // روی صفحه می‌ماندند، که یعنی کاربر داده‌ی نادرست می‌دید.
                        this.removeRows(result.deleted_ids || []);

                        if (!result.blocked || result.blocked.length === 0) {
                            // بدون موردِ رد شده، تازه‌سازی امن است: چیزی برای خواندن نمانده
                            // و شمارنده/صفحه‌بندی سرور هم به‌روز می‌شود.
                            setTimeout(() => window.location.reload(), 600);
                        } else {
                            this.blocked = result.blocked;
                            this.clearSelectionKeepBlocked();
                        }
                    } catch {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: { message: 'اتصال به سرور برقرار نشد.', type: 'error' },
                        }));
                    } finally {
                        this.busy = false;
                    }
                },

                clearSelectionKeepBlocked() {
                    document.querySelectorAll('.row-select, .select-all').forEach(b => { b.checked = false; });
                    this.selected = [];
                },

                /**
                 * سطرهای رکوردهای حذف‌شده را از DOM برمی‌دارد.
                 *
                 * مبنا همان چک‌باکس `.row-select` است که مقدارش شناسه‌ی رکورد است، پس
                 * به ساختار جدولِ هر صفحه وابسته نیست. اگر ردیفی پیدا نشود (مثلاً جدول
                 * ساختار متفاوتی دارد) بی‌سروصدا رد می‌شود.
                 */
                removeRows(ids) {
                    ids.forEach(id => {
                        const box = document.querySelector(`.row-select[value="${id}"]`);
                        const row = box?.closest('tr');

                        if (row) row.remove();
                    });

                    this.showEmptyStateIfNeeded();
                },

                /** اگر آخرین سطر هم حذف شد، جدولِ خالی نباید بی‌پیام رها شود. */
                showEmptyStateIfNeeded() {
                    const body = document.querySelector('tbody');

                    if (!body || body.querySelector('tr')) return;

                    const columns = document.querySelectorAll('thead th').length || 1;
                    body.innerHTML = `<tr><td colspan="${columns}" class="px-5 py-10 text-center text-ink/40">موردی برای نمایش نیست.</td></tr>`;
                },
            };
        }
    </script>


    @stack('styles')
</head>
<body class="h-full bg-surface font-sans text-ink antialiased" x-data="{ sidebarOpen: false }">

    <div class="flex h-full">

        {{-- Mobile overlay --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
            style="display: none;"
        ></div>

        {{-- Sidebar. `x-show` toggles a plain inline display:none/flex - the same mechanism
             already used successfully by the overlay backdrop and profile dropdown below,
             instead of a dynamic transform class the Tailwind Play CDN wasn't reliably
             generating. `lg:!flex` forces it visible at desktop regardless of sidebarOpen,
             since `!important` is the only thing that can override an inline style. --}}
        <aside
            x-show="sidebarOpen"
            class="fixed inset-y-0 end-0 z-40 flex w-64 shrink-0 flex-col bg-ink lg:static lg:!flex"
            style="display: none;"
        >
            {{-- Logo + hierarchy rail: three small ticks colour-coded to the admin/user/guest
                 tiers this ACL system is built around — a nod to the org hierarchy the whole
                 module exists to model, not a decorative flourish. --}}
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <div class="flex flex-col gap-1">
                    <span class="h-1.5 w-6 rounded-full bg-gold"></span>
                    <span class="h-1.5 w-6 rounded-full bg-pine"></span>
                    <span class="h-1.5 w-6 rounded-full bg-white/25"></span>
                </div>
                <span class="text-base font-bold text-white">{{ __('پنل مدیریت') }}</span>
            </div>

            {{--
                منوی سایدبار از یک آرایه‌ی مرتب ساخته می‌شود، نه ۱۴ بلوک تکراری. برای
                جابه‌جایی یا افزودن آیتم کافی است همین آرایه تغییر کند.

                هر آیتم:
                  label   عنوان نمایشی
                  route   نام route مقصد
                  active  الگوی routeIs برای حالت «فعال» (پیش‌فرض: خودِ route با .*)
                  show    شرط نمایش (پیش‌فرض: true)
            --}}
            @php
                $user = auth()->user();

                // هر منو دسترسی مستقل خودش را دارد (config/permissions.php، بخش menus).
                // نقش‌های چارت سازمانی (مدیر واحد / معاون) هم به‌صورت خودکار به دو منوی
                // «داشبورد» و «ثبت اضافه‌کار» دسترسی دارند، چون بدون آن‌ها اصلاً نمی‌توانند
                // کارشان را انجام دهند و تخصیص دستیِ دسترسی برای هر مدیر جدید فراموش می‌شد.
                $inChart = $user && ($user->isUnitManager() || $user->isDeputyHead());

                $can = fn (string $permission) => $user && $user->hasPermission($permission);

                $navItems = [
                    [
                        'label' => __('داشبورد و گزارش‌ها'),
                        'route' => 'admin.overtime.dashboard',
                        'active' => 'admin.overtime.dashboard|admin.overtime.reports.*',
                        'show' => $can('menu/dashboard') || $inChart,
                    ],
                    [
                        'label' => __('پرسنل'),
                        'route' => 'admin.users.index',
                        'active' => 'admin.users.*',
                        'show' => $can('menu/personnel'),
                    ],
                    [
                        'label' => __('معاونت'),
                        'route' => 'admin.overtime.deputies.index',
                        'active' => 'admin.overtime.deputies.*',
                        'show' => $can('menu/deputies'),
                    ],
                    [
                        'label' => __('سمت سازمانی'),
                        'route' => 'admin.overtime.positions.index',
                        'active' => 'admin.overtime.positions.*',
                        'show' => $can('menu/positions'),
                    ],
                    [
                        'label' => __('واحد سازمانی'),
                        'route' => 'admin.overtime.units.index',
                        'active' => 'admin.overtime.units.*',
                        'show' => $can('menu/units'),
                    ],
                    [
                        'label' => __('قراردادها و سقف‌ها'),
                        'route' => 'admin.overtime.contract-types.index',
                        'active' => 'admin.overtime.contract-types.*',
                        'show' => $can('menu/contract-types'),
                    ],
                    [
                        'label' => __('همگام‌سازی پرسنل'),
                        'route' => 'admin.overtime.sync.index',
                        'active' => 'admin.overtime.sync.*',
                        'show' => $can('menu/personnel-sync'),
                    ],
                    [
                        'label' => __('کسر کار / ماموریت'),
                        'route' => 'admin.overtime.import.form',
                        'active' => 'admin.overtime.import.*',
                        'show' => $can('menu/import'),
                    ],
                    [
                        'label' => __('آیتم‌های ویژه'),
                        'route' => 'admin.overtime.special-items.index',
                        'active' => 'admin.overtime.special-items.*',
                        'show' => $can('menu/special-items'),
                    ],
                    [
                        'label' => __('واگذاری ظرفیت مازاد'),
                        'route' => 'admin.overtime.capacity-transfers.index',
                        'active' => 'admin.overtime.capacity-transfers.*',
                        'show' => $can('menu/capacity-transfers') || ($user && $user->isDeputyHead()),
                    ],
                    [
                        'label' => __('ثبت اضافه‌کار'),
                        'route' => 'admin.overtime.periods.index',
                        'active' => 'admin.overtime.periods.*',
                        'show' => $can('menu/overtime-entry') || $inChart,
                    ],
                    [
                        'label' => __('نقش سازمانی'),
                        'route' => 'admin.roles.index',
                        'active' => 'admin.roles.*',
                        'show' => $can('menu/roles'),
                    ],
                    [
                        'label' => __('گزارش تغییرات'),
                        'route' => 'admin.audit-logs.index',
                        'active' => 'admin.audit-logs.*',
                        'show' => $can('menu/audit-logs'),
                    ],
                ];
            @endphp

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach ($navItems as $item)
                    @continue(!($item['show'] ?? true))

                    @php
                        $patterns = explode('|', $item['active'] ?? ($item['route'] . '.*'));
                        $isActive = request()->routeIs(...$patterns);
                    @endphp

                    <a
                        href="{{ route($item['route']) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ $isActive ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @yield('sidebar-extra')
            </nav>

            <div class="border-t border-white/10 px-5 py-4">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/60 transition hover:bg-white/5 hover:text-white">
                        <span class="h-1.5 w-1.5 rounded-full bg-brick"></span>
                        {{ __('خروج از حساب') }}
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Topbar --}}
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        @click="sidebarOpen = true"
                        class="rounded-lg p-2 text-ink/60 hover:bg-surface lg:hidden"
                        aria-label="{{ __('باز کردن منو') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-base font-semibold text-ink sm:text-lg">@yield('title', __('داشبورد'))</h1>
                </div>

                <div class="flex items-center gap-2">
                    @include('partials.locale-switcher')

                    @auth
                        @php
                            $roleNames = auth()->user()->roles->pluck('name')->all();
                            $tier = in_array('admin', $roleNames) ? 'admin' : (in_array('user', $roleNames) ? 'user' : 'guest');
                            $tierStyle = [
                                'admin' => 'bg-gold-100 text-gold border-gold/30',
                                'user' => 'bg-pine-100 text-pine border-pine/30',
                                'guest' => 'bg-black/5 text-ink/50 border-ink/10',
                            ][$tier];
                            $tierLabel = ['admin' => __('مدیر'), 'user' => __('کاربر'), 'guest' => __('مهمان')][$tier];
                        @endphp
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-surface">
                                <span class="hidden text-end sm:block">
                                    <span class="block text-sm font-medium text-ink">{{ auth()->user()->name }} {{ auth()->user()->family }}</span>
                                    <span class="block text-xs text-ink/50">{{ auth()->user()->username }}</span>
                                </span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $tierStyle }}">
                                    {{ $tierLabel }}
                                </span>
                            </button>

                            <div
                                x-show="open"
                                x-transition
                                class="absolute end-0 mt-2 w-44 rounded-xl border border-line bg-white py-1 shadow-card"
                                style="display: none;"
                            >
                                <a href="{{ route('admin.profile') }}" class="block px-4 py-2 text-sm text-ink/70 hover:bg-surface">{{ __('پروفایل من') }}</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-brick hover:bg-brick-100">{{ __('خروج') }}</button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div class="mx-auto max-w-6xl">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Shared modal target, populated by acl-admin.js for the roles/users add-edit-permissions forms.
         Closes only via the explicit ✕ button or Escape - clicking the backdrop does nothing,
         so an accidental outside click can't discard unsaved form input. --}}
    <div
        id="admin-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-ink/50 p-4"
    >
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <span class="text-sm font-medium text-ink/40">&nbsp;</span>
                <button type="button" data-modal-close class="rounded-lg p-1 text-ink/40 hover:bg-surface hover:text-ink">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>

    @include('partials.toast')
    @include('partials.js-i18n')

    <script src="{{ asset('js/acl-admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
