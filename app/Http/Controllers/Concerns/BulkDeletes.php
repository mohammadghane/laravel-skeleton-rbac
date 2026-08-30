<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * حذف گروهی رکوردها با رعایت همان قواعد حفاظتیِ حذف تکی.
 *
 * نکته‌ی محوری: حذف گروهی **نباید** میان‌بری برای دور زدن محدودیت‌ها باشد. مثلاً حذف
 * واحدی که پرسنل دارد، آن پرسنل را بی‌واحد (و در نتیجه بدون مدیر و بدون امکان ثبت
 * اضافه‌کار) رها می‌کند. بنابراین برای هر رکورد همان شرطِ حذف تکی بررسی می‌شود و
 * موارد غیرقابل‌حذف با ذکر دلیل گزارش می‌گردند - نه اینکه کل عملیات رد شود.
 *
 * قرارداد استفاده: کنترلر متد bulkDestroy() را تعریف می‌کند و این متد را با یک closure
 * صدا می‌زند که در صورت غیرمجاز بودنِ حذف، پیام دلیل را برمی‌گرداند (و در غیر این صورت null).
 */
trait BulkDeletes
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(Model): ?string  $guard  پیام دلیلِ عدم حذف، یا null اگر حذف مجاز است
     */
    protected function bulkDelete(Request $request, string $modelClass, callable $guard, string $label): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $records = $modelClass::whereIn('id', $validated['ids'])->get();

        // شناسه‌ی رکوردهای حذف‌شده هم برمی‌گردد تا سمت کلاینت بتواند دقیقاً همان
        // سطرها را از جدول بردارد. بدون این، وقتی عملیات جزئی موفق باشد (بخشی حذف
        // شود و بخشی رد)، سطرهای حذف‌شده تا رفرش دستی روی صفحه باقی می‌ماندند.
        $deletedIds = [];
        $blocked = [];

        try {
            DB::transaction(function () use ($records, $guard, &$deletedIds, &$blocked) {
                foreach ($records as $record) {
                    $reason = $guard($record);

                    if ($reason !== null) {
                        $blocked[] = ($record->title ?? $record->name ?? $record->id) . ' — ' . $reason;
                        continue;
                    }

                    $record->delete();
                    $deletedIds[] = $record->getKey();
                }
            });
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $deleted = count($deletedIds);

        return response()->json([
            'success' => true,
            'message' => $this->bulkMessage($deleted, $blocked, $label),
            'deleted' => $deleted,
            'deleted_ids' => $deletedIds,
            'blocked' => $blocked,
        ]);
    }

    private function bulkMessage(int $deleted, array $blocked, string $label): string
    {
        if ($deleted === 0) {
            return __('هیچ موردی حذف نشد.');
        }

        $message = __(':count :label حذف شد.', ['count' => $deleted, 'label' => $label]);

        if ($blocked !== []) {
            $message .= ' ' . __(':count مورد قابل حذف نبود.', ['count' => count($blocked)]);
        }

        return $message;
    }
}
