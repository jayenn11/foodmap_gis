<?php
declare(strict_types=1);

// Ambil Google Maps API Key dari environment variable atau query string (opsional)
$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY') ?: ($_GET['gmaps_key'] ?? '');
$gmapsScriptUrl   = 'https://maps.googleapis.com/maps/api/js?callback=initMap';
if ($googleMapsApiKey !== '') {
    $gmapsScriptUrl = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode((string) $googleMapsApiKey) . '&callback=initMap';
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodMap GIS - Pencarian Penyedia Makanan Kawasan Soreang</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                    colors: {
                        gblue: {
                            DEFAULT: '#1A73E8',
                            hover: '#1557B0',
                            light: '#E8F0FE'
                        },
                        open: '#059669',
                        closed: '#E11D48'
                    }
                }
            }
        };
    </script>

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Main Map & Alpine Controller (dimuat sebelum Alpine.js) -->
    <script src="assets/js/map.js"></script>

    <!-- Alpine.js v3 -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body
    x-data="foodMapApp()"
    @keydown.escape.window="closeDetailModal()"
    class="h-screen overflow-hidden flex flex-col bg-[#F8FAFC] text-slate-900 antialiased"
>
    <!-- =========================================================
         1. HEADER NAVIGASI & FILTER BAR (TOP BAR)
         ========================================================= -->
    <header class="bg-white border-b border-slate-200 shadow-sm z-30 shrink-0">
        <div class="px-4 py-2.5 lg:px-5 flex flex-wrap items-center justify-between gap-3">
            <!-- Brand / Logo & Judul -->
            <a href="index.php" class="flex items-center gap-2.5 shrink-0 group">
                <div class="w-9 h-9 rounded-lg bg-[#1A73E8] text-white flex items-center justify-center shadow-sm group-hover:bg-[#1557B0] transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-base font-bold tracking-tight text-slate-900">FoodMap GIS</span>
                        <span class="hidden sm:inline-block text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-50 text-[#1A73E8] border border-blue-200">Soreang</span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-tight">Kawasan Soreang &amp; Sekitarnya</p>
                </div>
            </a>

            <!-- Filter Bar Utama -->
            <div class="flex flex-wrap items-center gap-2 flex-1 justify-end">
                <!-- Input Search (Debounce 300ms) -->
                <div class="relative w-full sm:w-56 lg:w-64">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                    </span>
                    <input
                        type="search"
                        x-model="filters.search"
                        @input.debounce.300ms="fetchPlaces()"
                        placeholder="Cari tempat makan atau menu..."
                        aria-label="Cari tempat makan atau menu"
                        class="w-full pl-9 pr-3 py-1.5 text-xs sm:text-sm bg-slate-50 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15 transition"
                    >
                </div>

                <!-- Dropdown Kategori (Dinamis dari api/categories.php) -->
                <div class="relative w-[calc(50%-0.25rem)] sm:w-auto">
                    <label for="filter-kategori" class="sr-only">Kategori Makanan</label>
                    <select
                        id="filter-kategori"
                        x-model="filters.kategori"
                        @change="fetchPlaces()"
                        class="w-full sm:w-48 appearance-none py-1.5 pl-3.5 pr-10 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg text-slate-700 font-medium focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15 cursor-pointer truncate"
                    >
                        <option value="">Semua Kategori</option>
                        <template x-for="cat in categories" :key="cat.id_kategori">
                            <option :value="cat.id_kategori" x-text="cat.nama_kategori"></option>
                        </template>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>

                <!-- Dropdown Rentang Harga -->
                <div class="relative w-[calc(50%-0.25rem)] sm:w-auto">
                    <label for="filter-harga" class="sr-only">Rentang Harga</label>
                    <select
                        id="filter-harga"
                        x-model="filters.price_range"
                        @change="fetchPlaces()"
                        class="w-full sm:w-48 appearance-none py-1.5 pl-3.5 pr-10 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg text-slate-700 font-medium focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15 cursor-pointer truncate"
                    >
                        <option value="all">Semua Harga</option>
                        <option value="under_20k">&lt; Rp 20.000</option>
                        <option value="20k_50k">Rp 20.000 - Rp 50.000</option>
                        <option value="above_50k">&gt; Rp 50.000</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>

                <!-- Dropdown Filter Jarak / Radius -->
                <div class="relative w-[calc(50%-0.25rem)] sm:w-auto">
                    <label for="filter-jarak" class="sr-only">Filter Jarak</label>
                    <select
                        id="filter-jarak"
                        x-model="filters.distance_mode"
                        @change="onDistanceFilterChange()"
                        class="w-full sm:w-40 appearance-none py-1.5 pl-3.5 pr-10 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg text-slate-700 font-medium focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15 cursor-pointer truncate"
                    >
                        <option value="all">Semua Jarak</option>
                        <option value="nearest">5 Terdekat</option>
                        <option value="1">Radius 1 km</option>
                        <option value="3">Radius 3 km</option>
                        <option value="5">Radius 5 km</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>

                <!-- Checkbox / Toggle "Buka Sekarang" -->
                <label
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs sm:text-sm font-medium cursor-pointer select-none transition"
                    :class="filters.open_now
                        ? 'bg-emerald-50 border-[#059669] text-[#059669]'
                        : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
                >
                    <input
                        type="checkbox"
                        x-model="filters.open_now"
                        @change="fetchPlaces()"
                        class="w-3.5 h-3.5 rounded text-[#059669] border-slate-300 focus:ring-[#059669]"
                    >
                    <span>Buka Sekarang</span>
                </label>

                <!-- Tombol "Lokasi Saya" -->
                <button
                    type="button"
                    @click="detectUserLocation()"
                    :disabled="userLocation.isLocating"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold border transition"
                    :class="userLocation.lat !== null
                        ? 'bg-blue-50 border-[#1A73E8] text-[#1A73E8]'
                        : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
                    title="Deteksi koordinat lokasi Anda saat ini"
                >
                    <svg
                        class="w-4 h-4"
                        :class="userLocation.isLocating ? 'animate-spin text-[#1A73E8]' : (userLocation.lat !== null ? 'text-[#1A73E8]' : 'text-slate-500')"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.364-5.364l-1.414 1.414M8.05 15.95l-1.414 1.414m0-11.314l1.414 1.414m7.9 7.9l1.414 1.414M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-text="userLocation.isLocating ? 'Mendeteksi...' : (userLocation.lat !== null ? 'Lokasi Aktif' : 'Lokasi Saya')"></span>
                </button>

                <!-- Tombol Navigasi Cepat ke Kelola Data (Admin) -->
                <a
                    href="views/admin.php"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition-colors duration-200 shrink-0"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Kelola Data (Admin)</span>
                </a>
            </div>
        </div>
    </header>

    <!-- =========================================================
         2. MAIN SPLIT-SCREEN CONTENT (SIDEBAR KIRI + PETA KANAN)
         ========================================================= -->
    <main class="flex-1 flex flex-col md:flex-row overflow-hidden relative h-[calc(100vh-64px)]">
        <!-- PANEL KIRI: SIDEBAR DAFTAR TEMPAT MAKAN -->
        <aside class="w-full md:w-96 lg:w-[420px] shrink-0 bg-white border-r border-slate-200 flex flex-col h-1/2 md:h-full overflow-hidden z-10">
            <!-- Sub-Header Sidebar: Ringkasan Jumlah & Reset Filter -->
            <div class="px-4 py-3 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between gap-2 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xs sm:text-sm font-semibold text-slate-700" aria-live="polite">
                        Menampilkan <span class="text-[#1A73E8] font-bold" x-text="totalPlaces">0</span> penyedia makanan
                    </span>
                </div>

                <button
                    type="button"
                    x-show="hasActiveFilters()"
                    x-cloak
                    @click="resetFilters()"
                    class="text-xs font-semibold text-[#1A73E8] hover:text-[#1557B0] hover:underline flex items-center gap-1"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>Reset Filter</span>
                </button>
            </div>

            <!-- Banner Info Koordinat Pengguna (Jika Aktif) -->
            <div
                x-show="userLocation.lat !== null && userLocation.lng !== null"
                x-cloak
                class="px-4 py-2 bg-blue-50/80 border-b border-blue-100 flex items-center justify-between text-xs text-slate-700 shrink-0"
            >
                <div class="flex items-center gap-1.5 truncate">
                    <span class="w-2 h-2 rounded-full bg-[#1A73E8] shrink-0"></span>
                    <span class="truncate">
                        Koordinat Anda:
                        <strong class="font-mono text-slate-900" x-text="`${userLocation.lat}, ${userLocation.lng}`"></strong>
                    </span>
                </div>
                <span class="text-[11px] font-semibold text-[#1A73E8] shrink-0 ml-2">Urut Terdekat</span>
            </div>

            <!-- Area Daftar Kartu dengan Scroll Mandiri -->
            <div id="places-sidebar-list" class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-3 bg-[#F8FAFC]">
                <!-- State Loading Skeleton -->
                <template x-if="isLoading">
                    <div class="space-y-3">
                        <template x-for="i in 4" :key="i">
                            <div class="bg-white rounded-xl border border-slate-200 p-3.5 animate-pulse">
                                <div class="flex gap-3">
                                    <div class="w-20 h-20 rounded-lg bg-slate-200 shrink-0"></div>
                                    <div class="flex-1 space-y-2 py-1">
                                        <div class="h-3.5 bg-slate-200 rounded w-1/3"></div>
                                        <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                        <div class="h-3 bg-slate-200 rounded w-full"></div>
                                        <div class="h-3 bg-slate-200 rounded w-1/2"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- State Error -->
                <template x-if="!isLoading && errorMessage !== ''">
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-center my-4">
                        <p class="text-sm font-semibold text-[#E11D48] mb-1" x-text="errorMessage"></p>
                        <button
                            type="button"
                            @click="fetchPlaces()"
                            class="mt-2 inline-flex items-center px-3 py-1.5 rounded-lg bg-[#E11D48] text-white text-xs font-semibold hover:bg-rose-700 transition"
                        >
                            Coba Muat Ulang
                        </button>
                    </div>
                </template>

                <!-- State Kosong -->
                <template x-if="!isLoading && errorMessage === '' && places.length === 0">
                    <div class="bg-white border border-slate-200 rounded-xl p-8 text-center my-4">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800 mb-1">Tidak Ada Tempat Makan Ditemukan</h3>
                        <p class="text-xs text-slate-500 mb-4">Coba ubah kata kunci pencarian, kategori, atau rentang harga filter Anda.</p>
                        <button
                            type="button"
                            @click="resetFilters()"
                            class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-[#1A73E8] text-white text-xs font-semibold hover:bg-[#1557B0] transition"
                        >
                            Tampilkan Semua Tempat
                        </button>
                    </div>
                </template>

                <!-- Daftar Kartu Tempat Makan -->
                <template x-if="!isLoading && errorMessage === '' && places.length > 0">
                    <div class="space-y-3">
                        <template x-for="place in places" :key="place.id_penyedia">
                            <article
                                :id="`place-card-${place.id_penyedia}`"
                                @click="selectPlaceFromCard(place)"
                                class="bg-white rounded-xl border transition-all duration-200 cursor-pointer hover:shadow-md overflow-hidden"
                                :class="{
                                    'place-card-highlighted': Number(highlightedPlaceId) === Number(place.id_penyedia),
                                    'border-[#1A73E8] ring-2 ring-[#1A73E8]/15 shadow-sm': Number(selectedPlaceId) === Number(place.id_penyedia),
                                    'border-slate-200 hover:border-slate-300': Number(selectedPlaceId) !== Number(place.id_penyedia)
                                }"
                            >
                                <div class="p-3.5">
                                    <div class="flex gap-3.5">
                                        <!-- Thumbnail Foto dengan Fallback Placeholder Bersih -->
                                        <div class="w-24 h-24 rounded-lg bg-slate-100 border border-slate-200/80 shrink-0 relative overflow-hidden flex flex-col items-center justify-center text-slate-400">
                                            <!-- Placeholder SVG Bersih -->
                                            <svg class="w-7 h-7 text-slate-300 mb-1" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <span class="text-[10px] font-semibold text-slate-400 px-1 text-center leading-tight line-clamp-1" x-text="place.nama_kategori"></span>

                                            <!-- Gambar Asli (jika tersedia di folder assets/images/places/) -->
                                            <template x-if="place.foto">
                                                <img
                                                    :src="getPhotoPath(place.foto)"
                                                    :alt="place.nama_penyedia"
                                                    loading="lazy"
                                                    @error="$event.target.style.display = 'none'"
                                                    class="absolute inset-0 w-full h-full object-cover"
                                                >
                                            </template>
                                        </div>

                                        <!-- Informasi Utama Kartu -->
                                        <div class="flex-1 min-w-0">
                                            <!-- Baris Badge Kategori & Status Buka/Tutup -->
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <span
                                                    class="inline-block text-[11px] font-semibold px-2 py-0.5 rounded-md truncate max-w-[170px]"
                                                    :class="getCategoryBadgeClass(place.nama_kategori, place.id_kategori)"
                                                    x-text="place.nama_kategori"
                                                ></span>

                                                <!-- Badge Status Operasional (#059669 Buka / #E11D48 Tutup) -->
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0"
                                                    :class="place.is_open
                                                        ? 'bg-emerald-50 text-[#059669] border border-emerald-200'
                                                        : 'bg-rose-50 text-[#E11D48] border border-rose-200'"
                                                >
                                                    <span
                                                        class="w-1.5 h-1.5 rounded-full"
                                                        :class="place.is_open ? 'bg-[#059669]' : 'bg-[#E11D48]'"
                                                    ></span>
                                                    <span x-text="place.is_open ? 'Buka' : 'Tutup'"></span>
                                                </span>
                                            </div>

                                            <!-- Nama Penyedia Makanan -->
                                            <h2
                                                class="text-sm font-bold text-slate-900 leading-snug line-clamp-1 mb-1"
                                                x-text="place.nama_penyedia"
                                            ></h2>

                                            <!-- Alamat Singkat -->
                                            <p
                                                class="text-xs text-slate-500 line-clamp-1 mb-1.5"
                                                :title="place.alamat"
                                                x-text="place.alamat"
                                            ></p>

                                            <!-- Jam Operasional & Rentang Harga -->
                                            <div class="flex flex-wrap items-center justify-between gap-y-1 text-xs">
                                                <span class="inline-flex items-center gap-1 text-slate-600 font-medium">
                                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span x-text="`${formatTime(place.jam_buka)} - ${formatTime(place.jam_tutup)}`"></span>
                                                </span>

                                                <span
                                                    class="font-bold text-slate-900"
                                                    x-text="formatPriceRange(place.min_price, place.max_price)"
                                                ></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Informasi Jarak (Muncul Otomatis Jika Lokasi Pengguna Aktif) -->
                                    <template x-if="place.distance_km !== null && place.distance_km !== undefined">
                                        <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                            <span class="inline-flex items-center gap-1.5 font-semibold text-[#1A73E8]">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span x-text="`${Number(place.distance_km).toFixed(2)} km dari lokasi Anda`"></span>
                                            </span>
                                            <span class="text-[11px] text-slate-400">Estimasi Haversine</span>
                                        </div>
                                    </template>

                                    <!-- Tombol Aksi Kartu: "Lihat Detail & Menu" dan "Rute" -->
                                    <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click.stop="openDetailModal(place.id_penyedia)"
                                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1A73E8] hover:bg-[#1557B0] text-white text-xs font-semibold transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            <span>Lihat Detail &amp; Menu</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click.stop="openRouteDirections(place.latitude, place.longitude)"
                                            class="inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 text-xs font-semibold transition"
                                            title="Buka Petunjuk Rute di Google Maps"
                                        >
                                            <svg class="w-3.5 h-3.5 text-[#1A73E8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                            </svg>
                                            <span>Rute</span>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </template>
            </div>
        </aside>

        <!-- PANEL KANAN: GOOGLE MAPS CANVAS -->
        <section class="flex-1 relative h-1/2 md:h-full bg-slate-200">
            <div
                id="map"
                role="region"
                aria-label="Peta Interaktif Penyedia Makanan Kawasan Soreang"
                class="w-full h-full"
            ></div>

            <!-- Legenda Ringkas di Sudut Kiri Bawah Peta -->
            <div class="hidden sm:flex items-center gap-3 bg-white/95 backdrop-blur-sm px-3.5 py-2 rounded-lg shadow-md border border-slate-200 absolute bottom-5 left-4 z-10 text-xs font-medium text-slate-700">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#059669]"></span>
                    <span>Sedang Buka</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#E11D48]"></span>
                    <span>Sedang Tutup</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#1A73E8]"></span>
                    <span>Lokasi Anda / Dipilih</span>
                </div>
            </div>
        </section>
    </main>

    <!-- =========================================================
         3. DRAWER / MODAL DETAIL PENYEDIA & MENU MAKANAN (ALPINE.JS)
         ========================================================= -->
    <div
        x-show="detailModal.isOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-hidden bg-slate-900/50 backdrop-blur-[1px] flex justify-end"
        role="dialog"
        aria-modal="true"
        aria-labelledby="drawer-detail-title"
    >
        <!-- Area Klik Luar untuk Menutup Drawer -->
        <div class="fixed inset-0" @click="closeDetailModal()"></div>

        <!-- Panel Drawer Kanan -->
        <div
            x-show="detailModal.isOpen"
            x-transition:enter="transform transition ease-out duration-250"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="relative w-full max-w-lg bg-white h-full shadow-2xl border-l border-slate-200 flex flex-col z-10 overflow-hidden"
        >
            <!-- Tombol Tutup Mengambang di Sudut Kanan Atas Banner -->
            <button
                type="button"
                @click="closeDetailModal()"
                aria-label="Tutup panel detail"
                class="absolute top-3.5 right-3.5 z-20 w-9 h-9 rounded-full bg-slate-900/65 hover:bg-slate-900 text-white flex items-center justify-center shadow-md transition"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <!-- Konten Drawer Scrollable -->
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                <!-- Banner Foto Header -->
                <div class="relative h-48 bg-gradient-to-br from-[#1E293B] via-slate-800 to-[#1A73E8] flex flex-col items-center justify-center text-white overflow-hidden">
                    <svg class="w-12 h-12 text-white/30 mb-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="text-xs font-medium text-white/70" x-text="detailModal.data?.nama_kategori || 'Penyedia Makanan'"></span>

                    <template x-if="detailModal.data && detailModal.data.foto">
                        <img
                            :src="getPhotoPath(detailModal.data.foto)"
                            :alt="detailModal.data.nama_penyedia"
                            @error="$event.target.style.display = 'none'"
                            class="absolute inset-0 w-full h-full object-cover"
                        >
                    </template>

                    <!-- Overlay Gradient Halus di Bawah Banner -->
                    <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-slate-950/75 to-transparent"></div>

                    <!-- Badge Kategori & Status di Atas Banner -->
                    <template x-if="detailModal.data">
                        <div class="absolute bottom-3 left-5 right-5 flex items-center justify-between gap-2">
                            <span
                                class="px-2.5 py-1 rounded-md text-xs font-semibold shadow-sm"
                                :class="getCategoryBadgeClass(detailModal.data.nama_kategori, detailModal.data.id_kategori)"
                                x-text="detailModal.data.nama_kategori"
                            ></span>

                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold shadow-sm"
                                :class="detailModal.data.is_open
                                    ? 'bg-[#059669] text-white'
                                    : 'bg-[#E11D48] text-white'"
                            >
                                <span class="w-2 h-2 rounded-full bg-white"></span>
                                <span x-text="detailModal.data.is_open ? 'Sedang Buka' : 'Sedang Tutup'"></span>
                            </span>
                        </div>
                    </template>
                </div>

                <!-- Body Informasi Detail -->
                <div class="p-5 space-y-5">
                    <template x-if="detailModal.data">
                        <div>
                            <h2
                                id="drawer-detail-title"
                                class="text-xl font-bold text-slate-900 leading-snug"
                                x-text="detailModal.data.nama_penyedia"
                            ></h2>

                            <!-- Jarak Jika Lokasi Pengguna Aktif -->
                            <template x-if="detailModal.data.distance_km !== null && detailModal.data.distance_km !== undefined">
                                <p class="mt-1 text-xs font-semibold text-[#1A73E8] flex items-center gap-1">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    <span x-text="`Berjarak ${Number(detailModal.data.distance_km).toFixed(2)} km dari lokasi Anda`"></span>
                                </p>
                            </template>

                            <!-- Kotak Informasi Lengkap (Alamat, Jam Operasional, Telepon, Koordinat) -->
                            <div class="mt-4 bg-[#F8FAFC] border border-slate-200 rounded-xl p-4 space-y-3 text-xs sm:text-sm">
                                <!-- Alamat Lengkap -->
                                <div class="flex items-start gap-3">
                                    <svg class="w-4 h-4 text-[#1A73E8] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs font-semibold text-slate-500">Alamat Lengkap</div>
                                        <div class="text-slate-800 font-medium leading-relaxed" x-text="detailModal.data.alamat"></div>
                                    </div>
                                </div>

                                <!-- Jam Operasional -->
                                <div class="flex items-start gap-3 pt-2.5 border-t border-slate-200/80">
                                    <svg class="w-4 h-4 text-[#1A73E8] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs font-semibold text-slate-500">Jam Operasional</div>
                                        <div class="text-slate-800 font-semibold">
                                            <span x-text="`${formatTime(detailModal.data.jam_buka)} - ${formatTime(detailModal.data.jam_tutup)} WIB`"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Nomor Telepon / Kontak -->
                                <div class="flex items-start gap-3 pt-2.5 border-t border-slate-200/80">
                                    <svg class="w-4 h-4 text-[#1A73E8] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="text-xs font-semibold text-slate-500">Nomor Telepon</div>
                                        <template x-if="detailModal.data.no_telepon">
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <a
                                                    :href="`tel:${detailModal.data.no_telepon}`"
                                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#1A73E8] hover:underline"
                                                    x-text="detailModal.data.no_telepon"
                                                ></a>
                                                <a
                                                    :href="getWhatsAppUrl(detailModal.data.no_telepon)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-[#059669] border border-emerald-200 text-[11px] font-semibold hover:bg-emerald-100 transition"
                                                >
                                                    <span>Hubungi via WhatsApp</span>
                                                </a>
                                            </div>
                                        </template>
                                        <template x-if="!detailModal.data.no_telepon">
                                            <span class="text-slate-400 text-xs">Tidak tersedia</span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Koordinat Spasial -->
                                <div class="flex items-start gap-3 pt-2.5 border-t border-slate-200/80">
                                    <svg class="w-4 h-4 text-[#1A73E8] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs font-semibold text-slate-500">Titik Koordinat (Lat, Lng)</div>
                                        <div
                                            class="font-mono text-xs text-slate-700"
                                            x-text="`${Number(detailModal.data.latitude).toFixed(6)}, ${Number(detailModal.data.longitude).toFixed(6)}`"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Bagian Daftar Menu Makanan -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Daftar Menu &amp; Harga</h3>
                            <template x-if="detailModal.data && detailModal.data.menus">
                                <span
                                    class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-[#1A73E8]"
                                    x-text="`${detailModal.data.menus.length} Menu`"
                                ></span>
                            </template>
                        </div>

                        <!-- Loading Menu -->
                        <template x-if="detailModal.isLoading">
                            <div class="space-y-2">
                                <template x-for="n in 3" :key="n">
                                    <div class="h-11 bg-slate-100 rounded-lg animate-pulse"></div>
                                </template>
                            </div>
                        </template>

                        <!-- Error Menu -->
                        <template x-if="!detailModal.isLoading && detailModal.error !== ''">
                            <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-[#E11D48]" x-text="detailModal.error"></div>
                        </template>

                        <!-- Tabel Daftar Menu -->
                        <template x-if="!detailModal.isLoading && detailModal.data && Array.isArray(detailModal.data.menus) && detailModal.data.menus.length > 0">
                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase">
                                            <th class="py-2.5 px-3.5">Nama Menu</th>
                                            <th class="py-2.5 px-3.5 text-right">Harga</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 text-xs sm:text-sm">
                                        <template x-for="menu in detailModal.data.menus" :key="menu.id_menu">
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-2.5 px-3.5 font-medium text-slate-800" x-text="menu.nama_menu"></td>
                                                <td class="py-2.5 px-3.5 text-right font-bold text-slate-900 whitespace-nowrap" x-text="formatRupiah(menu.harga)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- Kosong -->
                        <template x-if="!detailModal.isLoading && detailModal.data && (!detailModal.data.menus || detailModal.data.menus.length === 0)">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-center text-xs text-slate-500">
                                Belum ada data menu yang terdaftar untuk tempat makan ini.
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Footer Drawer: Tombol Petunjuk Rute Google Maps -->
            <template x-if="detailModal.data">
                <div class="p-4 bg-white border-t border-slate-200 flex items-center gap-3 shrink-0">
                    <button
                        type="button"
                        @click="openRouteDirections(detailModal.data.latitude, detailModal.data.longitude)"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#1A73E8] hover:bg-[#1557B0] text-white text-sm font-semibold shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <span>Petunjuk Rute Google Maps</span>
                    </button>

                    <button
                        type="button"
                        @click="closeDetailModal()"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold border border-slate-300 transition"
                    >
                        Tutup
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- =========================================================
         4. FLOATING TOAST NOTIFICATION
         ========================================================= -->
    <div
        x-show="toast.show"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-5 right-5 z-50 max-w-sm bg-white border border-slate-200 rounded-xl shadow-lg px-4 py-3 flex items-center gap-3"
        role="status"
        aria-live="polite"
    >
        <span
            class="w-2.5 h-2.5 rounded-full shrink-0"
            :class="{
                'bg-[#059669]': toast.type === 'success',
                'bg-[#E11D48]': toast.type === 'error',
                'bg-[#1A73E8]': toast.type === 'info'
            }"
        ></span>
        <p class="text-xs sm:text-sm font-medium text-slate-800" x-text="toast.message"></p>
    </div>

    <!-- Google Maps JavaScript API (Dimuat secara asinkron tanpa memblokir UI) -->
    <script async defer src="<?= htmlspecialchars($gmapsScriptUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
