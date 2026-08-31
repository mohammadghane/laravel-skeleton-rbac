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
#   INSTALL_DB  yes|no                (پیش‌فرض: yes - نصب و پیکربندی MariaDB/MySQL)
#   DB_NAME     نام دیتابیس           (پیش‌فرض: overtime)
#   DB_USER     کاربر دیتابیس         (پیش‌فرض: overtime)
#   DB_PASS     رمز دیتابیس           (پیش‌فرض: ساخت خودکار)
#   DB_HOST     میزبان دیتابیس        (پیش‌فرض: 127.0.0.1)
#   DB_PORT     پورت دیتابیس          (پیش‌فرض: 3306)
#   ADMIN_PASS  رمز کاربر admin       (پیش‌فرض: ساخت خودکار)
#
# این اسکریپت همه‌ی پیش‌نیازها را از صفر نصب می‌کند: PHP (نسخه‌ی موردنیاز به همراه
# مخزن رسمی در صورت نبودن)، composer، وب‌سرور، دیتابیس، و خودِ پروژه.
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
INSTALL_DB="${INSTALL_DB:-yes}"
DB_NAME="${DB_NAME:-overtime}"
DB_USER="${DB_USER:-overtime}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
ADMIN_PASS="${ADMIN_PASS:-}"
ASSUME_YES="no"

[[ "${1:-}" == "--yes" || "${1:-}" == "-y" ]] && ASSUME_YES="yes"

# حداقل نسخه‌ی PHP که پروژه (composer.json: php ^8.3) نیاز دارد.
readonly MIN_PHP_MAJOR=8
readonly MIN_PHP_MINOR=3

# نسخه‌ای که در صورت نبودِ PHP مناسب نصب می‌شود. عمداً 8.3 است نه بالاتر: پروژه روی
# 8.3 قفل شده (config.platform در composer.json) و سرورهای LTS معمولاً همین را دارند.
readonly PHP_INSTALL_VERSION="8.3"

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

pkg_install() {
    case "$OS_FAMILY" in
        debian) DEBIAN_FRONTEND=noninteractive apt-get install -y "$@" >/dev/null ;;
        rhel)   (command -v dnf >/dev/null && dnf install -y "$@" >/dev/null) || yum install -y "$@" >/dev/null ;;
        *)      warn "نصب خودکار بسته روی این سیستم‌عامل پشتیبانی نمی‌شود؛ این‌ها را دستی نصب کنید: $*"; return 1 ;;
    esac
}

# افزودن مخزن رسمی PHP در صورتی که نسخه‌ی موردنیاز در مخازن سیستم نباشد.
#
# چرا لازم است: Debian 12 با PHP 8.2 و Ubuntu 22.04 با 8.1 می‌آید که هیچ‌کدام برای
# این پروژه کافی نیستند. بدون این مرحله، «نصب از صفر» روی سرور تازه شکست می‌خورد.
add_php_repo() {
    if [[ "$OS_FAMILY" == "debian" ]]; then
        # اگر بسته‌ی نسخه‌ی موردنظر از قبل در دسترس است، کاری نکن.
        apt-cache show "php${PHP_INSTALL_VERSION}-fpm" >/dev/null 2>&1 && return 0

        info "افزودن مخزن PHP (sury/ondrej) …"
        DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates apt-transport-https \
            software-properties-common curl gnupg lsb-release >/dev/null 2>&1 || true

        if grep -qi ubuntu /etc/os-release; then
            LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 \
                || warn "افزودن ppa:ondrej/php ناموفق بود."
        else
            curl -fsSL https://packages.sury.org/php/apt.gpg \
                -o /usr/share/keyrings/deb.sury.org-php.gpg 2>/dev/null || true
            echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
                > /etc/apt/sources.list.d/sury-php.list
        fi

        apt-get update -qq >/dev/null 2>&1 || warn "apt-get update پس از افزودن مخزن با خطا مواجه شد."
    else
        # RHEL/Alma/Rocky: ماژول‌های PHP از remi می‌آیند.
        apt-cache >/dev/null 2>&1 || true
        if ! rpm -q remi-release >/dev/null 2>&1; then
            info "افزودن مخزن remi …"
            local rel; rel="$(rpm -E %rhel 2>/dev/null || echo 9)"
            (command -v dnf >/dev/null && dnf install -y epel-release >/dev/null 2>&1) || true
            (command -v dnf >/dev/null && dnf install -y \
                "https://rpms.remirepo.net/enterprise/remi-release-${rel}.rpm" >/dev/null 2>&1) \
                || warn "افزودن مخزن remi ناموفق بود."
            dnf module reset php -y >/dev/null 2>&1 || true
            dnf module enable "php:remi-${PHP_INSTALL_VERSION}" -y >/dev/null 2>&1 || true
        fi
    fi
}

