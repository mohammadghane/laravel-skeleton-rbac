/**
 * انتخابگر نقطه روی نقشه‌ی OpenStreetMap.
 *
 * Leaflet از node_modules می‌آید نه CDN، تا با قاعده‌ی پروژه (هیچ فایلی از
 * اینترنت) هم‌خوان باشد. تنها چیزی که همچنان از شبکه می‌آید خودِ «کاشی‌های» نقشه
 * است؛ آن‌ها داده‌ی زنده‌ی OpenStreetMap‌اند و قابل بسته‌بندی نیستند. اگر سرور
 * دسترسی اینترنت نداشته باشد، نقشه خاکستری می‌ماند ولی فرم و ثبت مختصات کار
 * می‌کند (نگاه کنید به پیام داخل صفحه).
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// آیکون پیش‌فرض Leaflet آدرس تصویرها را نسبت به CSS حدس می‌زند و با bundling
// خراب می‌شود؛ صریح از همان فایل‌های محلی ساخته می‌شود.
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';

const markerIcon = L.icon({
    iconUrl,
    iconRetinaUrl,
    shadowUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

window.L = L;

/**
 * کامپوننت Alpine برای فرم «موقعیت جغرافیایی».
 *
 * با کلیک روی نقشه، نشانگر جابه‌جا می‌شود و مختصات در دو input مخفی می‌نشیند؛
 * پس ارسال فرم همان مسیر معمول پروژه است و سمت سرور چیز خاصی لازم ندارد.
 */
window.locationPicker = function (config) {
    return {
        lat: config.lat ?? null,
        lng: config.lng ?? null,
        map: null,
        marker: null,

        init() {
            // مرکز پیش‌فرض: اگر نقطه‌ای ثبت شده همان، وگرنه مرکز ایران.
            const hasPoint = this.lat !== null && this.lng !== null;
            const center = hasPoint ? [this.lat, this.lng] : [config.fallback.lat, config.fallback.lng];

            this.map = L.map(this.$refs.map).setView(center, hasPoint ? 15 : config.fallback.zoom);

            L.tileLayer(window.mapConfig.tileUrl, {
                maxZoom: 19,
                attribution: window.mapConfig.attribution,
            }).addTo(this.map);

            if (hasPoint) {
                this.place(this.lat, this.lng);
            }

            this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng));

            // نقشه داخل مودال ساخته می‌شود و ابعادش در لحظه‌ی init هنوز نهایی
            // نیست؛ بدون این، فقط بخشی از کاشی‌ها رندر می‌شوند.
            setTimeout(() => this.map.invalidateSize(), 200);
        },

        place(lat, lng) {
            this.lat = Number(lat.toFixed(7));
            this.lng = Number(lng.toFixed(7));

            if (this.marker) {
                this.marker.setLatLng([this.lat, this.lng]);
            } else {
                this.marker = L.marker([this.lat, this.lng], { icon: markerIcon, draggable: true }).addTo(this.map);
                // کشیدن نشانگر هم باید مختصات را به‌روز کند، نه فقط کلیک.
                this.marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    this.place(p.lat, p.lng);
                });
            }
        },

        hasPoint() {
            return this.lat !== null && this.lng !== null;
        },
    };
};

/**
 * انتخابگر نقطه برای «ثبت نظر».
 *
 * با هر کلیک، نقطه به سرور فرستاده می‌شود و پاسخ («مجاز هستید» یا «مجاز نیستید»)
 * نمایش داده می‌شود. بررسی عمداً سمت سرور است نه با محاسبه‌ی محلی: اگر شعاع‌ها را
 * به مرورگر می‌فرستادیم، کاربر می‌توانست آن‌ها را عوض کند و پیام دلخواه بگیرد -
 * و مهم‌تر، دو پیاده‌سازیِ موازیِ فاصله می‌داشتیم که می‌توانند از هم جدا بیفتند.
 */
window.commentPicker = function (config) {
    return {
        lat: null,
        lng: null,
        map: null,
        marker: null,
        busy: false,
        checked: false,
        allowed: false,
        message: '',
        detail: '',

        init() {
            this.map = L.map(this.$refs.map)
                .setView([config.fallback.lat, config.fallback.lng], config.fallback.zoom);

            L.tileLayer(window.mapConfig.tileUrl, {
                maxZoom: 19,
                attribution: window.mapConfig.attribution,
            }).addTo(this.map);

            this.drawAllowedZones();

            this.map.on('click', (e) => this.select(e.latlng.lat, e.latlng.lng));

            setTimeout(() => this.map.invalidateSize(), 200);
        },

        /**
         * محدوده‌های مجاز به‌صورت دایره نمایش داده می‌شوند تا کاربر پیش از کلیک
         * بداند کجا مجاز است - وگرنه باید کورکورانه کلیک کند تا پیام بگیرد.
         *
         * این فقط راهنمای بصری است؛ تصمیم واقعی همچنان با سرور است.
         */
        drawAllowedZones() {
            const zones = window.allowedZones ?? [];
            if (!zones.length) return;

            const circles = zones.map(z =>
                L.circle([z.lat, z.lng], {
                    radius: z.radius,
                    color: '#3E6259',
                    fillColor: '#3E6259',
                    fillOpacity: 0.12,
                    weight: 1,
                }).addTo(this.map).bindPopup(`${z.title} — ${z.radius} متر`));

            if (circles.length === 1) {
                this.map.setView([zones[0].lat, zones[0].lng], 15);
            } else {
                this.map.fitBounds(L.featureGroup(circles).getBounds().pad(0.3));
            }
        },

        async select(lat, lng) {
            this.lat = Number(lat.toFixed(7));
            this.lng = Number(lng.toFixed(7));

            if (this.marker) {
                this.marker.setLatLng([this.lat, this.lng]);
            } else {
                this.marker = L.marker([this.lat, this.lng]).addTo(this.map);
            }

            this.busy = true;
            this.checked = false;

            try {
                const res = await fetch(config.checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ latitude: this.lat, longitude: this.lng }),
                });

                const data = await res.json();

                this.allowed = data.allowed === true;
                this.message = data.message;
                this.detail = this.buildDetail(data);
                this.checked = true;
            } catch {
                this.allowed = false;
                this.message = 'بررسی موقعیت انجام نشد.';
                this.detail = 'ارتباط با سرور برقرار نشد.';
                this.checked = true;
            } finally {
                this.busy = false;
            }
        },

        buildDetail(data) {
            if (!data.location) return '';

            return data.allowed
                ? `داخل محدوده‌ی «${data.location}» — فاصله: ${data.distance} متر`
                : `نزدیک‌ترین موقعیت: «${data.location}» — فاصله: ${data.distance} متر`;
        },
    };
};
