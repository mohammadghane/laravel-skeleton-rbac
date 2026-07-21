# ماژول اضافه‌کار پرسنل - وضعیت و راهنمای ادامه‌ی کار

این سند خلاصه‌ی کاری‌ست که روی برنچ `feature/overtime-management` انجام شده، طبق
شرح خدمات پروژه‌ی «تعریف و مدیریت اضافه‌کار پرسنل».

## چیزهایی که این بررسی مجدد (هنگام clone کردن ریپوی واقعی) اصلاح کرد

نسخه‌ی اولیه‌ی این ماژول بدون دیدن سورس واقعی پروژه نوشته شده بود و دو فرض اشتباه داشت
که در همین commit اصلاح شدند:

1. **`$user->can('overtime/...')` → `$user->hasPermission('overtime/...')`** - این پروژه
   permission را از طریق `App\Concerns\HasRoles::hasPermission()` چک می‌کند، نه Gate
   عمومی لاراول. همه‌ی کنترلرها اصلاح شدند.
2. **حذف `$this->middleware(...)` از constructor کنترلرها** - `app/Http/Controllers/Controller.php`
   در این پروژه کاملاً خالی است (بدون trait میدل‌ور)؛ فراخوانی `$this->middleware()` باعث
   Fatal Error می‌شد. تمام چک‌های permission به سطح route منتقل شدند (دقیقاً مثل الگوی
   `routes/acl.php`) - نگاه کنید به `routes/overtime.php`.
3. **گیرندگان هشدار سقف** حالا با یک کوئری مستقیم `whereHas('roles.permissions', ...)`
   پیدا می‌شوند (نه پیمایش تک‌تک کاربرها)، چون ساختار واقعی جدول‌ها
   (`roles`/`permissions`/`role_user`/`permission_role`) الان مشخص است.

## چه چیزی روی این برنچ اضافه شده (Backend کامل)

- **Migrations**: چارت سازمانی (`deputies`, `units`, `positions`, `contract_types`)،
  ستون‌های سازمانی روی `users`، سقف‌های ماهانه‌ی نسخه‌بندی‌شده، آیتم‌های ویژه،
  `overtime_periods` + گردش کار + تاریخچه، و دو ستون مالی مستقل.
- **Models**: `Deputy`, `Unit`, `Position`, `ContractType` (+ نسخه‌های سقف)،
  `SpecialItem` + وابسته‌ها، `OvertimePeriod` (+ `transitionTo()` برای state machine)،
  `OvertimeSpecialItemLine`, `OvertimeStatusHistory`. `User.php` واقعی پروژه merge شد
  (روابط چارت سازمانی به فایل موجود اضافه شد، نه overwrite).
- **Services**: `OvertimeCeilingService` (موتور کنترل سقف - دو سطحی: واحد + معاونت)،
  `OvertimePeriodService` (ساخت/یافتن دوره با اسنپ‌شات چارت)، `OvertimeReportService`
  (بند ۴.۷: مصرف، هشدار، مقایسه معاونت‌ها، رتبه‌بندی، روند).
- **Controllers + Requests + Routes**: چارت سازمانی، سقف‌ها، آیتم‌های ویژه، گردش کار کامل
  (ثبت مدیر → تایید معاونت → تایید مدیرعامل)، گزارش‌ها، خروجی اکسل.
- **Notification + Scheduled Command**: `overtime:send-ceiling-warnings` (ثبت‌شده در
  `routes/console.php`، هر روز ساعت ۸ صبح).
- **Export**: `OvertimeMonthlyExport` (Laravel Excel) + یک فایل نمونه‌ی واقعی
  (`overtime-monthly-sample.xlsx`، جداگانه در چت ارائه شد، در ریپو نیست).
- **Seeder**: `OvertimeOrgSeeder` (داده‌ی نمونه‌ی چارت + آیتم‌های ویژه‌ی اولیه)، در
  `DatabaseSeeder` ثبت شده.
- **`config/permissions.php`**: ماژول `overtime` (۴ کلید permission) اضافه شد.
- **`bootstrap/providers.php`**: `OvertimeServiceProvider` ثبت شد (Gate/Blade کمکی برای
  `isUnitManager()`/`isDeputyHead()`).

## قبل از اجرا (باید دستی انجام شود - این‌ها را نمی‌توانم از سندباکس انجام دهم)

```bash
composer update sallar/jdf maatwebsite/excel   # composer.lock باید رفرش شود؛
                                                 # سندباکس من به packagist.org دسترسی ندارد
php artisan migrate
php artisan db:seed
php artisan notifications:table && php artisan migrate   # برای کانال database نوتیفیکیشن
```

## ⚠️ کاری که هنوز مانده: Blade Views / UI

**این برنچ فقط Backend (migrations/models/services/controllers/routes) دارد. هیچ View
برای این ماژول اضافه نشده.**

دلیل: تلاش اول برای ساخت View (قبل از دیدن این ریپو) بر مبنای Bootstrap 5 + jQuery +
SweetAlert2 بود - کاملاً ناسازگار با پشته‌ی واقعی این پروژه که کشف شد:

- **Tailwind** (فعلاً از طریق Play CDN در `layouts/admin.blade.php`، نه build شده با Vite)
- **Alpine.js** برای state ساده (سایدبار، دراپ‌داون)
- یک مودال مشترک `#admin-modal` که با کلاس `.open-modal` روی لینک‌ها پر می‌شود (fetch با
  `X-Requested-With: XMLHttpRequest`، پاسخ HTML خام یک partial فرم است)
- فرم‌ها با `FormData` مستقیم POST می‌شوند (نه JSON)، پاسخ `{success, message}` است
- حذف با `.btn-remove` + `confirm()` ساده + حذف مستقیم ردیف از DOM
- `resources/js/acl-admin.js` این‌ها را delegate می‌کند - **نکته**: این فایل فعلاً به
  `bootstrap.Modal` ارجاع می‌دهد ولی هیچ Bootstrap JS در لایوت لود نمی‌شود (یک ناهماهنگی
  از قبل موجود در اسکلت پروژه، نه چیزی که من اضافه کرده باشم) - قبل از ساخت View باید این
  را هم اصلاح کرد (به مکانیزم toggle کلاس ساده روی همان `#admin-modal` فعلی).

برای این‌که Viewهای این ماژول از روز اول با بقیه‌ی پنل یکدست باشند (نه یک پشته‌ی جدا که
بعداً باید بازنویسی شود)، ساخت View را به یک commit/چرخه‌ی جدا موکول کردم. الگوی دقیقی که
باید کپی شود: `resources/views/admin/users/{index,form}.blade.php` +
`app/Http/Controllers/Admin/UserController.php` (متدهای `index`/`edit`/`store`/`destroy`،
تفکیک `$request->ajax()` برای رندر partial در برابر صفحه‌ی کامل).

**پیشنهاد قدم بعدی:** یک commit جدا برای اصلاح `acl-admin.js` (رفع ناهماهنگی Bootstrap)،
سپس Viewهای این ماژول (چارت سازمانی، سقف‌ها، آیتم‌های ویژه، گردش کار، داشبورد) دقیقاً با
همین الگو.

## محدودیت شناخته‌شده‌ی دیگر

`Unit::manager_user_id` / `Deputy::head_user_id` فعلاً در فرم‌ها به شکل ورودی عددی شناسه‌ی
کاربر خواهند بود، مگر کامپوننت انتخاب کاربر (user-picker) موجودی در پروژه باشد که به آن
معرفی شوم.