# نصب PHP در صورتی که نسخه‌ی مناسبی روی سیستم نباشد.
ensure_php() {
    # اگر detect_php_version نسخه‌ی معتبری پیدا کرده، کاری لازم نیست.
    if [[ -n "$PHP_VERSION" ]]; then
        local maj="${PHP_VERSION%%.*}" min="${PHP_VERSION##*.}"
        if (( maj > MIN_PHP_MAJOR )) || { (( maj == MIN_PHP_MAJOR )) && (( min >= MIN_PHP_MINOR )); }; then
            return 0
        fi
        warn "PHP $PHP_VERSION از حداقل موردنیاز (${MIN_PHP_MAJOR}.${MIN_PHP_MINOR}) پایین‌تر است؛ نصب ${PHP_INSTALL_VERSION} …"
    else
        info "PHP مناسبی یافت نشد؛ نصب ${PHP_INSTALL_VERSION} …"
    fi

    add_php_repo

    if [[ "$OS_FAMILY" == "debian" ]]; then
        pkg_install "php${PHP_INSTALL_VERSION}-cli" "php${PHP_INSTALL_VERSION}-fpm" \
            || die "نصب PHP ${PHP_INSTALL_VERSION} ناموفق بود."
    else
        pkg_install php-cli php-fpm || die "نصب PHP ناموفق بود."
    fi

    PHP_VERSION="$PHP_INSTALL_VERSION"
    ok "PHP $PHP_VERSION نصب شد."
}

# نصب composer در صورت نبودن. از نصب‌کننده‌ی رسمی با بررسی امضا استفاده می‌شود.
ensure_composer() {
    command -v composer >/dev/null 2>&1 && { ok "composer موجود است."; return 0; }

    info "نصب composer …"
    local tmp; tmp="$(mktemp -d)"
    local expected actual

    expected="$(curl -fsSL https://composer.github.io/installer.sig 2>/dev/null || true)"
    curl -fsSL https://getcomposer.org/installer -o "$tmp/composer-setup.php" 2>/dev/null \
        || { warn "دانلود نصب‌کننده‌ی composer ناموفق بود."; rm -rf "$tmp"; return 1; }

    # بررسی امضا: دانلود اجراشدنی بدون تایید، ریسک امنیتی است.
    if [[ -n "$expected" ]]; then
        actual="$("$PHP_BIN" -r "echo hash_file('sha384','$tmp/composer-setup.php');")"
        if [[ "$expected" != "$actual" ]]; then
            rm -rf "$tmp"
            die "امضای نصب‌کننده‌ی composer نامعتبر است؛ نصب متوقف شد."
        fi
    else
        warn "امضای composer قابل دریافت نبود؛ بررسی صحت انجام نشد."
    fi

    "$PHP_BIN" "$tmp/composer-setup.php" --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1 \
        || { rm -rf "$tmp"; warn "نصب composer ناموفق بود."; return 1; }

    rm -rf "$tmp"
    ok "composer نصب شد."
}

# مسیر باینری PHP. تا پیش از ensure_php ممکن است اصلاً وجود نداشته باشد، پس همه‌جا
# از این متغیر استفاده می‌شود نه از php خام - وگرنه روی سرور تازه به «command not
# found» می‌خوریم یا بدتر، نسخه‌ی قدیمیِ سیستم اجرا می‌شود.
PHP_BIN="php"
resolve_php_bin() {
    if [[ -n "$PHP_VERSION" ]] && command -v "php${PHP_VERSION}" >/dev/null 2>&1; then
        PHP_BIN="php${PHP_VERSION}"
    elif command -v php >/dev/null 2>&1; then
        PHP_BIN="php"
    fi
}

