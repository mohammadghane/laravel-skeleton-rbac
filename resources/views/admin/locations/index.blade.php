@extends('layouts.admin')

@section('title', __('موقعیت جغرافیایی'))

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('موقعیت‌های جغرافیایی'),
        'subtitle' => __('ثبت نقاط مشخص روی نقشه و مدیریت آن‌ها.'),
        'action' => new \Illuminate\Support\HtmlString(
            '<a href="' . route('admin.locations.create') . '" class="open-modal inline-flex items-center gap-2 rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white transition hover:bg-pine-600">+ ' . __('موقعیت جدید') . '</a>'
        ),
    ])

    <div class="mb-4">
        @include('admin.overtime.partials.search-box', [
            'route' => 'admin.locations.index',
            'placeholder' => __('عنوان یا توضیحات…'),
        ])
    </div>

    <div x-data="bulkDelete(
        '{{ route('admin.locations.bulk-destroy') }}',
        '{{ __('حذف :count موقعیت انتخاب‌شده؟') }}'
    )">
        @include('admin.overtime.partials.bulk-delete', ['label' => __('موقعیت')])

        {{-- نقشه‌ی مرور: همه‌ی موقعیت‌های ثبت‌شده یک‌جا. --}}
        @if ($locations->isNotEmpty())
            <div
                class="mb-4 overflow-hidden rounded-2xl border border-line bg-white shadow-card"
                x-data="locationsOverview(@js($locations->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'lat' => (float) $l->latitude,
                    'lng' => (float) $l->longitude,
                ])->values()))"
            >
                <div x-ref="map" class="h-80 w-full" style="background: #eef0ec;"></div>
            </div>
        @endif

        <div id="locations-table" class="rounded-2xl border border-line bg-white shadow-card">
            @include('admin.locations.partials.table', ['locations' => $locations])
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function reloadLocationGrid() { window.location.reload(); }

    /** نقشه‌ی فقط‌خواندنیِ فهرست: همه‌ی نقاط با نشانگر و عنوان. */
    function locationsOverview(points) {
        return {
            init() {
                if (!window.L || !points.length) return;

                const map = L.map(this.$refs.map);

                L.tileLayer(window.mapConfig.tileUrl, {
                    maxZoom: 19,
                    attribution: window.mapConfig.attribution,
                }).addTo(map);

                const markers = points.map(p =>
                    L.marker([p.lat, p.lng]).addTo(map).bindPopup(p.title));

                // قاب نقشه طوری تنظیم می‌شود که همه‌ی نقاط دیده شوند؛ برای یک نقطه
                // fitBounds بیش از حد زوم می‌کند، پس حالت تک‌نقطه‌ای جدا شده.
                if (markers.length === 1) {
                    map.setView([points[0].lat, points[0].lng], 15);
                } else {
                    map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
                }

                setTimeout(() => map.invalidateSize(), 200);
            },
        };
    }
</script>
@endpush
