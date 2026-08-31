# مدیریت کاربران و دسترسی‌ها از خط فرمان

راهنمای دستورهای artisan برای مدیریت کاربران، نقش‌ها و دسترسی‌ها.

> **چرا CLI مهم است:** پنلِ مدیریت کاربران و نقش‌ها، خودش پشت دسترسی
> `admin/users` قفل است. اگر آخرین مدیر سیستم دسترسی‌اش را از دست بدهد، تنها راه
> بازگشت همین دستورهاست.

همه‌ی دستورها از ریشه‌ی پروژه اجرا می‌شوند:

```bash
cd /var/www/laravel
php artisan <دستور>
```

---

## `user:password` — تغییر رمز عبور

```bash
# رمز را به‌صورت مخفی می‌پرسد (دوبار، برای تایید)
php artisan user:password admin

# بدون پرسش
php artisan user:password admin --password=YourStrongPass123

# ساخت رمز تصادفی و نمایش آن (فقط یک‌بار نمایش داده می‌شود)
php artisan user:password admin --random

# فهرست کاربرانی که نقش دارند
php artisan user:password --list
```

شناسه‌ی کاربر می‌تواند **نام کاربری، ایمیل یا کد پرسنلی** باشد. اگر یک شناسه به
چند کاربر بخورد، دستور صریح می‌پرسد کدام‌یک مدنظر است.

حداقل طول رمز ۸ کاراکتر است (هم‌تراز با قانون فرم).

---

## `user:roles` — نمایش و تغییر نقش‌ها

```bash
# نمایش نقش‌ها و دسترسی‌های یک کاربر
php artisan user:roles admin

# فهرست همه‌ی نقش‌های تعریف‌شده، با تعداد کاربر و دسترسی
php artisan user:roles --list-roles

# افزودن نقش (نقش‌های قبلی حفظ می‌شوند)
php artisan user:roles admin --add=admin

# حذف نقش
php artisan user:roles ali --remove=user

# جایگزینی کامل نقش‌ها
php artisan user:roles ali --set=admin,editor

# چند نقش هم‌زمان (با کاما)
php artisan user:roles ali --add=admin,editor
```

اگر نقشی که نامش را داده‌اید وجود نداشته باشد، دستور **با خطا متوقف می‌شود** و
هیچ تغییری اعمال نمی‌کند - تا اشتباه تایپی باعث پاک‌شدن نقش‌ها نشود.

پس از هر تغییر، کش دسترسی‌ها خودکار پاک می‌شود.

### بازیابی دسترسیِ ازدست‌رفته

اگر کاربری (مثلاً `admin`) دیگر هیچ منویی نمی‌بیند:

```bash
php artisan user:roles admin              # ببینید چه نقش‌هایی دارد
php artisan user:roles --list-roles       # ببینید چه نقش‌هایی موجود است
php artisan user:roles admin --add=admin  # نقش را برگردانید
```

---

## `overtime:sync-personnel` — همگام‌سازی پرسنل

```bash
# از وب‌سرویس تعریف‌شده در config/services.php
php artisan overtime:sync-personnel

# از آدرس دلخواه
php artisan overtime:sync-personnel --url=http://127.0.0.1/api/getpersonels.json

# از فایل محلی (برای تست، بدون تماس با وب‌سرویس)
php artisan overtime:sync-personnel --file=storage/app/personnel.json
```

این دستور پرسنل، انواع قرارداد، سمت‌های سازمانی، معاونت‌ها و واحدها را می‌سازد و
چارت سازمانی را از زنجیره‌ی مدیران بازسازی می‌کند.

---

## `overtime:send-ceiling-warnings` — هشدار سقف

```bash
php artisan overtime:send-ceiling-warnings
```

هشدار نزدیک‌شدن یا عبور از سقف اضافه‌کار را برای منابع انسانی و مدیر سازمان
ارسال می‌کند. معمولاً روی cron تنظیم می‌شود.

---

## دستورهای مفید لاراول

```bash
# پاک‌کردن کش‌ها (پس از تغییر دسترسی‌ها یا کانفیگ)
php artisan config:clear && php artisan route:clear && php artisan view:clear

# اجرای مهاجرت‌ها
php artisan migrate --force

# ثبت دسترسی‌ها و نقش‌های پایه (idempotent - تکرارش بی‌خطر است)
php artisan db:seed --class="Database\Seeders\PermissionSeeder" --force
php artisan db:seed --class="Database\Seeders\RoleSeeder" --force
php artisan db:seed --class="Database\Seeders\RolePermissionSeeder" --force
php artisan db:seed --class="Database\Seeders\AdminUserSeeder" --force
```

> **هشدار:** `AdminUserSeeder` رمز کاربر `admin` را به `123456` بازمی‌گرداند.
> پس از اجرای آن حتماً رمز را عوض کنید:
> `php artisan user:password admin --random`

---

## ساختار دسترسی‌ها

دسترسی‌ها در `config/permissions.php` تعریف شده‌اند و در سه گروه‌اند:

| گروه | نمونه | توضیح |
|---|---|---|
| `menus` | `menu/personnel`، `menu/overtime-entry` | هر منو یک دسترسی مستقل دارد |
| `overtime` | `overtime/hr-manage`، `overtime/org-chart-manage` | عملیات ماژول اضافه‌کار |
| `system` | `system-manager`، `admin/users` | مدیریت سیستم |

`system-manager` دسترسی ویژه است: هر کاربری که آن را داشته باشد، **همه‌ی**
دسترسی‌ها را دارد و بررسی‌های دیگر دور زده می‌شوند.

کاربر فقط منوهایی را می‌بیند که نقش‌هایش دسترسی `menu/*` آن را دارند، و مسیرها هم
در سطح route محافظت می‌شوند - نه فقط پنهان‌سازی در سایدبار.

---

## نکته‌ی امنیتی: فرم پروفایل

در صفحه‌ی **پروفایل**، نقش سازمانی فقط **نمایش داده می‌شود** و قابل تغییر نیست؛
تغییر نقش فقط از فهرست پرسنل و توسط مدیر سیستم ممکن است.

حتی اگر کسی دستی `roles[]` را به مسیر `admin/profile` بفرستد، نادیده گرفته
می‌شود - تا کاربر نتواند دسترسی خودش را ارتقا دهد.