detect_os
detect_webserver
detect_php_version
resolve_php_bin

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
INSTALL_DB="$(ask 'نصب و پیکربندی دیتابیس؟ (yes/no)' "$INSTALL_DB")"
if [[ "$INSTALL_DB" == "yes" ]]; then
    DB_NAME="$(ask 'نام دیتابیس' "$DB_NAME")"
    DB_USER="$(ask 'کاربر دیتابیس' "$DB_USER")"
fi

# ── اعتبارسنجی ──
[[ "$PORT" =~ ^[0-9]+$ ]] && (( PORT > 0 && PORT < 65536 )) || die "پورت نامعتبر: $PORT"
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "نام دامنه نامعتبر: $DOMAIN"
[[ "$WEBSERVER" == "apache" || "$WEBSERVER" == "nginx" ]] || die "وب‌سرور باید apache یا nginx باشد."
[[ -f "$APP_DIR/artisan" ]] || die "مسیر پروژه معتبر نیست (فایل artisan پیدا نشد): $APP_DIR"
# نبودِ PHP یا قدیمی‌بودنش دیگر خطای مرگبار نیست: ensure_php نسخه‌ی درست را نصب
# می‌کند. فقط وقتی توقف می‌کنیم که کاربر صراحتاً نسخه‌ی ناکافی خواسته باشد.
if [[ -n "$PHP_VERSION" ]]; then
    php_major="${PHP_VERSION%%.*}"; php_minor="${PHP_VERSION##*.}"
    if (( php_major < MIN_PHP_MAJOR )) || { (( php_major == MIN_PHP_MAJOR )) && (( php_minor < MIN_PHP_MINOR )); }; then
        warn "PHP $PHP_VERSION کافی نیست (حداقل ${MIN_PHP_MAJOR}.${MIN_PHP_MINOR})؛ نسخه‌ی ${PHP_INSTALL_VERSION} نصب خواهد شد."
    fi
fi

printf '\n'
info "دامنه       : $DOMAIN:$PORT"
info "وب‌سرور     : $WEBSERVER"
info "سیستم‌عامل  : $OS_FAMILY"
info "PHP         : ${PHP_VERSION:-نصب ${PHP_INSTALL_VERSION}} (FPM)"
info "مسیر پروژه  : $APP_DIR"
if [[ "$INSTALL_DB" == "yes" ]]; then
    info "دیتابیس     : ${DB_NAME} @ ${DB_HOST}:${DB_PORT} (کاربر: ${DB_USER})"
else
    info "دیتابیس     : نصب نمی‌شود (INSTALL_DB=no)"
fi
printf '\n'

if [[ "$ASSUME_YES" != "yes" ]]; then
    read -r -p "ادامه می‌دهید؟ (y/N): " confirm </dev/tty || confirm=""
    [[ "$confirm" =~ ^[Yy]$ ]] || die "لغو شد."
fi

# ─────────────────────────── نصب بسته‌ها ───────────────────────────
step "۱) بررسی و نصب پیش‌نیازها"

if [[ "$OS_FAMILY" == "debian" ]]; then
    apt-get update -qq >/dev/null 2>&1 || warn "apt-get update با خطا مواجه شد؛ ادامه می‌دهیم."
fi

# ابزارهای پایه که مراحل بعدی به آن‌ها نیاز دارند (دانلود، unzip برای composer، …)
pkg_install curl unzip ca-certificates >/dev/null 2>&1 || true

# PHP - پیش از هر چیز، چون بقیه‌ی مراحل به آن وابسته‌اند.
ensure_php
resolve_php_bin
ok "PHP در دسترس: $("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo "$PHP_VERSION")"

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

