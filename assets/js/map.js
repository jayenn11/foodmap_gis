/**
 * FoodMap GIS - Google Maps JavaScript API & Alpine.js Controller
 * Studi Kasus: Kawasan Soreang & Sekitarnya, Kabupaten Bandung
 */

const SOREANG_CENTER = { lat: -7.0305, lng: 107.5312 };
const DEFAULT_ZOOM = 14;
const FOCUS_ZOOM = 16;

// State terpusat untuk Google Maps, Marker, dan InfoWindow
let gMap = null;
let markers = [];
const markerById = new Map();
let activeMarker = null;
let activeInfoWindow = null;
let userLocationMarker = null;
let userRadiusCircle = null;
let pendingPlacesForMap = null;

// Koordinat pengguna selalu bernilai null sebelum geolokasi diaktifkan (tanpa fallback hardcoded)
const currentUserCoords = {
    lat: null,
    lng: null
};

/**
 * Escape karakter HTML untuk mencegah XSS pada InfoWindow Google Maps
 */
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Format angka ke mata uang Rupiah (contoh: Rp 13.000)
 */
function formatRupiah(amount) {
    const num = Number(amount);
    if (Number.isNaN(num) || num <= 0) return 'Rp 0';
    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

/**
 * Format rentang harga menu (contoh: Rp 13.000 - Rp 45.000)
 */
function formatPriceRange(minPrice, maxPrice) {
    const min = Number(minPrice) || 0;
    const max = Number(maxPrice) || 0;
    if (min <= 0 && max <= 0) {
        return 'Harga belum tersedia';
    }
    if (min === max) {
        return formatRupiah(min);
    }
    return `${formatRupiah(min)} - ${formatRupiah(max)}`;
}

/**
 * Format waktu HH:MM:SS menjadi HH:MM
 */
function formatTimeShort(timeStr) {
    if (!timeStr) return '-';
    const parts = String(timeStr).split(':');
    if (parts.length >= 2) {
        return `${parts[0]}:${parts[1]}`;
    }
    return String(timeStr);
}

/**
 * Validasi apakah koordinat bernilai numerik yang sah (bukan null/kosong)
 */
function isValidCoord(lat, lng) {
    return (
        lat !== null &&
        lng !== null &&
        lat !== '' &&
        lng !== '' &&
        Number.isFinite(Number(lat)) &&
        Number.isFinite(Number(lng))
    );
}

/**
 * Buat URL Google Maps Directions sesuai spesifikasi:
 * - Jika userLat & userLng valid: sertakan origin & destination
 * - Jika belum aktif: hanya sertakan destination
 */
function buildDirectionsUrl(destLat, destLng, userLat = null, userLng = null) {
    const dLat = Number(destLat);
    const dLng = Number(destLng);
    if ( isValidCoord(userLat, userLng) ) {
        const uLat = Number(userLat);
        const uLng = Number(userLng);
        return `https://www.google.com/maps/dir/?api=1&origin=${uLat},${uLng}&destination=${dLat},${dLng}`;
    }
    return `https://www.google.com/maps/dir/?api=1&destination=${dLat},${dLng}`;
}

/**
 * Buat ikon SVG Marker kustom untuk tempat makan
 * - Default/Tidak Aktif: Hijau Emerald (#059669) jika buka, Merah Rose (#E11D48) jika tutup
 * - Aktif (Dipilih): Biru Google (#1A73E8) dengan skala sedikit lebih besar
 */
function createPlaceMarkerIcon(isOpen, isSelected = false) {
    const fillColor = isSelected ? '#1A73E8' : (isOpen ? '#059669' : '#E11D48');
    const scale = isSelected ? 1.18 : 1;
    const width = Math.round(34 * scale);
    const height = Math.round(42 * scale);

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 34 42" fill="none">
        <path d="M17 1C8.163 1 1 8.163 1 17C1 28.25 17 41 17 41C17 41 33 28.25 33 17C33 8.163 25.837 1 17 1Z" fill="${fillColor}" stroke="#FFFFFF" stroke-width="2"/>
        <circle cx="17" cy="16.5" r="7.5" fill="#FFFFFF"/>
        <path d="M14.5 12.5V16.5M14.5 16.5V20.5M14.5 16.5H16.2C16.2 14.5 16.2 12.5 14.5 12.5ZM19.5 12.5V20.5M18 12.5V15.2C18 16 18.6 16.5 19.5 16.5C20.4 16.5 21 16 21 15.2V12.5" stroke="${fillColor}" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;

    if (window.google && window.google.maps) {
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(width, height),
            anchor: new google.maps.Point(Math.round(width / 2), height)
        };
    }
    return null;
}

