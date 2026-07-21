<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * پرتاب می‌شود وقتی ثبت/ویرایش یک اضافه‌کار باعث عبور مجموع یک محدوده (واحد یا معاونت)
 * از سقف مجاز آن نوع قرارداد/سمت در آن ماه شمسی شود. کنترلر این را می‌گیرد و به صورت
 * پاسخ ۴۲۲ با پیام مناسب به کاربر برمی‌گرداند (نگاه کنید به الگوی toast در acl-admin.js).
 */
class OvertimeCeilingExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $scopeLabel,
        public readonly string $ceiling,
        public readonly string $used,
        public readonly string $attempted,
    ) {
        parent::__construct($message);
    }
}
