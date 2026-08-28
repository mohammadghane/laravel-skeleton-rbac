<?php

namespace App\Console\Commands;

use App\Services\PersonnelSyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * همگام‌سازی پرسنل، انواع قرارداد و سمت‌ها از وب‌سرویس پرسنلی.
 *
 *   php artisan overtime:sync-personnel
 *   php artisan overtime:sync-personnel --url=http://example/api/getpersonels.json
 *   php artisan overtime:sync-personnel --file=storage/app/personnel.json   (آفلاین)
 */
class SyncPersonnel extends Command
{
    protected $signature = 'overtime:sync-personnel
                            {--url= : آدرس وب‌سرویس (پیش‌فرض از config/services.php)}
                            {--file= : خواندن از فایل JSON محلی به‌جای وب‌سرویس}';

    protected $description = 'همگام‌سازی پرسنل، انواع قرارداد و سمت‌های سازمانی از وب‌سرویس پرسنلی';

    public function handle(PersonnelSyncService $service): int
    {
        $this->info('شروع همگام‌سازی…');

        try {
            if ($file = $this->option('file')) {
                $path = str_starts_with($file, '/') ? $file : base_path($file);

                if (!is_file($path)) {
                    $this->error("فایل یافت نشد: {$path}");

                    return self::FAILURE;
                }

                $records = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $result = $service->sync(is_array($records) ? $records : []);
            } else {
                $result = $service->syncFromUrl($this->option('url'));
            }
        } catch (Throwable $e) {
            $this->error('همگام‌سازی ناموفق بود: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("رکوردهای دریافتی: <fg=cyan>{$result['fetched']}</>");

        $this->table(
            ['مورد', 'ایجاد', 'به‌روزرسانی'],
            [
                ['انواع قرارداد', $result['stats']['contract_types_created'], $result['stats']['contract_types_updated']],
                ['سمت‌های سازمانی', $result['stats']['positions_created'], $result['stats']['positions_updated']],
                ['پرسنل', $result['stats']['personnel_created'], $result['stats']['personnel_updated']],
            ]
        );

        if ($result['stats']['skipped'] > 0) {
            $this->warn("رکوردهای رد شده: {$result['stats']['skipped']}");

            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line("  - {$error}");
            }

            if (count($result['errors']) > 20) {
                $this->line('  … و ' . (count($result['errors']) - 20) . ' مورد دیگر');
            }
        }

        $this->newLine();
        $this->info('همگام‌سازی به پایان رسید.');

        return self::SUCCESS;
    }
}
