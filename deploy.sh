#!/usr/bin/env bash
#
# راه‌اندازی پروژه‌ی «مدیریت اضافه‌کار پرسنل» روی یک وب‌سرور محلی.
#
# استفاده:
#   sudo ./deploy.sh                    # تعاملی: مقادیر را می‌پرسد (پیش‌فرض‌ها در پرانتز)
#   sudo ./deploy.sh --yes              # بدون سؤال، با مقادیر پیش‌فرض/متغیرهای محیطی
#   sudo DOMAIN=x.local PORT=8080 ./deploy.sh --yes
#
# متغیرهای قابل تنظیم (هم از محیط، هم تعاملی):
#   DOMAIN      نام دامنه            (پیش‌فرض: myowertime.local)
#   PORT        پورت وب‌سرور          (پیش‌فرض: 80)
#   WEBSERVER   apache | nginx        (پیش‌فرض: تشخیص خودکار)
#   OS_FAMILY   debian | rhel         (پیش‌فرض: تشخیص خودکار از /etc/os-release)
#   PHP_VERSION نسخه‌ی PHP برای FPM   (پیش‌فرض: تشخیص خودکار، حداقل 8.2)
#   APP_DIR     مسیر پروژه            (پیش‌فرض: پوشه‌ی همین اسکریپت)
#   RUN_MIGRATIONS  yes|no            (پیش‌فرض: yes)
#   RUN_SEEDERS     yes|no            (پیش‌فرض: no  - داده‌ی نمونه)
#
set -euo pipefail

# ─────────────────────────── پیش‌فرض‌ها ───────────────────────────
DOMAIN="${DOMAIN:-myowertime.local}"
PORT="${PORT:-80}"
WEBSERVER="${WEBSERVER:-}"
OS_FAMILY="${OS_FAMILY:-}"
PHP_VERSION="${PHP_VERSION:-}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-yes}"
RUN_SEEDERS="${RUN_SEEDERS:-no}"
ASSUME_YES="no"

[[ "${1:-}" == "--yes" || "${1:-}" == "-y" ]] && ASSUME_YES="yes"

# حداقل نسخه‌ی PHP که پروژه (composer.json: php ^8.2) نیاز دارد.
readonly MIN_PHP_MAJOR=8
readonly MIN_PHP_MINOR=2

# ─────────────────────────── ابزار نمایش ───────────────────────────
c_reset=$'\033[0m'; c_bold=$'\033[1m'; c_red=$'\033[31m'
c_green=$'\033[32m'; c_yellow=$'\033[33m'; c_blue=$'\033[34m'

info()  { printf '%s▸%s %s\n' "$c_blue"   "$c_reset" "$*"; }
ok()    { printf '%s✔%s %s\n' "$c_green"  "$c_reset" "$*"; }
warn()  { printf '%s!%s %s\n' "$c_yellow" "$c_reset" "$*"; }
die()   { printf '%s✘ %s%s\n' "$c_red"    "$*" "$c_reset" >&2; exit 1; }
step()  { printf '\n%s%s%s\n' "$c_bold" "$*" "$c_reset"; }

# پرسش تعاملی با مقدار پیش‌فرض؛ در حالت --yes مستقیم پیش‌فرض را برمی‌گرداند.
ask() {
    local prompt="$1" default="$2" answer
    if [[ "$ASSUME_YES" == "yes" ]]; then
        printf '%s\n' "$default"
        return
    fi
    read -r -p "$prompt [$default]: " answer </dev/tty || answer=""
    printf '%s\n' "${answer:-$default}"
}

# ─────────────────────────── بررسی‌های اولیه ───────────────────────────
[[ $EUID -eq 0 ]] || die "این اسکریپت باید با sudo اجرا شود:  sudo $0"