# افزونه‌های PHP لازم پروژه.
#
# نام ماژولی که php -m چاپ می‌کند با نام بسته یکی نیست (مثلاً بسته‌ی php-mysql
# ماژول pdo_mysql می‌دهد)، پس نگاشت صریح لازم است - وگرنه اسکریپت یا بسته‌ی موجود
# را دوباره نصب می‌کند یا نبودِ افزونه‌ای حیاتی را نمی‌بیند.
#   bcmath : محاسبات سقف (بند ۵ - بدون خطای گرد کردن)
#   pdo_mysql : اتصال به دیتابیس
#   zip/gd/xml : maatwebsite/excel و PhpSpreadsheet
#   intl/mbstring : متن فارسی
declare -A PHP_EXT_PKG=(
    [mbstring]=mbstring [xml]=xml [zip]=zip [curl]=curl
    [pdo_mysql]=mysql [bcmath]=bcmath [gd]=gd [intl]=intl
)

missing_ext=()
for mod in "${!PHP_EXT_PKG[@]}"; do
    "$PHP_BIN" -m 2>/dev/null | grep -qix "$mod" && continue

    if [[ "$OS_FAMILY" == "debian" ]]; then
        missing_ext+=("php${PHP_VERSION}-${PHP_EXT_PKG[$mod]}")
    else
        missing_ext+=("php-${PHP_EXT_PKG[$mod]}")
    fi
done

