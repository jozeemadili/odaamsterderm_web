import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
Chart.defaults.font.family = "'Inter', 'SF Pro Display', system-ui, -apple-system, sans-serif";
Chart.defaults.font.weight = 400;
Chart.defaults.color = '#64748d';

window.Chart = Chart;

// --- Maps (Leaflet + OpenStreetMap tiles) ---------------------------------
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Vite rewrites asset URLs, so point Leaflet's default marker at them.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

window.L = L;

const DEFAULT_CENTER = [-6.7924, 39.2083]; // Dar es Salaam

// Store names come from sellers — never put them in popup HTML unescaped.
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');

    return div.innerHTML;
}

function tiles(map) {
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
}

document.addEventListener('alpine:init', () => {
    /**
     * Pin a single location on a map. Bind lat/lng with $wire.entangle(), e.g.
     * x-data="locationPicker({ lat: $wire.entangle('latitude'), lng: $wire.entangle('longitude') })"
     * The map element needs x-ref="map" inside a wire:ignore wrapper.
     */
    window.Alpine.data('locationPicker', ({ lat, lng, editable = true }) => ({
        lat,
        lng,
        editable,
        locating: false,
        error: null,
        map: null,
        marker: null,

        init() {
            const hasPoint = this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '';
            this.map = L.map(this.$refs.map, { scrollWheelZoom: false })
                .setView(hasPoint ? [this.lat, this.lng] : DEFAULT_CENTER, hasPoint ? 16 : 11);
            tiles(this.map);

            if (hasPoint) {
                this.placeMarker();
            }

            if (this.editable) {
                this.map.on('click', (e) => this.setPoint(e.latlng.lat, e.latlng.lng));
            }

            // The map is often first drawn inside a container that was hidden.
            setTimeout(() => this.map.invalidateSize(), 200);
        },

        setPoint(lat, lng) {
            this.lat = Number(lat.toFixed(7));
            this.lng = Number(lng.toFixed(7));
            this.error = null;
            this.placeMarker();
        },

        placeMarker() {
            const point = [this.lat, this.lng];

            if (this.marker) {
                this.marker.setLatLng(point);

                return;
            }

            this.marker = L.marker(point, { draggable: this.editable }).addTo(this.map);
            this.marker.on('dragend', () => {
                const p = this.marker.getLatLng();
                this.setPoint(p.lat, p.lng);
            });
        },

        useMyLocation() {
            if (!navigator.geolocation) {
                this.error = 'This browser cannot share its location. Click on the map instead.';

                return;
            }

            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.locating = false;
                    this.setPoint(pos.coords.latitude, pos.coords.longitude);
                    this.map.setView([this.lat, this.lng], 17);
                },
                () => {
                    this.locating = false;
                    this.error = 'Location permission was denied or unavailable. Click on the map to place the pin.';
                },
                { enableHighAccuracy: true, timeout: 10000 },
            );
        },
    }));
});

document.addEventListener('alpine:init', () => {
    /**
     * Read-only map with several store pins (and the customer's position).
     * x-data="storesMap({ stores: [{ lat, lng, name, url, note }], me: { lat, lng } | null })"
     */
    // Live distance from the customer's current position to the pickup store.
    window.Alpine.data('liveDistance', ({ lat, lng }) => ({
        state: 'finding',
        meters: null,
        watchId: null,
        init() {
            if (!navigator.geolocation) { this.state = 'failed'; return; }
            this.watchId = navigator.geolocation.watchPosition(
                (pos) => {
                    const toRad = (d) => d * Math.PI / 180;
                    const dLat = toRad(lat - pos.coords.latitude);
                    const dLng = toRad(lng - pos.coords.longitude);
                    const a = Math.sin(dLat / 2) ** 2
                        + Math.cos(toRad(pos.coords.latitude)) * Math.cos(toRad(lat)) * Math.sin(dLng / 2) ** 2;
                    this.meters = 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                    this.state = 'ready';
                },
                (err) => { this.state = err.code === 1 ? 'denied' : 'failed'; },
                { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 },
            );
        },
        destroy() { if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId); },
        get label() {
            if (this.meters === null) return '';
            return this.meters < 1000 ? `${Math.round(this.meters / 10) * 10} m` : `${(this.meters / 1000).toFixed(this.meters < 10000 ? 1 : 0)} km`;
        },
        // About 25 km/h by car or boda boda in town.
        get minutes() { return Math.max(1, Math.ceil(this.meters / 1000 / 25 * 60)); },
    }));

    window.Alpine.data('storesMap', ({ stores, me = null }) => ({
        init() {
            const map = L.map(this.$refs.map, { scrollWheelZoom: false });
            tiles(map);

            const bounds = [];
            stores.forEach((s) => {
                const marker = L.marker([s.lat, s.lng]).addTo(map);
                marker.bindPopup(`<strong>${escapeHtml(s.name)}</strong>${s.note ? `<br>${escapeHtml(s.note)}` : ''}${s.url ? `<br><a href="${encodeURI(s.url)}">View store</a>` : ''}`);
                bounds.push([s.lat, s.lng]);
            });

            if (me) {
                L.circleMarker([me.lat, me.lng], { radius: 8, color: '#01162f', fillColor: '#c89a44', fillOpacity: 1, weight: 3 })
                    .addTo(map)
                    .bindPopup('You are here');
                bounds.push([me.lat, me.lng]);
            }

            if (bounds.length > 1) {
                map.fitBounds(bounds, { paddingTopLeft: [50, 50], paddingBottomRight: [30, 30], maxZoom: 15 });
            } else {
                map.setView(bounds[0] ?? DEFAULT_CENTER, bounds.length ? 15 : 11);
            }

            setTimeout(() => map.invalidateSize(), 200);
        },
    }));
});
