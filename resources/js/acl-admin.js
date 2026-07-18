// Minimal replacement for the old CI admin JS (ajax-loader modal opener, btn-remove,
// reloadDataGrid). Adjust selectors/markup to whatever modal component your Laravel
// front-end uses (Bootstrap modal shown here).
document.addEventListener('click', async (e) => {
    const openTrigger = e.target.closest('.open-modal');
    if (openTrigger) {
        e.preventDefault();
        const res = await fetch(openTrigger.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await res.text();
        document.querySelector('#admin-modal .modal-body').innerHTML = html;
        new bootstrap.Modal(document.querySelector('#admin-modal')).show();
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
        bootstrap.Modal.getInstance(document.querySelector('#admin-modal'))?.hide();
        const callback = form.dataset.callback;
        if (callback && window[callback]) window[callback](result);
    } else if (result.message) {
        alert(result.message);
    }
});
