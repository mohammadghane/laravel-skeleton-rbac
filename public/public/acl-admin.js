// Admin panel glue: opens the shared modal (#admin-modal) with server-rendered partials,
// wires up delete confirmations, and submits any form loaded into the modal via fetch.
// No Bootstrap JS dependency — modal visibility is plain classList toggling to match
// the Tailwind layout in resources/views/layouts/admin.blade.php.

function openAdminModal() {
    const modal = document.querySelector('#admin-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAdminModal() {
    const modal = document.querySelector('#admin-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.querySelector('.modal-body').innerHTML = '';
}

document.addEventListener('click', async (e) => {
    const openTrigger = e.target.closest('.open-modal');
    if (openTrigger) {
        e.preventDefault();
        const res = await fetch(openTrigger.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await res.text();
        document.querySelector('#admin-modal .modal-body').innerHTML = html;
        openAdminModal();
        return;
    }

    if (e.target.closest('[data-modal-close]') || e.target === document.querySelector('#admin-modal')) {
        closeAdminModal();
        return;
    }

    const removeTrigger = e.target.closest('.btn-remove');
    if (removeTrigger) {
        e.preventDefault();
        if (!confirm('آیا از حذف این مورد اطمینان دارید؟')) return;
        const res = await fetch(removeTrigger.dataset.url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const result = await res.json();
        if (result.success) removeTrigger.closest('tr').remove();
    }
});

// Close modal on Escape.
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAdminModal();
});

// Delegate submit for any form loaded into the modal (role form, user form, permissions form).
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('#role-form, #user-form, #perms-form');
    if (!form) return;
    e.preventDefault();

    const res = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const result = await res.json();

    if (result.success) {
        closeAdminModal();
        const callback = form.dataset.callback;
        if (callback && window[callback]) window[callback](result);
    } else if (result.message) {
        alert(result.message);
    }
});
