<?php

namespace App\Exports;

use App\Enums\OvertimePeriodStatus;
use App\Models\OvertimePeriod;
use App\Models\SpecialItem;
use App\Support\Jalali;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * خروجی اکسل ماهانه‌ی اضافه‌کار برای سیستم مالی (راهکاران/همکاران سیستم) - سند.
 * این یک نمونه‌ی اولیه است؛ ستون‌ها و نام‌گذاری دقیق باید بعداً با فرمت واقعی سیستم مالی
 * تطبیق داده شود (طبق تصمیم پروژه: فعلاً یک ساختار پیش‌فرض).
 *
 * پیش‌نیاز: composer require maatwebsite/excel
 * استفاده: Excel::download(new OvertimeMonthlyExport($year, $month), "overtime-$year-$month.xlsx");
 *
 * پیش‌فرض فقط دوره‌های Finalized را خروجی می‌گیرد (چون این فایل برای فیش حقوقی است و باید
 * فقط بر مبنای اعداد تایید نهاییِ مدیرعامل باشد)؛ با $onlyFinalized=false همه را می‌آورد
 * (برای بازبینی پیشِ‌نویس توسط HR، نه برای ارسال نهایی).
 */
class OvertimeMonthlyExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    private Collection $specialItems;

    public function __construct(
        private readonly int $jalaliYear,
        private readonly int $jalaliMonth,
        private readonly bool $onlyFinalized = true,
    ) {
        $this->specialItems = SpecialItem::where('is_active', true)->orderBy('title')->get();
    }

    public function collection(): Collection
    {
        return OvertimePeriod::with([
            'personnel', 'personnel.eligibleSpecialItems', 'unitSnap', 'deputySnap', 'positionSnap', 'contractTypeSnap', 'specialItemLines',
        ])
            ->where('jalali_year', $this->jalaliYear)
            ->where('jalali_month', $this->jalaliMonth)
            ->when($this->onlyFinalized, fn ($q) => $q->where('status', OvertimePeriodStatus::Finalized->value))
            ->orderBy('deputy_id_snap')->orderBy('unit_id_snap')
            ->get();
    }

    public function headings(): array
    {
        $fixed = [
            'کد پرسنلی', 'نام و نام خانوادگی', 'کد ملی', 'معاونت', 'واحد', 'نوع قرارداد', 'سمت سازمانی',
            'دوره (ماه شمسی)', 'تعداد روز کارکرد ماه', 'ضریب ریالی', 'کسر کار (ساعت)', 'ماموریت (ساعت)',
            'اضافه‌کار عادی (ساعت)', 'اضافه‌کار مازاد (ساعت)',
        ];

        $hourHeadings = $this->specialItems->where('unit', 'hour')->pluck('title')->map(fn ($t) => $t . ' (ساعت)')->all();
        $dayHeadings = $this->specialItems->where('unit', 'day')->pluck('title')->map(fn ($t) => $t . ' (روز)')->all();

        // «جمع خام» و «سقف کلی» و «مازاد بر سقف» فقط جهت شفافیت و ردیابی می‌آیند؛ ستونی که
        // سیستم مالی باید مبنای فیش حقوقی قرار دهد «ساعت قابل پرداخت» است.
        return array_merge($fixed, $hourHeadings, $dayHeadings, [
            'جمع کل ساعت اضافه‌کار (خام)',
            'سقف کلی قرارداد',
            'مازاد بر سقف (کسرشده)',
            'ساعت قابل پرداخت',
            'وضعیت',
        ]);
    }

    public function map($period): array
    {
        /** @var OvertimePeriod $period */
        $fixed = [
            $period->personnel?->username ?? $period->personnel?->id,
            trim(($period->personnel?->name ?? '') . ' ' . ($period->personnel?->family ?? '')),
            $period->personnel?->national_code,
            $period->deputy_title_snap,
            $period->unit_title_snap,
            $period->contract_type_title_snap,
            $period->position_title_snap,
            Jalali::periodLabel($period->jalali_year, $period->jalali_month),
            $period->worked_days_count,
            $period->rial_coefficient,
            $period->deduction_hours,
            $period->mission_hours,
            (string) $period->normal_hours,
            (string) $period->excess_hours,
        ];

        $specialItemValues = $this->specialItems->where('unit', 'hour')->map(function (SpecialItem $item) use ($period) {
            return (string) ($period->personnel?->eligibleSpecialItems->firstWhere('id', $item->id)?->default_value ?? '0');
        })->all();
        $specialItemDayValues = $this->specialItems->where('unit', 'day')->map(function (SpecialItem $item) use ($period) {
            $eligibleItem = $period->personnel?->eligibleSpecialItems->firstWhere('id', $item->id);

            return $eligibleItem ? (string) $eligibleItem->default_value : null;
        })->all();

        return array_merge($fixed, $specialItemValues, $specialItemDayValues, [
            $period->totalHours(),
            $period->overallCeiling() ?? '-',
            $period->cappedHours(),
            $period->payableHours(),
            $period->status->label(),
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'name' => 'Arial']]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $headings = $this->headings();

                foreach ($headings as $index => $heading) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    // محاسبه‌ی خودکار PhpSpreadsheet برای حروف فارسی عرض را کم برآورد
                    // می‌کند؛ عرض را از طول عنوان می‌گیریم تا متن سرستون بریده نشود.
                    $width = min(42, max(14, mb_strlen((string) $heading) * 1.15 + 3));
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
                $sheet->getStyle('A1:' . $lastColumn . '1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true)
                    ->setReadOrder(Alignment::READORDER_RTL);
                $sheet->getRowDimension(1)->setRowHeight(34);
            },
        ];
    }
}
