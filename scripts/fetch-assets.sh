#!/usr/bin/env bash
#
# دانلود دوباره‌ی فونت‌های محلی از Google Fonts.
#
# این اسکریپت فقط برای **به‌روزرسانی** فونت‌هاست و روی سرور اجرا نمی‌شود؛ خروجی‌اش
# (public/vendor/fonts + public/vendor/css/fonts.css) در مخزن کامیت است، چون پروژه
# نباید هنگام اجرا هیچ فایلی از اینترنت بگیرد.
#
# پس از اجرا حتماً بسته را دوباره بسازید:
#   cp public/vendor/css/fonts.css resources/css/fonts.css && npm run build
#
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

FONT_CSS_URL='https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap'

# بدون User-Agent مدرن، گوگل به‌جای woff2 فرمت قدیمی truetype می‌دهد (حجم چند برابر).
UA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'

mkdir -p public/vendor/fonts public/vendor/css
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

echo "▸ دریافت CSS فونت‌ها …"
curl -fsSL -A "$UA" "$FONT_CSS_URL" -o "$tmp"

echo "▸ دانلود فایل‌های فونت …"
python3 - "$tmp" <<'PY'
import re, sys, hashlib, os, urllib.request

css = open(sys.argv[1], encoding='utf-8').read()
ua = {'User-Agent': 'Mozilla/5.0'}
seen = {}

def repl(m):
    url = m.group(1)
    fam = re.search(r'/s/([^/]+)/', url).group(1)
    h = hashlib.md5(os.path.basename(url).encode()).hexdigest()[:8]
    dest = f"public/vendor/fonts/{fam}-{h}.woff2"
    if url not in seen:
        req = urllib.request.Request(url, headers=ua)
        with urllib.request.urlopen(req) as r, open(dest, 'wb') as f:
            f.write(r.read())
        seen[url] = dest
    return f"url(/vendor/fonts/{fam}-{h}.woff2)"

out, n = re.subn(r'url\((https://fonts\.gstatic\.com/[^)]+)\)', repl, css)

assert 'gstatic' not in out and 'googleapis' not in out.split('*/')[-1], "آدرس خارجی باقی مانده"

header = """/*
 * فونت‌های محلی - جایگزین fonts.googleapis.com
 *
 * ساخته‌شده توسط scripts/fetch-assets.sh؛ دستی ویرایش نکنید.
 * پروژه هیچ فایلی از اینترنت نمی‌گیرد.
 */
"""
open('public/vendor/css/fonts.css', 'w', encoding='utf-8').write(header + out)
print(f"  {len(seen)} فایل فونت، {n} ارجاع بازنویسی شد")
PY

cp public/vendor/css/fonts.css resources/css/fonts.css
echo "✔ انجام شد. حالا اجرا کنید:  npm run build"
