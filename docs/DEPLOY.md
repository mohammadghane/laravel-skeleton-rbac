# راه‌اندازی پروژه با `deploy.sh`

اسکریپت `deploy.sh` پروژه را **از صفر** روی یک سرور خالی بالا می‌آورد. یعنی لازم نیست
از قبل چیزی نصب باشد؛ اسکریپت خودش این‌ها را انجام می‌دهد:

1. **PHP** - اگر نسخه‌ی مناسبی نصب نباشد، مخزن رسمی (sury روی دبیان، ppa:ondrej روی
   اوبونتو، remi روی RHEL) را اضافه و PHP ۸.۳ را نصب می‌کند.
2. **افزونه‌های PHP** - `bcmath`، `pdo_mysql`، `mbstring`، `intl`، `zip`، `gd`، `xml`، `curl`
3. **Composer** - با بررسی امضای نصب‌کننده‌ی رسمی
4. **دیتابیس** - نصب MariaDB، ساخت دیتابیس `utf8mb4` و کاربر با رمز تصادفی
5. **وب‌سرور** - Apache یا Nginx به‌همراه vhost و ثبت دامنه در `/etc/hosts`
6. **پروژه** - `composer install`، `APP_KEY`، تنظیم `.env`، دسترسی‌ها، migration
7. **کاربر مدیر** - دسترسی‌ها و نقش‌ها، و رمز تصادفی برای `admin`

## اجرا

```bash
# تعاملی - همه‌ی مقادیر را می‌پرسد (مقدار پیش‌فرض داخل کروشه)
sudo ./deploy.sh

# بدون سؤال، با پیش‌فرض‌ها (دامنه myowertime.local روی پورت ۸۰)
sudo ./deploy.sh --yes

# تعیین مقادیر از طریق متغیر محیطی
sudo DOMAIN=overtime.local PORT=8080 WEBSERVER=nginx ./deploy.sh --yes
```

## متغیرهای قابل تنظیم

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| `DOMAIN` | `myowertime.local` | نام دامنه |
| `PORT` | `80` | پورت وب‌سرور |
| `WEBSERVER` | تشخیص خودکار | `apache` یا `nginx` |
| `OS_FAMILY` | تشخیص خودکار | `debian` یا `rhel` |
| `PHP_VERSION` | بالاترین نسخه‌ی ≥ ۸.۳ | نسخه‌ی PHP-FPM (در صورت نبود، ۸.۳ نصب می‌شود) |
| `APP_DIR` | پوشه‌ی اسکریپت | مسیر پروژه |
| `RUN_MIGRATIONS` | `yes` | اجرای `php artisan migrate` |
| `RUN_SEEDERS` | `no` | ساخت داده‌ی نمونه‌ی دمو |
| `INSTALL_DB` | `yes` | نصب و پیکربندی MariaDB |
| `DB_NAME` | `overtime` | نام دیتابیس |
| `DB_USER` | `overtime` | کاربر دیتابیس |
| `DB_PASS` | ساخت خودکار | رمز دیتابیس |
| `DB_HOST` | `127.0.0.1` | میزبان دیتابیس |
| `DB_PORT` | `3306` | پورت دیتابیس |
| `ADMIN_PASS` | ساخت خودکار | رمز کاربر `admin` |

همه‌ی این‌ها هم از طریق متغیر محیطی و هم به‌صورت تعاملی قابل تنظیم‌اند.

## رمزهای ساخته‌شده

اگر `DB_PASS` و `ADMIN_PASS` را ندهید، اسکریپت رمز تصادفی می‌سازد و **در پایان
یک‌بار نمایش می‌دهد**. رمز دیتابیس در `.env` ذخیره می‌شود (که با `chmod 640` محافظت
می‌شود)، ولی رمز `admin` هش شده و بازیابی‌شدنی نیست - حتماً یادداشتش کنید.

اگر گمش کردید:

```bash
php artisan user:password admin --random
```

> **توجه:** `AdminUserSeeder` رمز پیش‌فرض `123456` دارد که برای سرور واقعی خطرناک
> است. اسکریپت بلافاصله پس از اجرای seeder آن را با رمز قوی جایگزین می‌کند. اگر این
> مرحله با خطا مواجه شود، اسکریپت هشدار می‌دهد - آن هشدار را جدی بگیرید.