detect_os() {
    [[ -n "$OS_FAMILY" ]] && return
    if [[ -f /etc/os-release ]]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        case "${ID:-}${ID_LIKE:-}" in
            *debian*|*ubuntu*) OS_FAMILY="debian" ;;
            *rhel*|*fedora*|*centos*|*rocky*|*alma*) OS_FAMILY="rhel" ;;
            *) OS_FAMILY="unknown" ;;
        esac
    else
        OS_FAMILY="unknown"
    fi
}

detect_webserver() {
    [[ -n "$WEBSERVER" ]] && return
    if command -v apache2 >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then
        WEBSERVER="apache"
    elif command -v nginx >/dev/null 2>&1; then
        WEBSERVER="nginx"
    else
        WEBSERVER="apache"   # پیش‌فرض؛ در ادامه نصب می‌شود
    fi
}

# بالاترین نسخه‌ی PHP نصب‌شده که >= 8.2 باشد را پیدا می‌کند.
detect_php_version() {
    [[ -n "$PHP_VERSION" ]] && return

    local best="" v
    for v in $(ls -1 /etc/php 2>/dev/null | sort -V -r); do
        local major="${v%%.*}" minor="${v##*.}"
        if (( major > MIN_PHP_MAJOR )) || { (( major == MIN_PHP_MAJOR )) && (( minor >= MIN_PHP_MINOR )); }; then
            best="$v"; break
        fi
    done

    # اگر پوشه‌ی /etc/php نبود (مثلاً RHEL)، از خود php بپرس
    if [[ -z "$best" ]] && command -v php >/dev/null 2>&1; then
        best="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    fi

    PHP_VERSION="$best"
}

detect_os
detect_webserver
detect_php_version

# ─────────────────────────── جمع‌آوری تنظیمات ───────────────────────────
step "تنظیمات راه‌اندازی"

DOMAIN="$(ask 'نام دامنه' "$DOMAIN")"
PORT="$(ask 'پورت' "$PORT")"
WEBSERVER="$(ask 'وب‌سرور (apache/nginx)' "$WEBSERVER")"
OS_FAMILY="$(ask 'خانواده‌ی سیستم‌عامل (debian/rhel)' "$OS_FAMILY")"
PHP_VERSION="$(ask 'نسخه‌ی PHP' "$PHP_VERSION")"
APP_DIR="$(ask 'مسیر پروژه' "$APP_DIR")"
RUN_MIGRATIONS="$(ask 'اجرای migration؟ (yes/no)' "$RUN_MIGRATIONS")"
RUN_SEEDERS="$(ask 'ساخت داده‌ی نمونه‌ی دمو؟ (yes/no)' "$RUN_SEEDERS")"

# ── اعتبارسنجی ──
[[ "$PORT" =~ ^[0-9]+$ ]] && (( PORT > 0 && PORT < 65536 )) || die "پورت نامعتبر: $PORT"
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "نام دامنه نامعتبر: $DOMAIN"
[[ "$WEBSERVER" == "apache" || "$WEBSERVER" == "nginx" ]] || die "وب‌سرور باید apache یا nginx باشد."
[[ -f "$APP_DIR/artisan" ]] || die "مسیر پروژه معتبر نیست (فایل artisan پیدا نشد): $APP_DIR"
[[ -n "$PHP_VERSION" ]] || die "نسخه‌ی PHP تشخیص داده نشد؛ با PHP_VERSION=8.3 مشخصش کنید."

# نسخه‌ی PHP باید حداقل ۸.۲ باشد، وگرنه پروژه اصلاً بوت نمی‌شود.
php_major="${PHP_VERSION%%.*}"; php_minor="${PHP_VERSION##*.}"
if (( php_major < MIN_PHP_MAJOR )) || { (( php_major == MIN_PHP_MAJOR )) && (( php_minor < MIN_PHP_MINOR )); }; then
    die "این پروژه به PHP >= ${MIN_PHP_MAJOR}.${MIN_PHP_MINOR} نیاز دارد (نسخه‌ی داده‌شده: $PHP_VERSION)."
fi

