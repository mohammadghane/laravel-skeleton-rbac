/** @type {import('tailwindcss').Config} */
export default {
    // مسیرهایی که کلاس‌های Tailwind در آن‌ها استفاده می‌شوند. هر کلاسی که اینجا
    // دیده نشود در خروجی نهایی نمی‌آید، پس اگر صفحه‌ای بی‌استایل شد اول این فهرست
    // را بررسی کنید.
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './public/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Vazirmatn هم فارسی و هم لاتین را پوشش می‌دهد، پس با تغییر زبان
                // نیازی به عوض‌کردن font-family نیست؛ Inter فقط fallback است.
                sans: ['Vazirmatn', 'Inter', 'sans-serif'],
                mono: ['JetBrains Mono', 'monospace'],
            },
            colors: {
                ink: { DEFAULT: '#1C2333', soft: '#2A3244' },
                surface: '#F5F6F3',
                pine: { DEFAULT: '#3E6259', 600: '#345049', 100: '#E4ECE9' },
                gold: { DEFAULT: '#C9A227', 100: '#FBF3D9' },
                brick: { DEFAULT: '#B4432F', 100: '#F7E4E0' },
                line: '#E3E5E0',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(28 35 51 / 0.04), 0 1px 3px 0 rgb(28 35 51 / 0.06)',
            },
        },
    },
    plugins: [],
};
