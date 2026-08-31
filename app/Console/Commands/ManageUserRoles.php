<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * مدیریت نقش‌های یک کاربر از خط فرمان.
 *
 *   php artisan user:roles admin                    # نمایش نقش‌ها و دسترسی‌های کاربر
 *   php artisan user:roles admin --add=system-manager
 *   php artisan user:roles admin --remove=user
 *   php artisan user:roles admin --set=admin,editor  # جایگزینی کامل
 *   php artisan user:roles --list-roles              # فهرست نقش‌های موجود
 *
 * انگیزه‌ی وجود این دستور: اگر کاربری دسترسی‌اش را از دست بدهد (یا آخرین مدیر
 * سیستم از پنل حذف شود)، بدون راه CLI هیچ راهی برای بازگرداندن آن نیست - چون
 * خودِ پنلِ مدیریت نقش‌ها هم پشت همان دسترسی قفل است.
 */
class ManageUserRoles extends Command
{
    protected $signature = 'user:roles
                            {user? : نام کاربری، ایمیل یا کد پرسنلی}
                            {--add= : افزودن نقش (با کاما جدا کنید)}
                            {--remove= : حذف نقش (با کاما جدا کنید)}
                            {--set= : جایگزینی کامل نقش‌ها (با کاما جدا کنید)}
                            {--list-roles : نمایش فهرست نقش‌های تعریف‌شده}';

    protected $description = 'نمایش و تغییر نقش‌های یک کاربر (افزودن، حذف یا جایگزینی)';

    public function handle(): int
    {
        if ($this->option('list-roles')) {
            return $this->listRoles();
        }

        $identifier = $this->argument('user') ?: $this->ask('نام کاربری، ایمیل یا کد پرسنلی');

        $user = $this->findUser((string) $identifier);

        if (!$user) {
            return self::FAILURE;
        }

        $changed = false;

        if ($names = $this->option('set')) {
            $ids = $this->resolveRoles($names);
            if ($ids === null) {
                return self::FAILURE;
            }
            $user->roles()->sync($ids);
            $changed = true;
        }

        if ($names = $this->option('add')) {
            $ids = $this->resolveRoles($names);
            if ($ids === null) {
                return self::FAILURE;
            }
            $user->roles()->syncWithoutDetaching($ids);
            $changed = true;
        }

        if ($names = $this->option('remove')) {
            $ids = $this->resolveRoles($names);
            if ($ids === null) {
                return self::FAILURE;
            }
            $user->roles()->detach($ids);
            $changed = true;
        }

        if ($changed) {
            // بدون پاک‌کردن کش، دسترسی‌های جدید تا انقضای کش اعمال نمی‌شوند.
            User::forgetPermissionCache($user->id);
            $this->info('نقش‌ها به‌روزرسانی شد.');
        }

        $this->showUser($user->fresh()->load('roles.permissions'));

        return self::SUCCESS;
    }

    private function findUser(string $identifier): ?User
    {
        $matches = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('personnel_code', $identifier)
            ->get();

        if ($matches->isEmpty()) {
            $this->error("کاربری با شناسه‌ی «{$identifier}» یافت نشد.");

            return null;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // چند کاربر با یک شناسه: صریح می‌پرسیم تا نقشِ کاربر اشتباهی عوض نشود.
        $choices = $matches->mapWithKeys(fn (User $u) => [
            $u->id => "#{$u->id} — {$u->username} — " . trim($u->name . ' ' . $u->family),
        ])->all();

        $chosen = $this->choice('کدام کاربر؟', $choices);

        return $matches->first(fn (User $u) => $choices[$u->id] === $chosen);
    }

    /** نام نقش‌ها را به شناسه تبدیل می‌کند؛ در صورت نبودِ نقش، null برمی‌گرداند. */
    private function resolveRoles(string $names): ?array
    {
        $wanted = collect(explode(',', $names))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->values();

        $roles = Role::whereIn('name', $wanted)->get();
        $missing = $wanted->diff($roles->pluck('name'));

        if ($missing->isNotEmpty()) {
            $this->error('این نقش‌ها وجود ندارند: ' . $missing->implode('، '));
            $this->line('برای دیدن فهرست نقش‌ها:  <fg=cyan>php artisan user:roles --list-roles</>');

            return null;
        }

        return $roles->pluck('id')->all();
    }

    private function showUser(User $user): void
    {
        $this->newLine();
        $this->line("کاربر : <fg=cyan>{$user->username}</> (" . trim($user->name . ' ' . $user->family) . ')');

        if ($user->roles->isEmpty()) {
            $this->warn('این کاربر هیچ نقشی ندارد و به هیچ منویی دسترسی نخواهد داشت.');

            return;
        }

        $this->line('نقش‌ها : ' . $user->roles->pluck('name')->implode('، '));

        $permissions = $user->roles
            ->flatMap(fn (Role $r) => $r->permissions->pluck('key'))
            ->unique()->sort()->values();

        if ($permissions->contains('system-manager')) {
            $this->info('این کاربر دسترسی system-manager دارد (همه‌ی دسترسی‌ها).');

            return;
        }

        $this->line('دسترسی‌ها (' . $permissions->count() . '):');
        foreach ($permissions->chunk(3) as $chunk) {
            $this->line('  ' . $chunk->implode('  '));
        }
    }

    private function listRoles(): int
    {
        $roles = Role::withCount('users')->with('permissions')->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->warn('هیچ نقشی تعریف نشده است.');

            return self::SUCCESS;
        }

        $this->table(
            ['نقش', 'تعداد کاربر', 'تعداد دسترسی'],
            $roles->map(fn (Role $r) => [$r->name, $r->users_count, $r->permissions->count()])->all()
        );

        return self::SUCCESS;
    }
}