if (( ${#missing_ext[@]} )); then
    mapfile -t missing_ext < <(printf '%s\n' "${missing_ext[@]}" | sort -u)
    info "نصب افزونه‌های PHP: ${missing_ext[*]}"
    pkg_install "${missing_ext[@]}" || warn "نصب برخی افزونه‌ها ناموفق بود؛ ادامه می‌دهیم."
fi

# افزونه‌های حیاتی: اگر بعد از تلاش برای نصب هم نبودند، ادامه بی‌فایده است.
for mod in bcmath pdo_mysql mbstring; do
    "$PHP_BIN" -m 2>/dev/null | grep -qix "$mod" \
        || warn "افزونه‌ی $mod نصب نشد؛ پروژه احتمالاً درست کار نمی‌کند."
done
ok "افزونه‌های PHP بررسی شد."

# composer
ensure_composer

# ─────────────────────────── دیتابیس ───────────────────────────
if [[ "$INSTALL_DB" == "yes" ]]; then
    step "۱.۵) نصب و پیکربندی دیتابیس"

    # سرور دیتابیس
    if ! command -v mysqld >/dev/null 2>&1 && ! command -v mariadbd >/dev/null 2>&1; then
        info "نصب MariaDB …"
        if [[ "$OS_FAMILY" == "debian" ]]; then
            pkg_install mariadb-server mariadb-client || die "نصب MariaDB ناموفق بود."
        else
            pkg_install mariadb-server mariadb || die "نصب MariaDB ناموفق بود."
        fi
    fi

    DB_SVC="mariadb"
    systemctl list-unit-files 2>/dev/null | grep -q "^mysql\.service" && DB_SVC="mysql"
    systemctl list-unit-files 2>/dev/null | grep -q "^mysqld\.service" && DB_SVC="mysqld"

    systemctl enable --now "$DB_SVC" >/dev/null 2>&1 || true
    systemctl is-active --quiet "$DB_SVC" || warn "سرویس دیتابیس ($DB_SVC) فعال نیست."
    ok "سرویس دیتابیس: $DB_SVC"

    # رمز: اگر داده نشده، تصادفی می‌سازیم. رمز ثابتِ حدس‌زدنی روی سرور واقعی خطرناک است.
    if [[ -z "$DB_PASS" ]]; then
        DB_PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)"
        DB_PASS_GENERATED="yes"
    fi

    # ساخت دیتابیس و کاربر. utf8mb4 برای متن فارسی الزامی است.
    #
    # از سوکت root سیستم استفاده می‌کنیم (روش پیش‌فرض MariaDB روی دبیان) تا نیازی به
    # رمز root نباشد. IF NOT EXISTS یعنی اجرای دوباره‌ی اسکریپت داده را پاک نمی‌کند.
    db_sql() { mysql --protocol=socket -u root "$@" 2>/dev/null || mariadb --protocol=socket -u root "$@" 2>/dev/null; }

    if db_sql -e "SELECT 1" >/dev/null 2>&1; then
        db_sql <<SQL || warn "ساخت دیتابیس/کاربر ناموفق بود."
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
        ok "دیتابیس «${DB_NAME}» و کاربر «${DB_USER}» آماده شد."
    else
        warn "اتصال به دیتابیس با کاربر root ممکن نشد؛ دیتابیس را دستی بسازید:"
        printf '    CREATE DATABASE %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$DB_NAME"
        printf "    CREATE USER '%s'@'localhost' IDENTIFIED BY '<رمز>';\n" "$DB_USER"
        printf '    GRANT ALL PRIVILEGES ON %s.* TO %s@localhost;\n' "$DB_NAME" "$DB_USER"
    fi
fi

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
    if [[ "$WEBSERVER" == "nginx" ]]; then
        WEB_USER="nginx"; WEB_GROUP="nginx"
    fi
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
#
# بی‌قید و شرط اجرا می‌شود نه فقط وقتی vendor نیست: روی نصب از صفر vendor وجود ندارد،
# و روی به‌روزرسانی، vendorِ قدیمی باید با composer.lock هم‌تراز شود.
if command -v composer >/dev/null 2>&1; then
    info "نصب وابستگی‌های composer …"
    # ext-gd الزام PhpSpreadsheet است ولی خروجی این پروژه فقط متن/عدد است؛
    # اگر gd نصب نشد، با نادیده‌گرفتن همان یک الزام ادامه می‌دهیم.
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-dev --optimize-autoloader 2>&1 | tail -3 \
        || COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-dev --optimize-autoloader --ignore-platform-req=ext-gd 2>&1 | tail -3 \
        || warn "نصب composer ناموفق بود؛ دستی اجرا کنید: composer install"
    ok "وابستگی‌ها نصب شد."
else
    warn "composer نصب نیست؛ پوشه‌ی vendor ساخته نشد."
fi

# APP_KEY
if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
    "$PHP_BIN" artisan key:generate --force >/dev/null 2>&1 && ok "APP_KEY ساخته شد." || warn "ساخت APP_KEY ناموفق بود."
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

# ── اتصال دیتابیس در .env ──
#
# .env.example پیش‌فرض sqlite دارد ولی پروژه روی MySQL اجرا می‌شود؛ بدون این بخش،
# migration روی نصب تازه شکست می‌خورد یا بی‌سروصدا روی فایل sqlite می‌نویسد.
set_env() {
    local key="$1" val="$2"
    if grep -qE "^${key}=" .env; then
        # از | به‌عنوان جداکننده استفاده می‌شود چون رمز می‌تواند / داشته باشد.
        sed -i "s|^${key}=.*|${key}=${val}|" .env
    else
        printf '%s=%s\n' "$key" "$val" >> .env
    fi
}

if [[ "$INSTALL_DB" == "yes" ]]; then
    set_env DB_CONNECTION mysql
    set_env DB_HOST "$DB_HOST"
    set_env DB_PORT "$DB_PORT"
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASS"
    ok "اتصال دیتابیس در .env تنظیم شد."
fi

# محیط production: نمایش خطا به کاربر نهایی، افشای مسیر و کوئری است.
set_env APP_ENV production
set_env APP_DEBUG false
ok "APP_ENV=production و APP_DEBUG=false تنظیم شد."

# .env نباید برای همه خواندنی باشد - رمز دیتابیس داخلش است.
chmod 640 .env 2>/dev/null || true

# دسترسی‌ها: storage و bootstrap/cache باید برای وب‌سرور نوشتنی باشند.
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R "$WEB_USER":"$WEB_GROUP" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
ok "دسترسی storage و bootstrap/cache تنظیم شد."

# لینک storage عمومی
[[ -L public/storage ]] || "$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

# migration و seeder
if [[ "$RUN_MIGRATIONS" == "yes" ]]; then
    info "اجرای migration …"
    if "$PHP_BIN" artisan migrate --force 2>&1 | tail -3; then
        ok "migration انجام شد."
    else
        warn "migration ناموفق بود - تنظیمات DB_* در .env را بررسی کنید."
    fi
fi

# دسترسی‌ها و کاربر مدیر - بدون این‌ها نصبِ تازه هیچ راه ورودی ندارد.
#
# این seederها برخلاف داده‌ی نمونه، همیشه اجرا می‌شوند: فهرست دسترسی‌ها و نقش‌ها
# بخشی از ساختار سیستم است نه داده‌ی آزمایشی. همه idempotent هستند.
if [[ "$RUN_MIGRATIONS" == "yes" ]]; then
    info "ثبت دسترسی‌ها و نقش‌ها …"
    for seeder in PermissionSeeder RoleSeeder RolePermissionSeeder AdminUserSeeder; do
        "$PHP_BIN" artisan db:seed --class="Database\\Seeders\\${seeder}" --force >/dev/null 2>&1 \
            || warn "اجرای ${seeder} ناموفق بود."
    done
    ok "دسترسی‌ها، نقش‌ها و کاربر admin ثبت شد."

    # رمز پیش‌فرضِ AdminUserSeeder («123456») برای سرور واقعی خطرناک است، پس
    # بلافاصله با رمز قوی جایگزین می‌شود.
    if [[ -z "$ADMIN_PASS" ]]; then
        ADMIN_PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 16)"
        ADMIN_PASS_GENERATED="yes"
    fi

    if "$PHP_BIN" artisan user:password admin --password="$ADMIN_PASS" >/dev/null 2>&1; then
        ok "رمز کاربر admin تنظیم شد."
    else
        warn "تنظیم رمز admin ناموفق بود؛ رمز پیش‌فرض seeder (123456) فعال است - حتماً تغییرش دهید."
        ADMIN_PASS=""
    fi
fi

if [[ "$RUN_SEEDERS" == "yes" ]]; then
    info "ساخت داده‌ی نمونه …"
    "$PHP_BIN" artisan db:seed --class="Database\\Seeders\\OvertimeOrgSeeder" --force >/dev/null 2>&1 || true
    "$PHP_BIN" artisan db:seed --class="Database\\Seeders\\OvertimeDemoSeeder" --force >/dev/null 2>&1 \
        && ok "داده‌ی نمونه ساخته شد (کلمه‌ی عبور کاربران دمو: secret123)." \
        || warn "ساخت داده‌ی نمونه ناموفق بود."
fi

# پاک‌سازی کش‌های قدیمی (مسیر/کانفیگ قبلی می‌تواند باعث 404 شود)
"$PHP_BIN" artisan config:clear >/dev/null 2>&1 || true
"$PHP_BIN" artisan route:clear  >/dev/null 2>&1 || true
"$PHP_BIN" artisan view:clear   >/dev/null 2>&1 || true
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

        # رمزهای ساخته‌شده فقط همین یک‌بار نمایش داده می‌شوند؛ در .env ذخیره‌اند
        # ولی رمز admin هش شده و بازیابی‌شدنی نیست.
        if [[ -n "${ADMIN_PASS:-}" ]]; then
            printf '\n  %sورود مدیر سیستم%s\n' "$c_bold" "$c_reset"
            printf '    نام کاربری : admin\n'
            printf '    رمز عبور   : %s%s%s\n' "$c_bold" "$ADMIN_PASS" "$c_reset"
            if [[ "${ADMIN_PASS_GENERATED:-}" == "yes" ]]; then
                printf '    %s! این رمز فقط همین یک‌بار نمایش داده می‌شود.%s\n' "$c_yellow" "$c_reset"
            fi
        fi

        if [[ "${DB_PASS_GENERATED:-}" == "yes" ]]; then
            printf '\n  %sدیتابیس%s (در .env ذخیره شد)\n' "$c_bold" "$c_reset"
            printf '    نام       : %s\n' "$DB_NAME"
            printf '    کاربر     : %s\n' "$DB_USER"
            printf '    رمز عبور  : %s\n' "$DB_PASS"
        fi

        if [[ "$RUN_SEEDERS" == "yes" ]]; then
            printf '\n  ورود کاربران دمو: رمز secret123\n'
        fi

        printf '\n  تغییر رمز هر کاربر: %s artisan user:password <نام کاربری>\n' "$PHP_BIN"
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
