// Admin panel glue: opens the shared modal (#admin-modal) with server-rendered partials,
// wires up delete confirmations, and submits any form loaded into the modal (or a full page,
// e.g. settings) via fetch. No Bootstrap JS dependency — modal visibility is plain classList
// toggling to match the Tailwind layout in resources/views/layouts/admin.blade.php.
//
// User-facing strings come from window.i18n (injected by partials/js-i18n.blade.php via
// Laravel's __() helper) with a Persian literal fallback in case that script hasn't loaded.
const i18n = window.i18n || {};
const t = (key, fallback) => i18n[key] || fallback;

function openAdminModal() {
    const modal = document.querySelector('#admin-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAdminModal() {
    const modal = document.querySelector('#admin-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.querySelector('.modal-body').innerHTML = '';
}

// Toast notifications — consumed by the Alpine toast component in layouts/admin.blade.php
// and layouts/guest.blade.php. Replaces the browser's native alert() for form/API errors.
function toast(message, type = 'error') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
}

// Extracts a readable message out of a Laravel JSON error response:
// - validation failures (422): { message, errors: { field: ["msg", ...] } }
// - anything else: { message } or a plain string body
async function readErrorMessage(res) {
    let body;
    try {
        body = await res.json();
    } catch {
        return `${t('serverError', 'خطا در برقراری ارتباط با سرور')} (${res.status})`;
    }

    if (body.errors) {
        return Object.values(body.errors).flat().join(' — ');
    }

    return body.message || `${t('unexpectedError', 'خطای غیرمنتظره')} (${res.status})`;
}

document.addEventListener('click', async (e) => {
    const openTrigger = e.target.closest('.open-modal');
    if (openTrigger) {
        e.preventDefault();
        try {
            const res = await fetch(openTrigger.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) {
                toast(await readErrorMessage(res));
                return;
            }
            document.querySelector('#admin-modal .modal-body').innerHTML = await res.text();
            openAdminModal();
        } catch {
            toast(t('connectionFailed', 'اتصال به سرور برقرار نشد.'));
        }
        return;
    }

    // Modal only closes via the explicit close button (or Escape) — clicking the
    // backdrop no longer closes it, to avoid losing unsaved form input by accident.
    if (e.target.closest('[data-modal-close]')) {
        closeAdminModal();
        return;
    }

    const removeTrigger = e.target.closest('.btn-remove');
    if (removeTrigger) {
        e.preventDefault();
        if (!confirm(t('confirmDelete', 'آیا از حذف این مورد اطمینان دارید؟'))) return;
        try {
            const res = await fetch(removeTrigger.dataset.url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) {
                toast(await readErrorMessage(res));
                return;
            }
            const result = await res.json();
            if (result.success) removeTrigger.closest('tr').remove();
        } catch {
            toast(t('connectionFailed', 'اتصال به سرور برقرار نشد.'));
        }
    }
});

// Close modal on Escape.
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAdminModal();
});

// Delegate submit for any form loaded into the modal, or a full page (e.g. settings).
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('#role-form, #user-form, #perms-form, #settings-form');
    if (!form) return;
    e.preventDefault();

    try {
        const res = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!res.ok) {
            toast(await readErrorMessage(res));
            return;
        }

        const result = await res.json();
        if (result.success) {
            closeAdminModal();
            if (result.message) toast(result.message, 'success');
            const callback = form.dataset.callback;
            if (callback && window[callback]) window[callback](result);
        } else {
            toast(result.message || t('submitFailed', 'ثبت اطلاعات با خطا مواجه شد.'));
        }
    } catch {
        toast(t('connectionFailed', 'اتصال به سرور برقرار نشد.'));
    }
});
