<?php

namespace App\Http\Middleware;

use App\Support\PersianText;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * یکسان‌سازی ورودی همه‌ی درخواست‌ها پیش از رسیدن به کنترلر و اعتبارسنجی:
 * «ي/ك» عربی به فارسی و ارقام فارسی/عربی به لاتین.
 *
 * چرا در میدل‌ور و نه در تک‌تک فرم‌ها؟ چون داده از مسیرهای متعددی وارد می‌شود (فرم‌های
 * پنل، جست‌وجو، ثبت اضافه‌کار، تعریف سقف) و اگر هرکدام جداگانه این کار را انجام دهند،
 * دیر یا زود یکی فراموش می‌شود و همان‌جا رکورد ناسازگار ساخته می‌شود.
 *
 * نکته‌ی مهم درباره‌ی ارقام: چون همه‌ی ورودی‌های عددی سیستم (ساعت، سقف، کد پرسنلی، سال و
 * ماه شمسی) در نهایت باید عدد لاتین باشند، تبدیل ارقام روی تمام فیلدها اعمال می‌شود.
 * این باعث می‌شود کاربر بتواند «۱۴۰» را با کیبورد فارسی تایپ کند و درست ذخیره شود.
 */
class NormalizePersianInput
{
    /**
     * فیلدهایی که نباید دست بخورند.
     *
     * رمز عبور: کاربر ممکن است عمداً کاراکتر عربی یا رقم فارسی در رمزش داشته باشد؛
     * تغییر آن یعنی رمز ذخیره‌شده با آنچه تایپ می‌کند فرق کند و دیگر نتواند وارد شود.
     * توکن‌ها هم مقادیر تولیدشده‌ی سیستم‌اند و نباید تغییر کنند.
     */
    private const EXCEPT = [
        'password',
        'password_confirmation',
        'current_password',
        '_token',
        'api_token',
        'remember_token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        if ($input !== []) {
            $request->merge($this->normalizeArray($input));
        }

        return $next($request);
    }

    /** پیمایش بازگشتی روی آرایه‌ی ورودی (فرم‌های تودرتو مثل roles[] یا filters[]). */
    private function normalizeArray(array $input): array
    {
        foreach ($input as $key => $value) {
            if (in_array($key, self::EXCEPT, true)) {
                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->normalizeArray($value);
            } elseif (is_string($value)) {
                // collapseSpaces=false تا فاصله‌های داخل متن (مثل آدرس چندخطی) دست‌نخورده
                // بماند؛ فقط حروف، ارقام و فاصله‌های غیرمعمول یکسان می‌شوند.
                $input[$key] = PersianText::normalize($value, collapseSpaces: false);
            }
        }

        return $input;
    }
}