/**
 * Buat ikon SVG Marker biru terang untuk lokasi pengguna
 */
function createUserMarkerIcon() {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
        <circle cx="16" cy="16" r="15" fill="#1A73E8" fill-opacity="0.22"/>
        <circle cx="16" cy="16" r="8.5" fill="#1A73E8" stroke="#FFFFFF" stroke-width="3"/>
    </svg>`;

    if (window.google && window.google.maps) {
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(32, 32),
            anchor: new google.maps.Point(16, 16)
        };
    }
    return null;
}

/**
 * Reset status aktif marker kembali ke ikon default (hijau/merah sesuai jam operasional)
 * dan pastikan tidak ada marker yang tertinggal berwarna biru aktif.
 */
function resetActiveMarker() {
    if (activeMarker && window.google && window.google.maps) {
        const pData = activeMarker.placeData || {};
        activeMarker.setIcon(createPlaceMarkerIcon(Boolean(pData.is_open), false));
        activeMarker.setZIndex(pData.is_open ? 20 : 10);
        activeMarker = null;
    }

    // Pastikan seluruh marker lain juga berada pada state default
    markerById.forEach((m) => {
        const pData = m.placeData || {};
        m.setIcon(createPlaceMarkerIcon(Boolean(pData.is_open), false));
        m.setZIndex(pData.is_open ? 20 : 10);
    });

    if (activeInfoWindow) {
        activeInfoWindow.close();
    }

    window.dispatchEvent(new CustomEvent('active-marker-reset'));
}

window.resetActiveMarker = resetActiveMarker;

/**
 * Set satu marker menjadi aktif (biru) setelah mereset marker aktif sebelumnya
 */
function setActiveMarkerById(idPenyedia) {
    resetActiveMarker();
    const targetId = Number(idPenyedia);
    const marker = markerById.get(targetId);
    if (!marker) return null;

    const pData = marker.placeData || {};
    marker.setIcon(createPlaceMarkerIcon(Boolean(pData.is_open), true));
    marker.setZIndex(100);
    activeMarker = marker;
    return marker;
}

/**
 * Bangun konten HTML ringkas untuk Google Maps InfoWindow
 */
function createInfoWindowHtml(place) {
    const id = Number(place.id_penyedia);
    const nama = escapeHtml(place.nama_penyedia);
    const kategori = escapeHtml(place.nama_kategori);
    const alamat = escapeHtml(place.alamat);
    const jamBuka = formatTimeShort(place.jam_buka);
    const jamTutup = formatTimeShort(place.jam_tutup);
    const priceRange = escapeHtml(formatPriceRange(place.min_price, place.max_price));
    const destLat = Number(place.latitude);
    const destLng = Number(place.longitude);

    const statusBadge = place.is_open
        ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;background-color:#ECFDF5;color:#059669;border:1px solid #A7F3D0;">
             <span style="width:6px;height:6px;border-radius:9999px;background-color:#059669;"></span>Buka
           </span>`
        : `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;background-color:#FFF1F2;color:#E11D48;border:1px solid #FECDD3;">
             <span style="width:6px;height:6px;border-radius:9999px;background-color:#E11D48;"></span>Tutup
           </span>`;

    const distanceBadge = (place.distance_km !== null && place.distance_km !== undefined)
        ? `<div style="margin-top:6px;font-size:12px;font-weight:600;color:#1A73E8;">
             &#128205; ${Number(place.distance_km).toFixed(2)} km dari lokasi Anda
           </div>`
        : '';

    return `
        <div class="gmap-infowindow">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:6px;">
                <span style="font-size:11px;font-weight:600;color:#475569;background:#F1F5F9;padding:2px 8px;border-radius:6px;max-width:175px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    ${kategori}
                </span>
                ${statusBadge}
            </div>
            <h3 style="font-size:15px;font-weight:700;color:#0F172A;margin:0 0 4px 0;line-height:1.3;">
                ${nama}
            </h3>
            <p style="font-size:12px;color:#475569;margin:0 0 6px 0;line-height:1.4;">
                ${alamat}
            </p>
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:#334155;padding-top:6px;border-top:1px solid #F1F5F9;">
                <span>&#128337; ${jamBuka} - ${jamTutup}</span>
                <span style="font-weight:700;color:#0F172A;">${priceRange}</span>
            </div>
            ${distanceBadge}
            <div style="display:flex;gap:8px;margin-top:10px;">
                <button
                    type="button"
                    onclick="window.triggerOpenPlaceDetail(${id})"
                    style="flex:1;padding:7px 10px;background-color:#1A73E8;color:#FFFFFF;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"
                >
                    Lihat Detail &amp; Menu
                </button>
                <button
                    type="button"
                    onclick="window.triggerNavigateRoute(${destLat}, ${destLng})"
                    style="padding:7px 10px;background-color:#F1F5F9;color:#1E293B;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;"
                >
                    Rute &#8599;
                </button>
            </div>
        </div>
    `;
}

