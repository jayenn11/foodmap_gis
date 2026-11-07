/**
 * FoodMap GIS - Admin CRUD Controller (Alpine.js & Google Maps Mini-Map Picker)
 * Mengelola Penyedia Makanan, Menu Makanan, dan Kategori Makanan
 */

const ADMIN_DEFAULT_CENTER = { lat: -7.0305, lng: 107.5312 };
const ADMIN_DEFAULT_ZOOM = 14;
const ADMIN_FOCUS_ZOOM = 16;

let adminMiniMap = null;
let adminPickerMarker = null;

/**
 * Buat ikon SVG Marker biru untuk Coordinate Picker di Mini Map
 */
function createPickerMarkerIcon() {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 34 42" fill="none">
        <path d="M17 1C8.163 1 1 8.163 1 17C1 28.25 17 41 17 41C17 41 33 28.25 33 17C33 8.163 25.837 1 17 1Z" fill="#1A73E8" stroke="#FFFFFF" stroke-width="2"/>
        <circle cx="17" cy="16.5" r="7" fill="#FFFFFF"/>
        <circle cx="17" cy="16.5" r="3.5" fill="#1A73E8"/>
    </svg>`;

    if (window.google && window.google.maps) {
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(36, 44),
            anchor: new google.maps.Point(18, 44)
        };
    }
    return null;
}

/**
 * Inisialisasi Google Maps Mini-Map untuk Coordinate Picker
 */
function ensureAdminMiniMapInitialized(initialLat, initialLng) {
    const container = document.getElementById('admin-mini-map');
    if (!container || !window.google || !window.google.maps) return;

    const lat = Number.isFinite(Number(initialLat)) ? Number(initialLat) : ADMIN_DEFAULT_CENTER.lat;
    const lng = Number.isFinite(Number(initialLng)) ? Number(initialLng) : ADMIN_DEFAULT_CENTER.lng;
    const pos = { lat, lng };

    if (!adminMiniMap) {
        adminMiniMap = new google.maps.Map(container, {
            center: pos,
            zoom: ADMIN_DEFAULT_ZOOM,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true,
            gestureHandling: 'greedy'
        });

        adminPickerMarker = new google.maps.Marker({
            position: pos,
            map: adminMiniMap,
            draggable: true,
            title: 'Geser marker atau klik peta untuk memilih titik koordinat',
            icon: createPickerMarkerIcon()
        });

        // Klik pada mini map -> pindahkan marker & kirim event koordinat baru ke Alpine
        adminMiniMap.addListener('click', (event) => {
            if (!event.latLng) return;
            const clickedLat = event.latLng.lat();
            const clickedLng = event.latLng.lng();
            adminPickerMarker.setPosition({ lat: clickedLat, lng: clickedLng });
            adminMiniMap.panTo({ lat: clickedLat, lng: clickedLng });

            window.dispatchEvent(new CustomEvent('admin-picker-coords-changed', {
                detail: {
                    latitude: clickedLat.toFixed(8),
                    longitude: clickedLng.toFixed(8)
                }
            }));
        });

        // Geser (dragend) marker -> kirim event koordinat baru ke Alpine
        adminPickerMarker.addListener('dragend', () => {
            const newPos = adminPickerMarker.getPosition();
            if (!newPos) return;
            const draggedLat = newPos.lat();
            const draggedLng = newPos.lng();
            adminMiniMap.panTo({ lat: draggedLat, lng: draggedLng });

            window.dispatchEvent(new CustomEvent('admin-picker-coords-changed', {
                detail: {
                    latitude: draggedLat.toFixed(8),
                    longitude: draggedLng.toFixed(8)
                }
            }));
        });
    } else {
        google.maps.event.trigger(adminMiniMap, 'resize');
        adminPickerMarker.setPosition(pos);
        adminMiniMap.setCenter(pos);
        adminMiniMap.setZoom(ADMIN_FOCUS_ZOOM);
    }
}

/**
 * Sinkronkan posisi marker pada mini-map ketika admin mengetik koordinat manual
 */
function syncAdminMiniMapPosition(lat, lng, shouldPan = true) {
    if (!adminMiniMap || !adminPickerMarker || !window.google || !window.google.maps) return;
    const numLat = Number(lat);
    const numLng = Number(lng);
    if (!Number.isFinite(numLat) || !Number.isFinite(numLng)) return;
    if (numLat < -90 || numLat > 90 || numLng < -180 || numLng > 180) return;

    const pos = { lat: numLat, lng: numLng };
    adminPickerMarker.setPosition(pos);
    if (shouldPan) {
        adminMiniMap.panTo(pos);
    }
}

/**
 * Callback global saat script Google Maps selesai dimuat di halaman Admin
 */
window.initAdminMap = function () {
    window.dispatchEvent(new CustomEvent('admin-gmap-ready'));
};

/**
 * Komponen Alpine.js Utama untuk Panel Kelola Data (Admin CRUD)
 */
window.foodMapAdminApp = function () {
    return {
        places: [],
        categories: [],
        isLoading: true,
        searchQuery: '',
        filterCategory: '',
        currentPage: 1,
        perPage: 10,

        // Modal Tambah / Edit Tempat Makan
        placeModal: {
            isOpen: false,
            mode: 'create', // 'create' | 'edit'
            isSubmitting: false,
            isLocating: false,
            error: '',
            form: {
                id_penyedia: null,
                nama_penyedia: '',
                id_kategori: '',
                alamat: '',
                latitude: '-7.03050000',
                longitude: '107.53120000',
                jam_buka: '08:00',
                jam_tutup: '21:00',
                no_telepon: '',
                foto: ''
            }
        },

        // Modal Konfirmasi Hapus Tempat Makan
        deletePlaceModal: {
            isOpen: false,
            isSubmitting: false,
            place: null
        },

        // Modal Kelola Menu Makanan
        menuModal: {
            isOpen: false,
            isLoading: false,
            isSubmitting: false,
            error: '',
            place: null,
            menus: [],
            formMode: 'create', // 'create' | 'edit'
            form: {
                id_menu: null,
                nama_menu: '',
                harga: ''
            }
        },

        // Modal Kelola Kategori Makanan
        categoryModal: {
            isOpen: false,
            isSubmitting: false,
            error: '',
            formMode: 'create', // 'create' | 'edit'
            form: {
                id_kategori: null,
                nama_kategori: '',
                deskripsi: ''
            }
        },

        // Notifikasi Toast
        toast: {
            show: false,
            message: '',
            type: 'success',
            timer: null
        },

        init() {
            this.loadAllData();

            this.$watch('searchQuery', () => {
                this.currentPage = 1;
            });

            this.$watch('filterCategory', () => {
                this.currentPage = 1;
            });

            window.addEventListener('admin-picker-coords-changed', (e) => {
                if (e.detail) {
                    this.placeModal.form.latitude = String(e.detail.latitude);
                    this.placeModal.form.longitude = String(e.detail.longitude);
                }
            });

            window.addEventListener('admin-gmap-ready', () => {
                if (this.placeModal.isOpen) {
                    this.$nextTick(() => {
                        ensureAdminMiniMapInitialized(
                            this.placeModal.form.latitude,
                            this.placeModal.form.longitude
                        );
                    });
                }
            });
        },

        showToast(message, type = 'success') {
            if (this.toast.timer) {
                clearTimeout(this.toast.timer);
            }
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            this.toast.timer = setTimeout(() => {
                this.toast.show = false;
            }, 4000);
        },

        async loadAllData() {
            this.isLoading = true;
            await Promise.all([this.fetchCategories(), this.fetchPlaces()]);
            this.isLoading = false;
        },

        async fetchCategories() {
            try {
                const res = await fetch('../api/admin/categories.php', {
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

        async fetchPlaces() {
            try {
                const res = await fetch('../api/admin/places.php', {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (json.status === 'success' && Array.isArray(json.data)) {
                    this.places = json.data;
                    if (this.currentPage > this.totalPages) {
                        this.currentPage = this.totalPages;
                    }
                }
            } catch (err) {
                console.error('Gagal mengambil daftar tempat makan:', err);
                this.showToast('Gagal memuat daftar penyedia makanan.', 'error');
            }
        },

        /**
         * Filter cepat client-side untuk tabel penyedia makanan
         */
        get filteredPlaces() {
            const q = this.searchQuery.trim().toLowerCase();
            const catId = String(this.filterCategory).trim();

            return this.places.filter((item) => {
                const matchCategory = catId === '' || String(item.id_kategori) === catId;
                if (!matchCategory) return false;

                if (q === '') return true;
                const nama = String(item.nama_penyedia || '').toLowerCase();
                const alamat = String(item.alamat || '').toLowerCase();
                const kategori = String(item.nama_kategori || '').toLowerCase();
                return nama.includes(q) || alamat.includes(q) || kategori.includes(q);
            });
        },

        /**
         * Paginasi Sisi Klien (10 baris per halaman)
         */
        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredPlaces.length / this.perPage));
        },

        get paginatedPlaces() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredPlaces.slice(start, start + this.perPage);
        },

        get paginationStart() {
            if (this.filteredPlaces.length === 0) return 0;
            return (this.currentPage - 1) * this.perPage + 1;
        },

        get paginationEnd() {
            if (this.filteredPlaces.length === 0) return 0;
            return Math.min(this.currentPage * this.perPage, this.filteredPlaces.length);
        },

        get pageNumbers() {
            return Array.from({ length: this.totalPages }, (_, idx) => idx + 1);
        },

        goToPage(page) {
            const target = Number(page);
            if (target >= 1 && target <= this.totalPages) {
                this.currentPage = target;
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        // =========================================================
        // 1. CRUD PENYEDIA MAKANAN (+ MINI MAP COORDINATE PICKER)
        // =========================================================
        openCreatePlaceModal() {
            this.placeModal.mode = 'create';
            this.placeModal.error = '';
            this.placeModal.form = {
                id_penyedia: null,
                nama_penyedia: '',
                id_kategori: this.categories.length > 0 ? String(this.categories[0].id_kategori) : '',
                alamat: '',
                latitude: '-7.03050000',
                longitude: '107.53120000',
                jam_buka: '08:00',
                jam_tutup: '21:00',
                no_telepon: '',
                foto: ''
            };
            this.placeModal.isOpen = true;

            this.$nextTick(() => {
                setTimeout(() => {
                    ensureAdminMiniMapInitialized(
                        this.placeModal.form.latitude,
                        this.placeModal.form.longitude
                    );
                }, 80);
            });
        },

        openEditPlaceModal(place) {
            this.placeModal.mode = 'edit';
            this.placeModal.error = '';
            this.placeModal.form = {
                id_penyedia: Number(place.id_penyedia),
                nama_penyedia: place.nama_penyedia || '',
                id_kategori: String(place.id_kategori || ''),
                alamat: place.alamat || '',
                latitude: Number(place.latitude).toFixed(8),
                longitude: Number(place.longitude).toFixed(8),
                jam_buka: this.formatTime(place.jam_buka),
                jam_tutup: this.formatTime(place.jam_tutup),
                no_telepon: place.no_telepon || '',
                foto: place.foto || ''
            };
            this.placeModal.isOpen = true;

            this.$nextTick(() => {
                setTimeout(() => {
                    ensureAdminMiniMapInitialized(
                        this.placeModal.form.latitude,
                        this.placeModal.form.longitude
                    );
                }, 80);
            });
        },

        closePlaceModal() {
            this.placeModal.isOpen = false;
            this.placeModal.error = '';
        },

        onManualCoordInput() {
            syncAdminMiniMapPosition(
                this.placeModal.form.latitude,
                this.placeModal.form.longitude,
                true
            );
        },

        useCurrentLocationForPicker() {
            if (!navigator.geolocation) {
                this.placeModal.error = 'Peramban Anda tidak mendukung fitur geolokasi.';
                return;
            }

            this.placeModal.isLocating = true;
            this.placeModal.error = '';

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude.toFixed(8);
                    const lng = pos.coords.longitude.toFixed(8);
                    this.placeModal.form.latitude = lat;
                    this.placeModal.form.longitude = lng;
                    this.placeModal.isLocating = false;

                    syncAdminMiniMapPosition(lat, lng, true);
                    if (adminMiniMap) {
                        adminMiniMap.setZoom(ADMIN_FOCUS_ZOOM);
                    }
                    this.showToast('Koordinat lokasi saat ini berhasil diterapkan pada peta!', 'success');
                },
                (err) => {
                    this.placeModal.isLocating = false;
                    let msg = 'Gagal mengambil lokasi Anda saat ini. Pastikan izin lokasi diaktifkan.';
                    if (err.code === err.PERMISSION_DENIED) {
                        msg = 'Izin akses lokasi ditolak pada peramban Anda.';
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

        async submitPlaceForm() {
            this.placeModal.error = '';
            const f = this.placeModal.form;

            if (!f.nama_penyedia.trim() || !f.id_kategori || !f.alamat.trim() || !f.jam_buka || !f.jam_tutup) {
                this.placeModal.error = 'Mohon lengkapi seluruh kolom wajib (Nama Tempat, Kategori, Alamat, Jam Buka & Jam Tutup).';
                return;
            }

            const lat = Number(f.latitude);
            const lng = Number(f.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                this.placeModal.error = 'Koordinat Latitude atau Longitude tidak valid.';
                return;
            }

            this.placeModal.isSubmitting = true;

            try {
                const isEdit = this.placeModal.mode === 'edit';
                const payload = {
                    action: isEdit ? 'update' : 'create',
                    id_penyedia: f.id_penyedia,
                    nama_penyedia: f.nama_penyedia.trim(),
                    id_kategori: Number(f.id_kategori),
                    alamat: f.alamat.trim(),
                    latitude: lat,
                    longitude: lng,
                    jam_buka: f.jam_buka,
                    jam_tutup: f.jam_tutup,
                    no_telepon: f.no_telepon ? f.no_telepon.trim() : '',
                    foto: f.foto ? f.foto.trim() : ''
                };

                const res = await fetch('../api/admin/places.php', {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal menyimpan data penyedia makanan.');
                }

                this.showToast(json.message || 'Data tempat makan berhasil disimpan.', 'success');
                this.closePlaceModal();
                await Promise.all([this.fetchPlaces(), this.fetchCategories()]);
            } catch (err) {
                this.placeModal.error = err.message || 'Terjadi kesalahan saat menyimpan data.';
            } finally {
                this.placeModal.isSubmitting = false;
            }
        },

        openDeletePlaceConfirm(place) {
            this.deletePlaceModal.place = place;
            this.deletePlaceModal.isOpen = true;
        },

        closeDeletePlaceConfirm() {
            this.deletePlaceModal.isOpen = false;
            this.deletePlaceModal.place = null;
        },

        async confirmDeletePlace() {
            if (!this.deletePlaceModal.place) return;
            const targetId = Number(this.deletePlaceModal.place.id_penyedia);

            this.deletePlaceModal.isSubmitting = true;

            try {
                const res = await fetch('../api/admin/places.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id_penyedia: targetId
                    })
                });

                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal menghapus data penyedia makanan.');
                }

                this.showToast(json.message || 'Data tempat makan berhasil dihapus.', 'success');
                this.closeDeletePlaceConfirm();
                await Promise.all([this.fetchPlaces(), this.fetchCategories()]);
            } catch (err) {
                this.showToast(err.message || 'Gagal menghapus tempat makan.', 'error');
            } finally {
                this.deletePlaceModal.isSubmitting = false;
            }
        },

        // =========================================================
        // 2. CRUD MENU MAKANAN PER PENYEDIA
        // =========================================================
        async openManageMenuModal(place) {
            this.menuModal.place = place;
            this.menuModal.menus = [];
            this.menuModal.error = '';
            this.resetMenuForm();
            this.menuModal.isOpen = true;
            await this.fetchMenusForPlace(place.id_penyedia);
        },

        closeManageMenuModal() {
            this.menuModal.isOpen = false;
            this.menuModal.place = null;
            this.menuModal.menus = [];
            this.resetMenuForm();
        },

        resetMenuForm() {
            this.menuModal.formMode = 'create';
            this.menuModal.error = '';
            this.menuModal.form = {
                id_menu: null,
                nama_menu: '',
                harga: ''
            };
        },

        startEditMenu(menu) {
            this.menuModal.formMode = 'edit';
            this.menuModal.error = '';
            this.menuModal.form = {
                id_menu: Number(menu.id_menu),
                nama_menu: menu.nama_menu,
                harga: String(Math.round(Number(menu.harga)))
            };
        },

        async fetchMenusForPlace(idPenyedia) {
            this.menuModal.isLoading = true;
            try {
                const res = await fetch(`../api/admin/menus.php?id_penyedia=${encodeURIComponent(idPenyedia)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (json.status === 'success' && Array.isArray(json.data)) {
                    this.menuModal.menus = json.data;
                }
            } catch (err) {
                this.menuModal.error = 'Gagal memuat daftar menu makanan.';
            } finally {
                this.menuModal.isLoading = false;
            }
        },

        async submitMenuForm() {
            if (!this.menuModal.place) return;
            this.menuModal.error = '';

            const namaMenu = this.menuModal.form.nama_menu.trim();
            const hargaNum = Number(this.menuModal.form.harga);

            if (!namaMenu || this.menuModal.form.harga === '' || !Number.isFinite(hargaNum) || hargaNum < 0) {
                this.menuModal.error = 'Nama menu dan harga (angka Rupiah positif) wajib diisi.';
                return;
            }

            this.menuModal.isSubmitting = true;
            const isEdit = this.menuModal.formMode === 'edit';
            const placeId = Number(this.menuModal.place.id_penyedia);

            try {
                const payload = isEdit
                    ? {
                        action: 'update',
                        id_menu: Number(this.menuModal.form.id_menu),
                        nama_menu: namaMenu,
                        harga: hargaNum
                    }
                    : {
                        action: 'create',
                        id_penyedia: placeId,
                        nama_menu: namaMenu,
                        harga: hargaNum
                    };

                const res = await fetch('../api/admin/menus.php', {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal menyimpan menu makanan.');
                }

                this.showToast(json.message, 'success');
                this.resetMenuForm();
                await Promise.all([
                    this.fetchMenusForPlace(placeId),
                    this.fetchPlaces()
                ]);
            } catch (err) {
                this.menuModal.error = err.message || 'Terjadi kesalahan saat menyimpan menu.';
            } finally {
                this.menuModal.isSubmitting = false;
            }
        },

        async deleteMenuItem(menu) {
            if (!this.menuModal.place) return;
            const placeId = Number(this.menuModal.place.id_penyedia);

            try {
                const res = await fetch('../api/admin/menus.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id_menu: Number(menu.id_menu)
                    })
                });

                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal menghapus menu.');
                }

                if (this.menuModal.formMode === 'edit' && Number(this.menuModal.form.id_menu) === Number(menu.id_menu)) {
                    this.resetMenuForm();
                }

                this.showToast(json.message, 'success');
                await Promise.all([
                    this.fetchMenusForPlace(placeId),
                    this.fetchPlaces()
                ]);
            } catch (err) {
                this.menuModal.error = err.message || 'Gagal menghapus menu.';
            }
        },

        // =========================================================
        // 3. CRUD KATEGORI MAKANAN
        // =========================================================
        openCategoryModal() {
            this.resetCategoryForm();
            this.categoryModal.isOpen = true;
            this.fetchCategories();
        },

        closeCategoryModal() {
            this.categoryModal.isOpen = false;
            this.resetCategoryForm();
        },

        resetCategoryForm() {
            this.categoryModal.formMode = 'create';
            this.categoryModal.error = '';
            this.categoryModal.form = {
                id_kategori: null,
                nama_kategori: '',
                deskripsi: ''
            };
        },

        startEditCategory(cat) {
            this.categoryModal.formMode = 'edit';
            this.categoryModal.error = '';
            this.categoryModal.form = {
                id_kategori: Number(cat.id_kategori),
                nama_kategori: cat.nama_kategori || '',
                deskripsi: cat.deskripsi || ''
            };
        },

        async submitCategoryForm() {
            this.categoryModal.error = '';
            const nama = this.categoryModal.form.nama_kategori.trim();
            const deskripsi = this.categoryModal.form.deskripsi.trim();

            if (!nama) {
                this.categoryModal.error = 'Nama kategori makanan wajib diisi.';
                return;
            }

            this.categoryModal.isSubmitting = true;
            const isEdit = this.categoryModal.formMode === 'edit';

            try {
                const payload = isEdit
                    ? {
                        action: 'update',
                        id_kategori: Number(this.categoryModal.form.id_kategori),
                        nama_kategori: nama,
                        deskripsi: deskripsi
                    }
                    : {
                        action: 'create',
                        nama_kategori: nama,
                        deskripsi: deskripsi
                    };

                const res = await fetch('../api/admin/categories.php', {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    throw new Error(json.message || 'Gagal menyimpan kategori makanan.');
                }

                this.showToast(json.message, 'success');
                this.resetCategoryForm();
                await Promise.all([this.fetchCategories(), this.fetchPlaces()]);
            } catch (err) {
                this.categoryModal.error = err.message || 'Terjadi kesalahan saat menyimpan kategori.';
            } finally {
                this.categoryModal.isSubmitting = false;
            }
        },

        async deleteCategory(cat) {
            this.categoryModal.error = '';

            try {
                const res = await fetch('../api/admin/categories.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id_kategori: Number(cat.id_kategori)
                    })
                });

                const json = await res.json();
                if (!res.ok || json.status !== 'success') {
                    // Tampilkan pesan proteksi foreign key secara jelas di modal & toast
                    const errMsg = json.message || 'Kategori gagal dihapus karena masih terikat dengan penyedia makanan.';
                    this.categoryModal.error = errMsg;
                    this.showToast(errMsg, 'error');
                    return;
                }

                if (this.categoryModal.formMode === 'edit' && Number(this.categoryModal.form.id_kategori) === Number(cat.id_kategori)) {
                    this.resetCategoryForm();
                }

                this.showToast(json.message, 'success');
                await this.fetchCategories();
            } catch (err) {
                this.categoryModal.error = err.message || 'Terjadi kesalahan saat menghapus kategori.';
            }
        },

        // =========================================================
        // 4. FORMATTERS & HELPERS
        // =========================================================
        formatRupiah(amount) {
            const num = Number(amount);
            if (Number.isNaN(num) || num <= 0) return 'Rp 0';
            return 'Rp ' + Math.round(num).toLocaleString('id-ID');
        },

        formatTime(timeStr) {
            if (!timeStr) return '08:00';
            const parts = String(timeStr).split(':');
            if (parts.length >= 2) {
                return `${parts[0]}:${parts[1]}`;
            }
            return String(timeStr);
        },

        getPhotoPath(foto) {
            if (!foto) return '';
            const trimmed = String(foto).trim();
            if (trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('/')) {
                return trimmed;
            }
            return `../assets/images/places/${trimmed}`;
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