printf '\n'
info "دامنه       : $DOMAIN:$PORT"
info "وب‌سرور     : $WEBSERVER"
info "سیستم‌عامل  : $OS_FAMILY"
info "PHP         : $PHP_VERSION (FPM)"
info "مسیر پروژه  : $APP_DIR"
printf '\n'

if [[ "$ASSUME_YES" != "yes" ]]; then
    read -r -p "ادامه می‌دهید؟ (y/N): " confirm </dev/tty || confirm=""
    [[ "$confirm" =~ ^[Yy]$ ]] || die "لغو شد."
fi

# ─────────────────────────── نصب بسته‌ها ───────────────────────────
pkg_install() {
    case "$OS_FAMILY" in
        debian) DEBIAN_FRONTEND=noninteractive apt-get install -y "$@" >/dev/null ;;
        rhel)   (command -v dnf >/dev/null && dnf install -y "$@" >/dev/null) || yum install -y "$@" >/dev/null ;;
        *)      warn "نصب خودکار بسته روی این سیستم‌عامل پشتیبانی نمی‌شود؛ این‌ها را دستی نصب کنید: $*"; return 1 ;;
    esac
}

step "۱) بررسی و نصب پیش‌نیازها"

if [[ "$OS_FAMILY" == "debian" ]]; then
    apt-get update -qq >/dev/null 2>&1 || warn "apt-get update با خطا مواجه شد؛ ادامه می‌دهیم."
fi

# وب‌سرور
if [[ "$WEBSERVER" == "apache" ]]; then
    APACHE_BIN="apache2"; APACHE_SVC="apache2"
    [[ "$OS_FAMILY" == "rhel" ]] && { APACHE_BIN="httpd"; APACHE_SVC="httpd"; }

    if ! command -v "$APACHE_BIN" >/dev/null 2>&1; then
        info "نصب $APACHE_BIN …"
        pkg_install "$APACHE_BIN" || die "نصب $APACHE_BIN ناموفق بود."
    fi
    ok "$APACHE_BIN موجود است."
else
    if ! command -v nginx >/dev/null 2>&1; then
        info "نصب nginx …"
        pkg_install nginx || die "نصب nginx ناموفق بود."
    fi
    ok "nginx موجود است."
fi

# PHP-FPM
FPM_SVC="php${PHP_VERSION}-fpm"
FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
if [[ "$OS_FAMILY" == "rhel" ]]; then
    FPM_SVC="php-fpm"
    FPM_SOCK="/run/php-fpm/www.sock"
fi

if ! systemctl list-unit-files 2>/dev/null | grep -q "^${FPM_SVC}"; then
    info "نصب $FPM_SVC …"
    if [[ "$OS_FAMILY" == "debian" ]]; then
        pkg_install "php${PHP_VERSION}-fpm" || die "نصب php${PHP_VERSION}-fpm ناموفق بود."
    else
        pkg_install php-fpm || die "نصب php-fpm ناموفق بود."
    fi
fi
ok "$FPM_SVC موجود است."

