<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * تغییر رمز عبور یک کاربر از خط فرمان.
 *
 *   php artisan user:password admin                       # رمز را می‌پرسد (مخفی)
 *   php artisan user:password admin --password=secret123   # بدون پرسش
 *   php artisan user:password 52155 --random               # رمز تصادفی می‌سازد و نمایش می‌دهد
 *   php artisan user:password --list                       # فهرست کاربران قابل ورود
 *
 * شناسه‌ی کاربر می‌تواند نام کاربری، ایمیل یا کد پرسنلی باشد.
 */
class ResetUserPassword extends Command
{
    protected $signature = 'user:password
                            {user? : نام کاربری، ایمیل یا کد پرسنلی}
                            {--password= : رمز جدید (اگر داده نشود پرسیده می‌شود)}
                            {--random : ساخت رمز تصادفی و نمایش آن}
                            {--list : نمایش فهرست کاربران به‌جای تغییر رمز}';

    protected $description = 'تغییر رمز عبور یک کاربر (ورود از طریق نام کاربری، ایمیل یا کد پرسنلی)';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listUsers();
        }

        $identifier = $this->argument('user') ?: $this->ask('نام کاربری، ایمیل یا کد پرسنلی');

        if (!$identifier) {
            $this->error('شناسه‌ی کاربر وارد نشد.');

            return self::FAILURE;
        }

        $matches = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('personnel_code', $identifier)
            ->get();

        if ($matches->isEmpty()) {
            $this->error("کاربری با شناسه‌ی «{$identifier}» یافت نشد.");
            $this->line('برای دیدن فهرست کاربران:  <fg=cyan>php artisan user:password --list</>');

            return self::FAILURE;
        }

        // اگر یک شناسه به چند کاربر بخورد، صریح می‌پرسیم تا رمز کاربر اشتباهی عوض نشود.
        if ($matches->count() > 1) {
            $this->warn("چند کاربر با این شناسه یافت شد:");

            $choices = $matches->mapWithKeys(fn (User $u) => [
                $u->id => "#{$u->id} — {$u->username} — " . trim($u->name . ' ' . $u->family),
            ])->all();

            foreach ($choices as $id => $label) {
                $this->line("  {$label}");
            }

            $chosen = $this->choice('کدام کاربر؟', $choices);
            $user = $matches->first(fn (User $u) => $choices[$u->id] === $chosen);
        } else {
            $user = $matches->first();
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        // کش دسترسی‌ها ربطی به رمز ندارد، ولی پاک‌کردنش هزینه‌ای ندارد و از سردرگمی
        // پس از تغییرات نقش جلوگیری می‌کند.
        User::forgetPermissionCache($user->id);

        $this->newLine();
        $this->info('رمز عبور با موفقیت تغییر کرد.');
        $this->line("  کاربر : <fg=cyan>{$user->username}</> (" . trim($user->name . ' ' . $user->family) . ')');

        if ($this->option('random')) {
            $this->line("  رمز   : <fg=yellow>{$password}</>");
            $this->warn('این رمز فقط همین یک‌بار نمایش داده می‌شود.');
        }

        if ($user->trashed()) {
            $this->warn('توجه: این کاربر حذف (soft delete) شده است و نمی‌تواند وارد شود.');
        }

        return self::SUCCESS;
    }

    /** رمز را از گزینه‌ها یا پرسش تعاملی می‌گیرد. */
    private function resolvePassword(): ?string
    {
        if ($this->option('random')) {
            return Str::password(12, symbols: false);
        }

        if ($password = $this->option('password')) {
            return $this->validated((string) $password);
        }

        $password = $this->secret('رمز عبور جدید');
        $confirm = $this->secret('تکرار رمز عبور');

        if ($password !== $confirm) {
            $this->error('دو رمز وارد شده یکسان نیستند.');

            return null;
        }

        return $this->validated((string) $password);
    }

    /** حداقل طول رمز، هم‌تراز با قانون UserRequest (min:8). */
    private function validated(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            $this->error('رمز عبور باید دست‌کم ۸ کاراکتر باشد.');

            return null;
        }

        return $password;
    }

    private function listUsers(): int
    {
        $users = User::query()
            ->orderBy('id')
            ->get(['id', 'username', 'name', 'family', 'email', 'personnel_code']);

        if ($users->isEmpty()) {
            $this->warn('هیچ کاربری در سیستم وجود ندارد.');

            return self::SUCCESS;
        }

        // فهرست کامل پرسنل می‌تواند صدها ردیف باشد؛ فقط کاربرانی که «حساب ورود» دارند
        // (نام کاربری غیرعددی یا نقش دارند) معمولاً موردنیاز مدیر سیستم‌اند.
        $withRoles = User::has('roles')->pluck('id')->all();

        $rows = $users
            ->filter(fn (User $u) => in_array($u->id, $withRoles, true))
            ->map(fn (User $u) => [
                $u->id,
                $u->username,
                trim($u->name . ' ' . $u->family),
                $u->email,
                $u->personnel_code ?? '—',
            ])
            ->all();

        $this->table(['#', 'نام کاربری', 'نام', 'ایمیل', 'کد پرسنلی'], $rows);
        $this->line('کاربران بدون نقش نمایش داده نشدند (' . ($users->count() - count($rows)) . ' نفر).');

        return self::SUCCESS;
    }
}