## نکته‌ی مهم: نسخه‌ی PHP

پروژه به **PHP ≥ ۸.۳** نیاز دارد (`composer.json`)، و در `composer.json` روی
`config.platform.php = 8.3` قفل شده است.

**چرا این قفل مهم است:** بدون آن، اجرای `composer install` روی ماشینی با PHP ۸.۵
بسته‌هایی نصب می‌کند که به PHP ≥ ۸.۴ نیاز دارند (مثلاً `symfony/*` نسخه‌ی ۸.x). آن
`vendor` روی سرور ۸.۳ اجرا نمی‌شود. با این قفل، composer روی هر ماشینی همان
بسته‌هایی را می‌گیرد که روی ۸.۳ کار می‌کنند.

اگر روزی خواستید نسخه‌ی PHP سرور را بالا ببرید، `config.platform.php` را هم
به‌روز کنید وگرنه از نسخه‌های جدیدتر بسته‌ها بی‌بهره می‌مانید. روی بسیاری از سرورها Apache با
`mod_php` و نسخه‌ی قدیمی‌تر اجرا می‌شود - مثلاً روی همین سرور توسعه، Apache با
`mod_php8.1` بالا بود در حالی که CLI روی ۸.۵ است.

اسکریپت این وضعیت را مدیریت می‌کند:

1. بالاترین نسخه‌ی PHP ≥ ۸.۲ را پیدا می‌کند.
2. اگر `mod_php` قدیمی فعال باشد **غیرفعالش می‌کند** (`a2dismod php8.1`).
3. Apache را با `proxy_fcgi` به **PHP-FPM** وصل می‌کند.

بدون این کار، پروژه با خطای نسخه‌ی PHP بالا نمی‌آمد.

## آنچه اسکریپت انجام می‌دهد

1. **پیش‌نیازها** — نصب وب‌سرور، `php{نسخه}-fpm` و افزونه‌های لازم
   (`mbstring`, `xml`, `zip`, `curl`, `mysql`, `bcmath`, `gd`, `intl`).
2. **hosts** — افزودن `127.0.0.1 <domain>` (اگر از قبل نباشد).
3. **پروژه** — ساخت `.env`، `composer install`، `APP_KEY`، تنظیم `APP_URL` مطابق
   دامنه/پورت، دسترسی `storage` و `bootstrap/cache`، `storage:link`، migration و
   (اختیاری) seeder.
4. **وب‌سرور** — ساخت vhost/server-block، فعال‌سازی سایت، افزودن `Listen` برای پورت
   غیر ۸۰، و `configtest` پیش از restart.
5. **سرویس‌ها** — `enable` و `restart` کردن FPM و وب‌سرور.
6. **بررسی نهایی** — یک درخواست HTTP واقعی و گزارش کد پاسخ.

## پس از اجرا

آدرس: `http://myowertime.local`

اگر با `RUN_SEEDERS=yes` اجرا کرده باشید، کاربران نمونه ساخته می‌شوند
(کلمه‌ی عبور همه: `secret123`):

| کاربر | نقش |
|---|---|
| `admin` | مدیر سیستم |
| `deputy.it` | معاون فناوری اطلاعات |
| `manager.dev` / `manager.infra` | مدیر واحد |

## عیب‌یابی

**خطای ۵۰۰**
```bash
tail -50 storage/logs/laravel.log
tail -50 /var/log/apache2/myowertime.local-error.log
```

**خطای دسترسی روی storage**
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

**دامنه باز نمی‌شود** — بررسی کنید رکورد در `/etc/hosts` باشد و سرویس بالا باشد:
```bash
grep myowertime /etc/hosts
systemctl status apache2 php8.3-fpm
```

**migration ناموفق** — تنظیمات `DB_*` در `.env` را بررسی کنید؛ اسکریپت دیتابیس نمی‌سازد:
```sql
CREATE DATABASE overtime CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## محدودیت‌ها

- **HTTPS تنظیم نمی‌شود.** برای دامنه‌ی واقعی از `certbot --apache` استفاده کنید.
- **دیتابیس ساخته نمی‌شود** و MySQL/MariaDB نصب نمی‌شود؛ فقط migration اجرا می‌گردد.
- روی `rhel` (CentOS/Rocky) مسیرها پیش‌بینی شده ولی فقط روی Ubuntu 22.04 آزمایش شده است.