# افزونه‌های PHP لازم پروژه (اکسل/تاریخ شمسی/دیتابیس)
if [[ "$OS_FAMILY" == "debian" ]]; then
    missing_ext=()
    for ext in mbstring xml zip curl mysql bcmath gd intl; do
        php"$PHP_VERSION" -m 2>/dev/null | grep -qi "^${ext}$" || missing_ext+=("php${PHP_VERSION}-${ext}")
    done
    # gd/bcmath نام ماژول با نام بسته فرق دارد؛ بررسی دقیق‌تر:
    php"$PHP_VERSION" -m 2>/dev/null | grep -qi '^gd$'     || missing_ext+=("php${PHP_VERSION}-gd")
    php"$PHP_VERSION" -m 2>/dev/null | grep -qi '^bcmath$' || missing_ext+=("php${PHP_VERSION}-bcmath")

    if (( ${#missing_ext[@]} )); then
        # حذف تکراری‌ها
        mapfile -t missing_ext < <(printf '%s\n' "${missing_ext[@]}" | sort -u)
        info "نصب افزونه‌های PHP: ${missing_ext[*]}"
        pkg_install "${missing_ext[@]}" || warn "نصب برخی افزونه‌ها ناموفق بود؛ ادامه می‌دهیم."
    fi
fi
ok "افزونه‌های PHP بررسی شد."

# ─────────────────────────── فایل hosts ───────────────────────────
step "۲) ثبت دامنه در /etc/hosts"

if grep -qE "^[0-9.]+[[:space:]]+.*\b${DOMAIN}\b" /etc/hosts; then
    ok "$DOMAIN از قبل در /etc/hosts هست."
else
    printf '127.0.0.1\t%s\n' "$DOMAIN" >> /etc/hosts
    ok "$DOMAIN به /etc/hosts اضافه شد."
fi

# ─────────────────────────── آماده‌سازی پروژه ───────────────────────────
step "۳) آماده‌سازی پروژه"

cd "$APP_DIR"

# کاربر/گروه وب‌سرور
WEB_USER="www-data"; WEB_GROUP="www-data"
if [[ "$OS_FAMILY" == "rhel" ]]; then
    WEB_USER="apache"; WEB_GROUP="apache"
    [[ "$WEBSERVER" == "nginx" ]] && { WEB_USER="nginx"; WEB_GROUP="nginx"; }
fi

# .env
if [[ ! -f .env ]]; then
    if [[ -f .env.example ]]; then
        cp .env.example .env
        ok ".env از روی .env.example ساخته شد."
    else
        warn ".env و .env.example هیچ‌کدام وجود ندارند؛ باید دستی بسازید."
    fi
fi

# وابستگی‌های composer
if [[ ! -d vendor ]]; then
    if command -v composer >/dev/null 2>&1; then
        info "نصب وابستگی‌های composer …"
        # ext-gd الزام PhpSpreadsheet است ولی خروجی این پروژه فقط متن/عدد است؛
        # اگر gd نصب نشد، با نادیده‌گرفتن همان یک الزام ادامه می‌دهیم.
        sudo -u "$WEB_USER" -H composer install --no-interaction --no-dev --optimize-autoloader 2>/dev/null \
            || composer install --no-interaction --no-dev --optimize-autoloader --ignore-platform-req=ext-gd \
            || warn "نصب composer ناموفق بود؛ دستی اجرا کنید: composer install"
    else
        warn "composer نصب نیست؛ پوشه‌ی vendor ساخته نشد."
    fi
fi

# APP_KEY
if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
    php artisan key:generate --force >/dev/null 2>&1 && ok "APP_KEY ساخته شد." || warn "ساخت APP_KEY ناموفق بود."
fi

# APP_URL را با دامنه/پورت هم‌تراز می‌کنیم تا لینک‌های تولیدی درست باشند.
APP_URL="http://${DOMAIN}"
[[ "$PORT" != "80" ]] && APP_URL="http://${DOMAIN}:${PORT}"
if grep -qE '^APP_URL=' .env; then
    sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
else
    printf 'APP_URL=%s\n' "$APP_URL" >> .env
fi
ok "APP_URL روی $APP_URL تنظیم شد."

# دسترسی‌ها: storage و bootstrap/cache باید برای وب‌سرور نوشتنی باشند.
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R "$WEB_USER":"$WEB_GROUP" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
ok "دسترسی storage و bootstrap/cache تنظیم شد."

# لینک storage عمومی
[[ -L public/storage ]] || php artisan storage:link >/dev/null 2>&1 || true

# migration و seeder
if [[ "$RUN_MIGRATIONS" == "yes" ]]; then
    info "اجرای migration …"
    if php artisan migrate --force 2>&1 | tail -3; then
        ok "migration انجام شد."
    else
        warn "migration ناموفق بود - تنظیمات DB_* در .env را بررسی کنید."
    fi
fi

if [[ "$RUN_SEEDERS" == "yes" ]]; then
    info "ساخت داده‌ی نمونه …"
    php artisan db:seed --class="Database\\Seeders\\OvertimeOrgSeeder" --force >/dev/null 2>&1 || true
    php artisan db:seed --class="Database\\Seeders\\PermissionSeeder" --force >/dev/null 2>&1 || true
    php artisan db:seed --class="Database\\Seeders\\RolePermissionSeeder" --force >/dev/null 2>&1 || true
    php artisan db:seed --class="Database\\Seeders\\OvertimeDemoSeeder" --force >/dev/null 2>&1 \
        && ok "داده‌ی نمونه ساخته شد (کلمه‌ی عبور کاربران دمو: secret123)." \
        || warn "ساخت داده‌ی نمونه ناموفق بود."
fi

# پاک‌سازی کش‌های قدیمی (مسیر/کانفیگ قبلی می‌تواند باعث 404 شود)
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear  >/dev/null 2>&1 || true
php artisan view:clear   >/dev/null 2>&1 || true
ok "کش‌های لاراول پاک شد."

# ─────────────────────────── پیکربندی وب‌سرور ───────────────────────────
step "۴) پیکربندی $WEBSERVER"

DOC_ROOT="$APP_DIR/public"

if [[ "$WEBSERVER" == "apache" ]]; then
    if [[ "$OS_FAMILY" == "debian" ]]; then
        SITES_DIR="/etc/apache2/sites-available"
        VHOST="$SITES_DIR/${DOMAIN}.conf"
        PORTS_CONF="/etc/apache2/ports.conf"
    else
        SITES_DIR="/etc/httpd/conf.d"
        VHOST="$SITES_DIR/${DOMAIN}.conf"
        PORTS_CONF="/etc/httpd/conf/httpd.conf"
    fi

    # ماژول‌های لازم. توجه: بسیاری از سرورها هنوز mod_php دارند که با نسخه‌ی قدیمی PHP
    # اجرا می‌شود؛ چون این پروژه PHP >= 8.2 می‌خواهد، به FPM سوییچ می‌کنیم.
    if [[ "$OS_FAMILY" == "debian" ]]; then
        a2enmod rewrite proxy proxy_fcgi setenvif >/dev/null 2>&1 || true

        # غیرفعال‌کردن mod_php قدیمی (در صورت وجود) - وگرنه FPM نادیده گرفته می‌شود.
        for m in /etc/apache2/mods-enabled/php*.load; do
            [[ -e "$m" ]] || continue
            old_php="$(basename "$m" .load)"
            warn "غیرفعال‌کردن ماژول قدیمی $old_php (پروژه به PHP $PHP_VERSION نیاز دارد)."
            a2dismod "$old_php" >/dev/null 2>&1 || true
        done

        a2enconf "php${PHP_VERSION}-fpm" >/dev/null 2>&1 || true
    fi

    # پورت غیر ۸۰ باید در ports.conf اعلام شود
    if [[ "$PORT" != "80" ]] && ! grep -qE "^[[:space:]]*Listen[[:space:]]+${PORT}\b" "$PORTS_CONF"; then
        printf '\nListen %s\n' "$PORT" >> "$PORTS_CONF"
        ok "Listen $PORT به $(basename "$PORTS_CONF") اضافه شد."
    fi

    cat > "$VHOST" <<EOF
# ساخته‌شده توسط deploy.sh - پروژه‌ی مدیریت اضافه‌کار پرسنل
<VirtualHost *:${PORT}>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    DocumentRoot ${DOC_ROOT}

    <Directory ${DOC_ROOT}>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # اجرای PHP از طریق FPM (نه mod_php) تا نسخه‌ی درست PHP استفاده شود.
    <FilesMatch \\.php\$>
        SetHandler "proxy:unix:${FPM_SOCK}|fcgi://localhost/"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN}-access.log combined
</VirtualHost>
EOF
    ok "فایل vhost ساخته شد: $VHOST"

    [[ "$OS_FAMILY" == "debian" ]] && { a2ensite "${DOMAIN}.conf" >/dev/null 2>&1 || true; }

    "$APACHE_BIN"ctl configtest >/dev/null 2>&1 || apachectl configtest || die "پیکربندی Apache خطا دارد."

else
    # ── nginx ──
    if [[ -d /etc/nginx/sites-available ]]; then
        VHOST="/etc/nginx/sites-available/${DOMAIN}.conf"
        LINK="/etc/nginx/sites-enabled/${DOMAIN}.conf"
    else
        VHOST="/etc/nginx/conf.d/${DOMAIN}.conf"
        LINK=""
    fi

    cat > "$VHOST" <<EOF
# ساخته‌شده توسط deploy.sh - پروژه‌ی مدیریت اضافه‌کار پرسنل
server {
    listen ${PORT};
    listen [::]:${PORT};

    server_name ${DOMAIN} www.${DOMAIN};
    root ${DOC_ROOT};
    index index.php index.html;

    charset utf-8;
    client_max_body_size 20M;   # آپلود فایل اکسل

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${FPM_SOCK};
    }

    location ~ /\\.(?!well-known).* { deny all; }

    error_log  /var/log/nginx/${DOMAIN}-error.log;
    access_log /var/log/nginx/${DOMAIN}-access.log;
}
EOF
    ok "فایل server block ساخته شد: $VHOST"

    [[ -n "$LINK" ]] && ln -sf "$VHOST" "$LINK"
    nginx -t >/dev/null 2>&1 || nginx -t || die "پیکربندی nginx خطا دارد."
fi

# ─────────────────────────── راه‌اندازی سرویس‌ها ───────────────────────────
step "۵) راه‌اندازی سرویس‌ها"

systemctl enable --now "$FPM_SVC" >/dev/null 2>&1 || true
systemctl restart "$FPM_SVC" >/dev/null 2>&1 && ok "$FPM_SVC اجرا شد." || warn "راه‌اندازی $FPM_SVC ناموفق بود."

if [[ "$WEBSERVER" == "apache" ]]; then
    systemctl enable --now "$APACHE_SVC" >/dev/null 2>&1 || true
    systemctl restart "$APACHE_SVC" >/dev/null 2>&1 && ok "$APACHE_SVC اجرا شد." || die "راه‌اندازی $APACHE_SVC ناموفق بود."
else
    systemctl enable --now nginx >/dev/null 2>&1 || true
    systemctl restart nginx >/dev/null 2>&1 && ok "nginx اجرا شد." || die "راه‌اندازی nginx ناموفق بود."
fi

# ─────────────────────────── بررسی نهایی ───────────────────────────
step "۶) بررسی نهایی"

URL="http://${DOMAIN}"
[[ "$PORT" != "80" ]] && URL="http://${DOMAIN}:${PORT}"

sleep 2
code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN}" "http://127.0.0.1:${PORT}/" 2>/dev/null || echo 000)"

case "$code" in
    200|302)
        ok "سایت پاسخ داد (HTTP $code)"
        printf '\n%s✔ راه‌اندازی کامل شد.%s\n' "$c_green$c_bold" "$c_reset"
        printf '  آدرس: %s%s%s\n' "$c_bold" "$URL" "$c_reset"
        [[ "$RUN_SEEDERS" == "yes" ]] && printf '  ورود دمو: admin  /  secret123\n'
        ;;
    500)
        warn "سایت با خطای ۵۰۰ پاسخ داد. لاگ را ببینید:"
        printf '    tail -50 %s/storage/logs/laravel.log\n' "$APP_DIR"
        ;;
    000)
        warn "پاسخی دریافت نشد. بررسی کنید سرویس بالا باشد و پورت $PORT باز باشد."
        ;;
    *)
        warn "پاسخ غیرمنتظره: HTTP $code"
        ;;
esac

printf '\n'