/**
 * Bersihkan seluruh marker tempat makan dari peta (Array terpusat)
 */
function clearPlaceMarkers() {
    if (activeInfoWindow) {
        activeInfoWindow.close();
    }
    activeMarker = null;
    for (let i = 0; i < markers.length; i++) {
        markers[i].setMap(null);
    }
    markers = [];
    markerById.clear();
}

/**
 * Render daftar penyedia makanan sebagai marker pada Google Maps
 */
function renderPlaceMarkersOnMap(placesList, userLat = null, userLng = null, selectedId = null) {
    if (!gMap || !window.google || !window.google.maps) {
        pendingPlacesForMap = { placesList, userLat, userLng, selectedId };
        return;
    }

    clearPlaceMarkers();

    if (!Array.isArray(placesList) || placesList.length === 0) {
        return;
    }

    placesList.forEach((place) => {
        const lat = Number(place.latitude);
        const lng = Number(place.longitude);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const isSelected = selectedId !== null && Number(selectedId) === Number(place.id_penyedia);
        const marker = new google.maps.Marker({
            position: { lat, lng },
            map: gMap,
            title: place.nama_penyedia,
            icon: createPlaceMarkerIcon(Boolean(place.is_open), isSelected),
            zIndex: isSelected ? 100 : (place.is_open ? 20 : 10)
        });

        marker.placeData = place;
        if (isSelected) {
            activeMarker = marker;
        }

        marker.addListener('click', () => {
            openMarkerInfoWindow(place);
            window.dispatchEvent(new CustomEvent('map-marker-clicked', {
                detail: { id: Number(place.id_penyedia) }
            }));
        });

        markers.push(marker);
        markerById.set(Number(place.id_penyedia), marker);
    });
}

/**
 * Buka InfoWindow pada marker tempat makan tertentu & pastikan hanya 1 marker aktif
 */
function openMarkerInfoWindow(place) {
    if (!gMap || !window.google || !window.google.maps) return;

    const targetId = Number(place.id_penyedia);
    const marker = setActiveMarkerById(targetId);
    if (!marker) return;

    if (!activeInfoWindow) {
        activeInfoWindow = new google.maps.InfoWindow();
        google.maps.event.addListener(activeInfoWindow, 'closeclick', function () {
            resetActiveMarker();
        });
    }

    activeInfoWindow.setContent(createInfoWindowHtml(place));
    activeInfoWindow.open({
        anchor: marker,
        map: gMap,
        shouldFocus: false
    });
}

/**
 * Tampilkan / perbarui marker biru terang untuk posisi pengguna & lingkaran radius
 */
