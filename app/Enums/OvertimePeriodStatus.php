<?php

namespace App\Enums;

/**
 * State machine گردش کار اضافه‌کار (شامل آیتم‌های ویژه‌ای که نیازمند تایید معاونت هستند).
 *
 *   Draft -> ManagerSubmitted -> (قفل برای مدیر واحد)
 *   -> DeputyApproved | DeputyRejected
 *        DeputyRejected -> برمی‌گردد به ManagerSubmitted (قابل ویرایش مجدد توسط مدیر واحد)
 *        DeputyApproved -> می‌رود نزد مدیرعامل
 *   -> CeoApproved | CeoRejected
 *        CeoRejected -> مستقیم برمی‌گردد به DeputyApproved (نه نزد مدیر واحد)
 *        CeoApproved -> Finalized (قفل کامل، آماده‌ی خروجی اکسل)
 */
enum OvertimePeriodStatus: string
{
    case Draft = 'draft';
    case ManagerSubmitted = 'manager_submitted';
    case DeputyApproved = 'deputy_approved';
    case DeputyRejected = 'deputy_rejected';
    case CeoApproved = 'ceo_approved';
    case CeoRejected = 'ceo_rejected';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('پیش‌نویس'),
            self::ManagerSubmitted => __('ثبت‌شده توسط مدیر'),
            self::DeputyApproved => __('تایید معاونت'),
            self::DeputyRejected => __('رد شده توسط معاونت'),
            self::CeoApproved => __('تایید مدیرعامل'),
            self::CeoRejected => __('رد شده توسط مدیرعامل'),
            self::Finalized => __('نهایی‌شده'),
        };
    }

    /** آیا مدیر واحد در این وضعیت اجازه‌ی ویرایش اضافه‌کار عادی را دارد؟ */
    public function editableByManager(): bool
    {
        return in_array($this, [self::Draft, self::DeputyRejected], true);
    }

    /** آیا معاونت در این وضعیت اجازه‌ی بررسی/ثبت اضافه‌کار مازاد را دارد؟ */
    public function editableByDeputy(): bool
    {
        return in_array($this, [self::ManagerSubmitted, self::CeoRejected], true);
    }

    /** آیا مدیرعامل در این وضعیت اجازه‌ی تایید/رد نهایی را دارد؟ */
    public function editableByCeo(): bool
    {
        return $this === self::DeputyApproved;
    }
}
