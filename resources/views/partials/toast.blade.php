{{--
    Toast notifications. Dispatched via window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }))
    from public/js/acl-admin.js — see toast() there. Replaces the browser's native alert()
    for form/API errors so failures don't block the UI thread or look like a browser chrome popup.
--}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: detail.message, type: detail.type || 'error' });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 6000);
        },
        dismiss(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
    }"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4"
>
    <template x-for="t in toasts" :key="t.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="t.type === 'success' ? 'border-pine/30 bg-pine-100 text-pine' : 'border-brick/30 bg-brick-100 text-brick'"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-2 rounded-xl border px-4 py-3 text-sm shadow-card"
        >
            <span class="flex-1" x-text="t.message"></span>
            <button type="button" @click="dismiss(t.id)" class="shrink-0 opacity-60 hover:opacity-100">✕</button>
        </div>
    </template>
</div>