function updateUserLocationMarkerOnMap(lat, lng, radiusKm = null, panToUser = false) {
    if (!gMap || !window.google || !window.google.maps) return;

    if (!isValidCoord(lat, lng)) {
        if (userLocationMarker) {
            userLocationMarker.setMap(null);
            userLocationMarker = null;
        }
        if (userRadiusCircle) {
            userRadiusCircle.setMap(null);
            userRadiusCircle = null;
        }
        return;
    }

    const pos = { lat: Number(lat), lng: Number(lng) };

    if (!userLocationMarker) {
        userLocationMarker = new google.maps.Marker({
            position: pos,
            map: gMap,
            title: 'Lokasi Anda Saat Ini',
            icon: createUserMarkerIcon(),
            zIndex: 999
        });

        userLocationMarker.addListener('click', () => {
            if (!activeInfoWindow) {
                activeInfoWindow = new google.maps.InfoWindow();
            }
            activeInfoWindow.setContent(`
                <div style="padding:10px 14px;font-family:'Inter',sans-serif;">
                    <div style="font-size:13px;font-weight:700;color:#1A73E8;margin-bottom:2px;">Lokasi Anda Saat Ini</div>
                    <div style="font-size:11px;color:#475569;font-family:monospace;">${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}</div>
                </div>
            `);
            activeInfoWindow.open({
                anchor: userLocationMarker,
                map: gMap
            });
        });
    } else {
        userLocationMarker.setPosition(pos);
        userLocationMarker.setMap(gMap);
    }

    // Lingkaran visualisasi radius jika pengguna memilih filter radius (1, 3, atau 5 km)
    const numericRadius = Number(radiusKm);
    if (!Number.isNaN(numericRadius) && numericRadius > 0) {
        const radiusMeters = numericRadius * 1000;
        if (!userRadiusCircle) {
            userRadiusCircle = new google.maps.Circle({
                strokeColor: '#1A73E8',
                strokeOpacity: 0.65,
                strokeWeight: 1.5,
                fillColor: '#1A73E8',
                fillOpacity: 0.08,
                map: gMap,
                center: pos,
                radius: radiusMeters,
                clickable: false
            });
        } else {
            userRadiusCircle.setCenter(pos);
            userRadiusCircle.setRadius(radiusMeters);
            userRadiusCircle.setMap(gMap);
        }
    } else if (userRadiusCircle) {
        userRadiusCircle.setMap(null);
    }

    if (panToUser) {
        gMap.panTo(pos);
        if (gMap.getZoom() < 14) {
            gMap.setZoom(14);
        }
    }
}

/**
 * Helper global yang dipanggil dari tombol di dalam InfoWindow Google Maps
 */
window.triggerOpenPlaceDetail = function (idPenyedia) {
    window.dispatchEvent(new CustomEvent('open-place-detail', {
        detail: { id: Number(idPenyedia) }
    }));
};

window.triggerNavigateRoute = function (destLat, destLng) {
    window.dispatchEvent(new CustomEvent('navigate-place-route', {
        detail: { destLat: Number(destLat), destLng: Number(destLng) }
    }));
};

/**
 * Callback ketika autentikasi Google Maps API Key bermasalah
 */
window.gm_authFailure = function () {
    window.dispatchEvent(new CustomEvent('gmap-auth-failure'));
};

/**
 * Callback inisialisasi asinkron Google Maps JavaScript API
 */
window.initMap = function () {
    const mapElement = document.getElementById('map');
    if (!mapElement || !window.google || !window.google.maps) return;

    gMap = new google.maps.Map(mapElement, {
        center: SOREANG_CENTER,
        zoom: DEFAULT_ZOOM,
        mapTypeControl: true,
        mapTypeControlOptions: {
            position: google.maps.ControlPosition.TOP_RIGHT
        },
        streetViewControl: false,
        fullscreenControl: true,
        zoomControl: true,
        gestureHandling: 'greedy',
        styles: [
            {
                featureType: 'poi.business',
                stylers: [{ visibility: 'simplified' }]
            }
        ]
    });

    activeInfoWindow = new google.maps.InfoWindow();

    // Saat tombol X pada InfoWindow diklik, reset juga warna marker aktif
    google.maps.event.addListener(activeInfoWindow, 'closeclick', function () {
        resetActiveMarker();
    });

    // Klik area kosong di peta: reset marker aktif & tutup modal/drawer detail
    google.maps.event.addListener(gMap, 'click', function () {
        resetActiveMarker();
        window.dispatchEvent(new CustomEvent('close-detail-modal'));
    });

    window.dispatchEvent(new CustomEvent('gmap-ready'));

    if (pendingPlacesForMap) {
        renderPlaceMarkersOnMap(
            pendingPlacesForMap.placesList,
            pendingPlacesForMap.userLat,
            pendingPlacesForMap.userLng,
            pendingPlacesForMap.selectedId
        );
        pendingPlacesForMap = null;
    }
};

