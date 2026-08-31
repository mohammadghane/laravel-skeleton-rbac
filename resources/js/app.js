/**
 * نقطه‌ی ورود جاوااسکریپت پنل.
 *
 * Alpine اینجا bundle می‌شود نه از CDN: پروژه نباید هیچ فایلی از اینترنت بگیرد.
 * نسخه در package.json قفل است (alpinejs@3.14.1) - همان نسخه‌ای که پیش‌تر از
 * jsdelivr گرفته می‌شد.
 *
 * نکته‌ی مهم: کامپوننت‌های x-data (bulkDelete، rolePicker، overtimeEntry و …) در
 * خودِ Blade تعریف می‌شوند و باید پیش از Alpine.start() روی window موجود باشند.
 * به همین دلیل این فایل با @vite در <head> و با defer بارگذاری می‌شود، پس اسکریپت‌های
 * inline صفحه زودتر اجرا شده‌اند.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
