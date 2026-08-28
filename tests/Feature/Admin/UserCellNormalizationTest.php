<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\UserRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * تست نرمال‌سازی شماره موبایل.
 *
 * فرمت استاندارد ذخیره‌سازی: ۱۱ رقم با صفر ابتدایی (09121111111). کاربر می‌تواند شماره را
 * با پیش‌شماره‌ی +98/98، بدون صفر، با خط تیره یا با ارقام فارسی وارد کند و همه باید به
 * یک شکل یکسان تبدیل شوند - وگرنه بررسی یکتا بودن (unique) هم دور زده می‌شد.
 */
class UserCellNormalizationTest extends TestCase
{
    public static function cellProvider(): array
    {
        return [
            'با صفر (استاندارد)' => ['09121234567', '09121234567'],
            'بدون صفر' => ['9121234567', '09121234567'],
            'با پیش‌شماره +98' => ['+989121234567', '09121234567'],
            'با پیش‌شماره 98' => ['989121234567', '09121234567'],
            'با خط تیره' => ['0912-123-4567', '09121234567'],
            'با فاصله' => ['0912 123 4567', '09121234567'],
            'ارقام فارسی' => ['۰۹۱۲۱۲۳۴۵۶۷', '09121234567'],
            'ارقام عربی' => ['٠٩١٢١٢٣٤٥٦٧', '09121234567'],
        ];
    }

    #[DataProvider('cellProvider')]
    public function test_it_normalizes_cell_numbers_to_the_canonical_format(string $input, string $expected): void
    {
        $this->assertSame($expected, UserRequest::normalizeCell($input));
    }

    public function test_it_leaves_clearly_invalid_input_for_the_validator_to_reject(): void
    {
        // نرمال‌سازی نباید ورودی نامعتبر را «درست» کند؛ فقط ارقام را استخراج می‌کند و
        // تصمیم رد/قبول با قانون regex در rules() است.
        $this->assertSame('0812345678', UserRequest::normalizeCell('0812345678'));
        $this->assertSame('', UserRequest::normalizeCell('abc'));
    }
}