/**
 * Komponen Utama Alpine.js untuk Antarmuka PlaceFinder FoodMap GIS
 */
window.foodMapApp = function () {
    return {
        categories: [],
        places: [],
        totalPlaces: 0,
        isLoading: true,
        errorMessage: '',
        mapAuthWarning: false,

        filters: {
            search: '',
            kategori: '',
            open_now: false,
            price_range: 'all',
            distance_mode: 'all'
        },

        // Koordinat pengguna selalu null sebelum geolokasi diaktifkan (tanpa hardcoded fallback)
        userLocation: {
            lat: null,
            lng: null,
            isLocating: false
        },

        selectedPlaceId: null,
        highlightedPlaceId: null,
        highlightTimer: null,

        detailModal: {
            isOpen: false,
            isLoading: false,
            error: '',
            data: null
        },

        toast: {
            show: false,
            message: '',
            type: 'info',
            timer: null
        },

        init() {
            this.fetchCategories();
            this.fetchPlaces();

            // Event saat marker di peta diklik -> scroll ke kartu & highlight border
            window.addEventListener('map-marker-clicked', (e) => {
                if (e.detail && e.detail.id) {
                    this.handleMarkerClickedFromMap(e.detail.id);
                }
            });

            // Event saat marker direset -> bersihkan seleksi kartu di sidebar
            window.addEventListener('active-marker-reset', () => {
                this.selectedPlaceId = null;
                this.highlightedPlaceId = null;
            });

            // Event saat area kosong peta diklik -> tutup drawer detail
            window.addEventListener('close-detail-modal', () => {
                this.detailModal.isOpen = false;
            });

            // Event saat tombol "Lihat Detail & Menu" di InfoWindow diklik
            window.addEventListener('open-place-detail', (e) => {
                if (e.detail && e.detail.id) {
                    this.openDetailModal(e.detail.id);
                }
            });

            // Event saat tombol "Rute" di InfoWindow diklik
            window.addEventListener('navigate-place-route', (e) => {
                if (e.detail && e.detail.destLat !== undefined && e.detail.destLng !== undefined) {
                    this.openRouteDirections(e.detail.destLat, e.detail.destLng);
                }
            });

            // Event saat Google Maps selesai dimuat secara asinkron
            window.addEventListener('gmap-ready', () => {
                renderPlaceMarkersOnMap(
                    this.places,
                    this.userLocation.lat,
                    this.userLocation.lng,
                    this.selectedPlaceId
                );
                if (isValidCoord(this.userLocation.lat, this.userLocation.lng)) {
                    updateUserLocationMarkerOnMap(
                        this.userLocation.lat,
                        this.userLocation.lng,
                        this.getActiveRadiusKm(),
                        false
                    );
                }
            });

            window.addEventListener('gmap-auth-failure', () => {
                this.mapAuthWarning = true;
            });
        },

        showToast(message, type = 'info') {
            if (this.toast.timer) {
                clearTimeout(this.toast.timer);
            }
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            this.toast.timer = setTimeout(() => {
                this.toast.show = false;
            }, 3800);
        },

        async fetchCategories() {
            try {
                const res = await fetch('api/categories.php', {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (json.status === 'success' && Array.isArray(json.data)) {
                    this.categories = json.data;
                }
            } catch (err) {
                console.error('Gagal mengambil kategori:', err);
            }
        },

        getActiveRadiusKm() {
            const mode = this.filters.distance_mode;
            if (mode === '1' || mode === '3' || mode === '5') {
                return Number(mode);
            }
            return null;
        },

        buildQueryParams() {
            const params = new URLSearchParams();

            if (this.filters.search.trim() !== '') {
                params.set('search', this.filters.search.trim());
            }

            if (this.filters.kategori !== '') {
                params.set('kategori', this.filters.kategori);
            }

            if (this.filters.open_now) {
                params.set('open_now', '1');
            }

            // Filter Rentang Harga
            if (this.filters.price_range === 'under_20k') {
                params.set('max_price', '20000');
            } else if (this.filters.price_range === '20k_50k') {
                params.set('min_price', '20000');
                params.set('max_price', '50000');
            } else if (this.filters.price_range === 'above_50k') {
                params.set('min_price', '50000');
            }

            // Koordinat pengguna & filter spasial (hanya dikirim jika lokasi pengguna valid)
            if (isValidCoord(this.userLocation.lat, this.userLocation.lng)) {
                params.set('user_lat', String(this.userLocation.lat));
                params.set('user_lng', String(this.userLocation.lng));

                if (this.filters.distance_mode === 'nearest') {
                    params.set('nearest', '1');
                } else if (['1', '3', '5'].includes(this.filters.distance_mode)) {
                    params.set('radius', this.filters.distance_mode);
                }
            }

            return params.toString();
        },

        async fetchPlaces() {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const queryString = this.buildQueryParams();
                const url = queryString ? `api/places.php?${queryString}` : 'api/places.php';

                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                const json = await res.json();
                if (json.status === 'success' && Array.isArray(json.data)) {
                    this.places = json.data;
                    this.totalPlaces = Number(json.total ?? json.data.length);
                } else {
                    throw new Error(json.message || 'Gagal memuat daftar penyedia makanan.');
                }

                renderPlaceMarkersOnMap(
                    this.places,
                    this.userLocation.lat,
                    this.userLocation.lng,
                    this.selectedPlaceId
                );

                if (isValidCoord(this.userLocation.lat, this.userLocation.lng)) {
                    updateUserLocationMarkerOnMap(
                        this.userLocation.lat,
                        this.userLocation.lng,
                        this.getActiveRadiusKm(),
                        false
                    );
                }
            } catch (err) {
                console.error('Error fetchPlaces:', err);
                this.errorMessage = 'Gagal memuat data penyedia makanan dari server.';
                this.places = [];
                this.totalPlaces = 0;
                clearPlaceMarkers();
            } finally {
                this.isLoading = false;
            }
        },

        onDistanceFilterChange() {
            if (this.filters.distance_mode !== 'all' && !isValidCoord(this.userLocation.lat, this.userLocation.lng)) {
                this.showToast('Mendeteksi lokasi Anda untuk memfilter berdasarkan jarak...', 'info');
                this.detectUserLocation();
                return;
            }
            this.fetchPlaces();
        },

        detectUserLocation() {
            if (!navigator.geolocation) {
                this.showToast('Peramban Anda tidak mendukung fitur HTML5 Geolocation.', 'error');
                return;
            }

            this.userLocation.isLocating = true;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = Number(position.coords.latitude.toFixed(7));
                    const lng = Number(position.coords.longitude.toFixed(7));

                    this.userLocation.lat = lat;
                    this.userLocation.lng = lng;
                    currentUserCoords.lat = lat;
                    currentUserCoords.lng = lng;
                    this.userLocation.isLocating = false;

                    updateUserLocationMarkerOnMap(
                        lat,
                        lng,
                        this.getActiveRadiusKm(),
                        true
                    );

                    this.showToast('Lokasi Anda berhasil terdeteksi!', 'success');
                    this.fetchPlaces();
                },
                (error) => {
                    // Jangan gunakan koordinat palsu; biarkan bernilai null
                    this.userLocation.isLocating = false;
                    if (this.filters.distance_mode !== 'all') {
                        this.filters.distance_mode = 'all';
                    }
                    let msg = 'Lokasi belum diaktifkan. Silakan izinkan akses lokasi pada peramban Anda.';
                    if (error.code === error.PERMISSION_DENIED) {
                        msg = 'Izin akses lokasi ditolak pada peramban Anda.';
                    } else if (error.code === error.TIMEOUT) {
                        msg = 'Waktu pencarian lokasi habis. Silakan coba lagi.';
                    }
                    this.showToast(msg, 'error');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 8000,
                    maximumAge: 60000
                }
            );
        },

        /**
         * Standarisasi Alur Tombol "Rute" (Best Practice Routing):
         * 1. Jika koordinat user sudah aktif -> langsung buka Google Maps Directions dengan origin & destination.
         * 2. Jika koordinat user BELUM aktif -> panggil navigator.geolocation.getCurrentPosition secara instan:
         *    - Jika diizinkan: simpan koordinat, perbarui marker user, buka rute dengan origin & destination.
         *    - Jika ditolak/gagal: tetap buka Google Maps hanya dengan parameter destination (tanpa error kaku & tanpa koordinat palsu).
         */
        openRouteDirections(destLat, destLng) {
            const dLat = Number(destLat);
            const dLng = Number(destLng);

            // 1. Jika koordinat pengguna sudah aktif & valid
            if (isValidCoord(this.userLocation.lat, this.userLocation.lng)) {
                const url = buildDirectionsUrl(dLat, dLng, this.userLocation.lat, this.userLocation.lng);
                window.open(url, '_blank', 'noopener,noreferrer');
                return;
            }

            // Buka tab baru terlebih dahulu pada saat user click agar tidak diblokir popup blocker
            const newTab = window.open('about:blank', '_blank');

            const openUrlInTab = (targetUrl) => {
                if (newTab && !newTab.closed) {
                    newTab.location.href = targetUrl;
                } else {
                    window.open(targetUrl, '_blank', 'noopener,noreferrer');
                }
            };

            // Jika peramban tidak memiliki API geolokasi, langsung arahkan hanya dengan destination
            if (!navigator.geolocation) {
                openUrlInTab(buildDirectionsUrl(dLat, dLng, null, null));
                return;
            }

            this.userLocation.isLocating = true;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const uLat = Number(position.coords.latitude.toFixed(7));
                    const uLng = Number(position.coords.longitude.toFixed(7));

                    this.userLocation.lat = uLat;
                    this.userLocation.lng = uLng;
                    currentUserCoords.lat = uLat;
                    currentUserCoords.lng = uLng;
                    this.userLocation.isLocating = false;

                    updateUserLocationMarkerOnMap(
                        uLat,
                        uLng,
                        this.getActiveRadiusKm(),
                        false
                    );

                    // Perbarui estimasi jarak pada daftar tempat makan di latar belakang
                    this.fetchPlaces();

                    // Buka rute lengkap dengan origin & destination
                    const fullRouteUrl = buildDirectionsUrl(dLat, dLng, uLat, uLng);
                    openUrlInTab(fullRouteUrl);
                },
                () => {
                    // Jika user menolak/gagal: jangan tampilkan error kaku & jangan gunakan titik palsu
                    this.userLocation.isLocating = false;
                    const destOnlyUrl = buildDirectionsUrl(dLat, dLng, null, null);
                    openUrlInTab(destOnlyUrl);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 4500,
                    maximumAge: 60000
                }
            );
        },

        hasActiveFilters() {
            return (
                this.filters.search.trim() !== '' ||
                this.filters.kategori !== '' ||
                this.filters.open_now !== false ||
                this.filters.price_range !== 'all' ||
                this.filters.distance_mode !== 'all'
            );
        },

        resetFilters() {
            this.filters.search = '';
            this.filters.kategori = '';
            this.filters.open_now = false;
            this.filters.price_range = 'all';
            this.filters.distance_mode = 'all';
            resetActiveMarker();

            if (gMap) {
                gMap.panTo(SOREANG_CENTER);
                gMap.setZoom(DEFAULT_ZOOM);
            }

            this.fetchPlaces();
        },

        /**
         * Sinkronisasi Kartu -> Marker Peta:
         * Saat kartu diklik, kamera peta bergeser halus (smooth panTo) ke koordinat tempat makan,
         * menaikkan zoom proporsional ke level 16, dan mengaktifkan marker tersebut.
         */
        selectPlaceFromCard(place) {
            const id = Number(place.id_penyedia);
            this.selectedPlaceId = id;
            this.highlightedPlaceId = id;

            if (gMap && window.google && window.google.maps) {
                const pos = {
                    lat: Number(place.latitude),
                    lng: Number(place.longitude)
                };

                // Geser kamera secara halus menuju koordinat tempat makan
                gMap.panTo(pos);

                // Naikkan zoom secara proporsional ke level 16
                if (gMap.getZoom() !== FOCUS_ZOOM) {
                    gMap.setZoom(FOCUS_ZOOM);
                }

                openMarkerInfoWindow(place);
            }
        },

        /**
         * Sinkronisasi Marker Peta -> Kartu Sidebar:
         * Saat marker diklik, sidebar otomatis scroll halus ke kartu & memberi efek highlight border
         */
        handleMarkerClickedFromMap(idPenyedia) {
            const id = Number(idPenyedia);
            this.selectedPlaceId = id;
            this.highlightedPlaceId = id;

            if (this.highlightTimer) {
                clearTimeout(this.highlightTimer);
            }

            this.$nextTick(() => {
                const cardEl = document.getElementById(`place-card-${id}`);
                if (cardEl) {
                    cardEl.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });

            this.highlightTimer = setTimeout(() => {
                if (this.highlightedPlaceId === id) {
                    this.highlightedPlaceId = null;
                }
            }, 2200);
        },

        /**
         * Buka Drawer / Modal Detail Tempat Makan & Daftar Menu
         */
        async openDetailModal(idPenyedia) {
            const id = Number(idPenyedia);
            if (!id) return;

            // Sinkronkan juga fokus peta ke tempat makan terkait jika ada di daftar
            const foundPlace = this.places.find((p) => Number(p.id_penyedia) === id);
            if (foundPlace) {
                this.selectPlaceFromCard(foundPlace);
            }

            this.detailModal.isOpen = true;
            this.detailModal.isLoading = true;
            this.detailModal.error = '';
            this.detailModal.data = foundPlace ? { ...foundPlace, menus: [] } : null;

            try {
                const res = await fetch(`api/place-detail.php?id=${encodeURIComponent(id)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();

                if (res.ok && json.status === 'success' && json.data) {
                    const existingDistance = foundPlace ? foundPlace.distance_km : null;
                    this.detailModal.data = {
                        ...json.data,
                        distance_km: json.data.distance_km ?? existingDistance
                    };
                } else {
                    throw new Error(json.message || 'Gagal memuat detail penyedia makanan.');
                }
            } catch (err) {
                console.error('Error openDetailModal:', err);
                this.detailModal.error = err.message || 'Terjadi kesalahan saat mengambil detail tempat makan.';
            } finally {
                this.detailModal.isLoading = false;
            }
        },

        /**
         * Tutup Drawer / Modal Detail dan reset status marker aktif di peta
         */
        closeDetailModal() {
            this.detailModal.isOpen = false;
            resetActiveMarker();
        },

        /**
         * Format helper untuk template Alpine.js
         */
        formatRupiah(amount) {
            return formatRupiah(amount);
        },

        formatPriceRange(minPrice, maxPrice) {
            return formatPriceRange(minPrice, maxPrice);
        },

        formatTime(timeStr) {
            return formatTimeShort(timeStr);
        },

        getDirectionsUrl(destLat, destLng) {
            return buildDirectionsUrl(destLat, destLng, this.userLocation.lat, this.userLocation.lng);
        },

        getWhatsAppUrl(phone) {
            if (!phone) return '#';
            let digits = String(phone).replace(/[^0-9]/g, '');
            if (digits.startsWith('0')) {
                digits = '62' + digits.slice(1);
            }
            return `https://wa.me/${digits}`;
        },

        getPhotoPath(foto) {
            if (!foto) return '';
            const trimmed = String(foto).trim();
            if (trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('/')) {
                return trimmed;
            }
            return `assets/images/places/${trimmed}`;
        },

        /**
         * Penentu class warna badge dinamis berdasarkan ID atau nama kategori
         */
        getCategoryBadgeClass(namaKategori, idKategori = null) {
            const id = Number(idKategori);
            const name = String(namaKategori || '').toLowerCase();

            if (id === 1 || name.includes('tradisional') || name.includes('sunda')) {
                return 'bg-amber-50 text-amber-700 border border-amber-200';
            }
            if (id === 2 || name.includes('kafe') || name.includes('kopi')) {
                return 'bg-amber-900/10 text-amber-900 border border-amber-300';
            }
            if (id === 3 || name.includes('cepat saji') || name.includes('modern')) {
                return 'bg-rose-50 text-rose-700 border border-rose-200';
            }
            if (id === 4 || name.includes('bakso') || name.includes('mie') || name.includes('soto')) {
                return 'bg-orange-50 text-orange-700 border border-orange-200';
            }
            if (id === 5 || name.includes('seafood') || name.includes('bakaran')) {
                return 'bg-cyan-50 text-cyan-700 border border-cyan-200';
            }
            return 'bg-blue-50 text-blue-700 border border-blue-200';
        }
    };
};
