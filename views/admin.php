<?php
declare(strict_types=1);

$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY') ?: ($_GET['gmaps_key'] ?? '');
$gmapsScriptUrl   = 'https://maps.googleapis.com/maps/api/js?callback=initAdminMap';
if ($googleMapsApiKey !== '') {
    $gmapsScriptUrl = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode((string) $googleMapsApiKey) . '&callback=initAdminMap';
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Kelola Data - FoodMap GIS</title>

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
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Admin CRUD & Coordinate Picker Controller -->
    <script src="../assets/js/crud.js"></script>

    <!-- Alpine.js v3 -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body
    x-data="foodMapAdminApp()"
    class="min-h-screen flex flex-col bg-[#F8FAFC] text-slate-900 antialiased"
>
    <!-- =========================================================
         1. HEADER PANEL ADMIN
         ========================================================= -->
    <header class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <!-- Brand & Judul (Seragam dengan Header Peta Utama) -->
            <a href="../index.php" class="flex items-center gap-2.5 shrink-0 group">
                <div class="w-9 h-9 rounded-lg bg-[#1A73E8] text-white flex items-center justify-center shadow-sm group-hover:bg-[#1557B0] transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 leading-tight">
                        Panel Kelola Data - FoodMap GIS
                    </h1>
                    <p class="text-xs text-slate-500 leading-tight">
                        Manajemen Penyedia Makanan, Menu &amp; Kategori Kawasan Soreang
                    </p>
                </div>
            </a>

            <!-- Tombol Navigasi & Aksi Utama -->
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="../index.php"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs sm:text-sm font-semibold transition"
                >
                    <span>&larr; Kembali ke Peta Utama</span>
                </a>

                <button
                    type="button"
                    @click="openCategoryModal()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 text-xs sm:text-sm font-semibold shadow-sm transition"
                >
                    <svg class="w-4 h-4 text-[#1A73E8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <span>Kelola Kategori</span>
                </button>

                <button
                    type="button"
                    @click="openCreatePlaceModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition-colors duration-200"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Tambah Tempat Makan Baru</span>
                </button>
            </div>
        </div>
    </header>

    <!-- =========================================================
         2. KONTEN UTAMA & TABEL DATA PENYEDIA MAKANAN
         ========================================================= -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
        <!-- Ringkasan Statistik Cepat -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Penyedia Makanan</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1" x-text="places.length">0</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-blue-50 text-[#1A73E8] flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kategori Makanan</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1" x-text="categories.length">0</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sedang Buka Saat Ini</p>
                    <p class="text-2xl font-bold text-[#059669] mt-1" x-text="places.filter(p => p.is_open).length">0</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#059669] flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Panel Tabel Penyedia Makanan -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <!-- Toolbar Pencarian Cepat & Filter Tabel -->
            <div class="p-4 bg-[#F8FAFC] border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900">Daftar Penyedia Makanan</h2>
                    <span
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#1A73E8] border border-blue-200"
                        x-text="`${filteredPlaces.length} Data`"
                    ></span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                    <!-- Input Pencarian Cepat (Client-side Filter) -->
                    <div class="relative flex-1 sm:w-64">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                            </svg>
                        </span>
                        <input
                            type="search"
                            x-model="searchQuery"
                            placeholder="Cari nama tempat, alamat, kategori..."
                            aria-label="Cari di tabel admin"
                            class="w-full pl-9 pr-3 py-1.5 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                        >
                    </div>

                    <!-- Filter Kategori di Tabel Admin -->
                    <div class="relative w-full sm:w-48">
                        <select
                            x-model="filterCategory"
                            aria-label="Filter kategori tabel"
                            class="w-full appearance-none py-1.5 pl-3.5 pr-10 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg text-slate-700 font-medium focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15 cursor-pointer truncate"
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
                </div>
            </div>

            <!-- Tabel Responsif -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100/80 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                            <th class="py-3 px-4 w-12 text-center">No</th>
                            <th class="py-3 px-4 min-w-[260px]">Foto / Nama Tempat</th>
                            <th class="py-3 px-4 min-w-[160px]">Kategori</th>
                            <th class="py-3 px-4 min-w-[150px]">Jam Operasional</th>
                            <th class="py-3 px-4 min-w-[175px]">Koordinat (Lat, Lng)</th>
                            <th class="py-3 px-4 text-center min-w-[120px]">Jumlah Menu</th>
                            <th class="py-3 px-4 text-center min-w-[230px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-xs sm:text-sm">
                        <!-- State Loading -->
                        <template x-if="isLoading">
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-500 font-medium">
                                    Memuat data penyedia makanan...
                                </td>
                            </tr>
                        </template>

                        <!-- State Kosong -->
                        <template x-if="!isLoading && filteredPlaces.length === 0">
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-500">
                                    Tidak ada data penyedia makanan yang sesuai dengan pencarian.
                                </td>
                            </tr>
                        </template>

                        <!-- Baris Data Penyedia Makanan (Terpaginasi 10 Data per Halaman) -->
                        <template x-for="(place, index) in paginatedPlaces" :key="place.id_penyedia">
                            <tr class="hover:bg-slate-50/90 transition">
                                <!-- No -->
                                <td class="py-3.5 px-4 text-center font-semibold text-slate-500" x-text="(currentPage - 1) * perPage + index + 1"></td>

                                <!-- Foto / Nama Tempat & Alamat -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 shrink-0 relative overflow-hidden flex items-center justify-center text-slate-400">
                                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
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
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 leading-snug" x-text="place.nama_penyedia"></div>
                                            <div class="text-xs text-slate-500 line-clamp-1 mt-0.5" x-text="place.alamat"></div>
                                            <template x-if="place.no_telepon">
                                                <div class="text-[11px] text-slate-400 mt-0.5" x-text="`Telp: ${place.no_telepon}`"></div>
                                            </template>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kategori dengan Variasi Warna Badge -->
                                <td class="py-3.5 px-4">
                                    <span
                                        class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold"
                                        :class="getCategoryBadgeClass(place.nama_kategori, place.id_kategori)"
                                        x-text="place.nama_kategori"
                                    ></span>
                                </td>

                                <!-- Jam Operasional -->
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800" x-text="`${formatTime(place.jam_buka)} - ${formatTime(place.jam_tutup)}`"></div>
                                    <span
                                        class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold"
                                        :class="place.is_open
                                            ? 'bg-emerald-50 text-[#059669] border border-emerald-200'
                                            : 'bg-rose-50 text-[#E11D48] border border-rose-200'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="place.is_open ? 'bg-[#059669]' : 'bg-[#E11D48]'"></span>
                                        <span x-text="place.is_open ? 'Buka' : 'Tutup'"></span>
                                    </span>
                                </td>

                                <!-- Koordinat (Lat, Lng) -->
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-700 whitespace-nowrap">
                                    <div x-text="`${Number(place.latitude).toFixed(6)},`"></div>
                                    <div x-text="Number(place.longitude).toFixed(6)"></div>
                                </td>

                                <!-- Jumlah Menu -->
                                <td class="py-3.5 px-4 text-center">
                                    <button
                                        type="button"
                                        @click="openManageMenuModal(place)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-[#1A73E8] hover:bg-blue-100 border border-blue-200 transition"
                                        title="Klik untuk mengelola menu"
                                    >
                                        <span x-text="`${place.menu_count || 0} Menu`"></span>
                                    </button>
                                </td>

                                <!-- Aksi: Kelola Menu, Edit, Hapus -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openManageMenuModal(place)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-[#059669] border border-emerald-200 text-xs font-semibold transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            <span>Kelola Menu</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="openEditPlaceModal(place)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#1A73E8] border border-blue-200 text-xs font-semibold transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <span>Edit</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="openDeletePlaceConfirm(place)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-[#E11D48] border border-rose-200 text-xs font-semibold transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Kontrol Paginasi Tabel (10 Data per Halaman) -->
            <div class="px-4 py-3.5 bg-[#F8FAFC] border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs sm:text-sm font-medium text-slate-600">
                    Menampilkan
                    <span class="font-bold text-slate-900" x-text="`${paginationStart}-${paginationEnd}`">0-0</span>
                    dari total
                    <span class="font-bold text-slate-900" x-text="filteredPlaces.length">0</span>
                    data
                </div>

                <div class="flex items-center gap-1.5">
                    <!-- Tombol Sebelumnya -->
                    <button
                        type="button"
                        @click="prevPage()"
                        :disabled="currentPage <= 1 || filteredPlaces.length === 0"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-semibold transition disabled:opacity-45 disabled:cursor-not-allowed"
                    >
                        <span>&larr; Sebelumnya</span>
                    </button>

                    <!-- Nomor Halaman -->
                    <template x-for="page in pageNumbers" :key="page">
                        <button
                            type="button"
                            @click="goToPage(page)"
                            class="w-8 h-8 rounded-lg text-xs sm:text-sm font-bold border transition flex items-center justify-center"
                            :class="Number(currentPage) === Number(page)
                                ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                                : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
                            x-text="page"
                        ></button>
                    </template>

                    <!-- Tombol Selanjutnya -->
                    <button
                        type="button"
                        @click="nextPage()"
                        :disabled="currentPage >= totalPages || filteredPlaces.length === 0"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-semibold transition disabled:opacity-45 disabled:cursor-not-allowed"
                    >
                        <span>Selanjutnya &rarr;</span>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- =========================================================
         3. MODAL FORM TAMBAH / EDIT TEMPAT MAKAN (+ MINI MAP PICKER)
         ========================================================= -->
    <div
        x-show="placeModal.isOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/55 backdrop-blur-[1px] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0" @click="closePlaceModal()"></div>

        <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 flex flex-col max-h-[92vh]">
            <!-- Header Modal -->
            <div class="px-6 py-4 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between shrink-0">
                <div>
                    <h3
                        class="text-base sm:text-lg font-bold text-slate-900"
                        x-text="placeModal.mode === 'edit' ? 'Edit Data Penyedia Makanan' : 'Tambah Tempat Makan Baru'"
                    ></h3>
                    <p class="text-xs text-slate-500">Lengkapi informasi tempat makan dan pilih titik koordinat pada peta di bawah.</p>
                </div>
                <button
                    type="button"
                    @click="closePlaceModal()"
                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body Form Modal -->
            <form @submit.prevent="submitPlaceForm()" class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-4">
                <!-- Pesan Error Validasi -->
                <template x-if="placeModal.error !== ''">
                    <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs sm:text-sm font-medium text-[#E11D48]" x-text="placeModal.error"></div>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Nama Tempat Makan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Tempat Makan <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <input
                            type="text"
                            x-model="placeModal.form.nama_penyedia"
                            placeholder="Contoh: Warung Nasi Ampera Soreang"
                            required
                            class="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                        >
                    </div>

                    <!-- Kategori Makanan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Kategori Makanan <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <div class="relative">
                            <select
                                x-model="placeModal.form.id_kategori"
                                required
                                class="w-full appearance-none py-2 pl-3.5 pr-10 text-sm bg-white border border-slate-300 rounded-lg text-slate-800 focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15 cursor-pointer"
                            >
                                <option value="">-- Pilih Kategori --</option>
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
                    </div>
                </div>

                <!-- Alamat Lengkap -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alamat Lengkap <span class="text-rose-500 font-bold">*</span>
                    </label>
                    <textarea
                        rows="2"
                        x-model="placeModal.form.alamat"
                        placeholder="Contoh: Jl. Raya Soreang - Banjaran No. 120, Cingcin, Soreang"
                        required
                        class="w-full px-3.5 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                    ></textarea>
                </div>

                <!-- Jam Buka, Jam Tutup, No Telepon, Nama Berkas/URL Foto -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jam Buka <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <input
                            type="time"
                            x-model="placeModal.form.jam_buka"
                            required
                            class="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jam Tutup <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <input
                            type="time"
                            x-model="placeModal.form.jam_tutup"
                            required
                            class="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            No. Telepon
                        </label>
                        <input
                            type="text"
                            x-model="placeModal.form.no_telepon"
                            @input="placeModal.form.no_telepon = placeModal.form.no_telepon.replace(/[^0-9+]/g, '')"
                            placeholder="081234567890"
                            class="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Berkas / URL Foto
                        </label>
                        <input
                            type="text"
                            x-model="placeModal.form.foto"
                            placeholder="ampera.jpg / https://..."
                            class="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                        >
                    </div>
                </div>

                <!-- Coordinate Picker Interaktif (Google Maps) -->
                <div class="bg-[#F8FAFC] border border-slate-200 rounded-xl p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Penentu Titik Koordinat Spasial (Coordinate Picker)
                            </h4>
                            <p class="text-[11px] text-slate-500">
                                Klik titik pada peta, geser marker biru, atau ketik angka Latitude &amp; Longitude secara manual.
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="useCurrentLocationForPicker()"
                            :disabled="placeModal.isLocating"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-blue-50 text-[#1A73E8] border border-blue-200 text-xs font-semibold shadow-sm transition"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                :class="placeModal.isLocating ? 'animate-spin' : ''"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.364-5.364l-1.414 1.414M8.05 15.95l-1.414 1.414m0-11.314l1.414 1.414m7.9 7.9l1.414 1.414M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-text="placeModal.isLocating ? 'Mendeteksi...' : 'Gunakan Lokasi Saya Saat Ini'"></span>
                        </button>
                    </div>

                    <!-- Kanvas Mini Map Google Maps -->
                    <div
                        id="admin-mini-map"
                        class="w-full h-60 rounded-xl border border-slate-300 bg-slate-200 overflow-hidden"
                    ></div>

                    <!-- Input Latitude & Longitude -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Latitude (Lintang) <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                x-model="placeModal.form.latitude"
                                @input="onManualCoordInput()"
                                placeholder="-7.03050000"
                                required
                                class="w-full px-3 py-2 text-sm font-mono bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Longitude (Bujur) <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                x-model="placeModal.form.longitude"
                                @input="onManualCoordInput()"
                                placeholder="107.53120000"
                                required
                                class="w-full px-3 py-2 text-sm font-mono bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/15"
                            >
                        </div>
                    </div>
                </div>

                <!-- Footer Tombol Simpan & Batal -->
                <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        @click="closePlaceModal()"
                        class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs sm:text-sm font-semibold transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="placeModal.isSubmitting"
                        class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg bg-[#1A73E8] hover:bg-[#1557B0] text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-60"
                    >
                        <span x-text="placeModal.isSubmitting ? 'Menyimpan...' : (placeModal.mode === 'edit' ? 'Simpan Perubahan' : 'Tambah Tempat Makan')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         4. MODAL KONFIRMASI HAPUS TEMPAT MAKAN
         ========================================================= -->
    <div
        x-show="deletePlaceModal.isOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/55 backdrop-blur-[1px] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0" @click="closeDeletePlaceConfirm()"></div>

        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 p-6 z-10">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-[#E11D48] border border-rose-200 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Penyedia Makanan</h3>
            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                Apakah Anda yakin ingin menghapus
                <strong class="text-slate-900" x-text="deletePlaceModal.place?.nama_penyedia"></strong>?
            </p>
            <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900">
                <strong>Peringatan Integritas Relasi:</strong> Menghapus tempat makan ini akan otomatis menghapus seluruh daftar menu makanan (<span x-text="deletePlaceModal.place?.menu_count || 0"></span> menu) yang terikat di dalamnya.
            </div>

            <div class="mt-5 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    @click="closeDeletePlaceConfirm()"
                    class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs sm:text-sm font-semibold transition"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="confirmDeletePlace()"
                    :disabled="deletePlaceModal.isSubmitting"
                    class="px-4 py-2 rounded-lg bg-[#E11D48] hover:bg-rose-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-60"
                >
                    <span x-text="deletePlaceModal.isSubmitting ? 'Menghapus...' : 'Ya, Hapus Permanen'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         5. MODAL KELOLA MENU MAKANAN
         ========================================================= -->
    <div
        x-show="menuModal.isOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/55 backdrop-blur-[1px] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0" @click="closeManageMenuModal()"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 flex flex-col max-h-[90vh]">
            <!-- Header Modal Menu -->
            <div class="px-6 py-4 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between shrink-0">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#1A73E8]">Kelola Daftar Menu</span>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900" x-text="menuModal.place?.nama_penyedia"></h3>
                </div>
                <button
                    type="button"
                    @click="closeManageMenuModal()"
                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body Modal Menu -->
            <div class="p-6 overflow-y-auto custom-scrollbar space-y-5 flex-1">
                <!-- Form Ringkas Tambah / Edit Menu -->
                <form @submit.prevent="submitMenuForm()" class="bg-[#F8FAFC] border border-slate-200 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4
                            class="text-xs font-bold text-slate-700 uppercase tracking-wider"
                            x-text="menuModal.formMode === 'edit' ? 'Edit Menu Makanan' : 'Tambah Menu Makanan Baru'"
                        ></h4>
                        <template x-if="menuModal.formMode === 'edit'">
                            <button
                                type="button"
                                @click="resetMenuForm()"
                                class="text-xs font-semibold text-slate-500 hover:text-slate-800 underline"
                            >
                                Batal Edit
                            </button>
                        </template>
                    </div>

                    <template x-if="menuModal.error !== ''">
                        <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs font-medium text-[#E11D48]" x-text="menuModal.error"></div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div class="sm:col-span-6">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">
                                Nama Menu <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <input
                                type="text"
                                x-model="menuModal.form.nama_menu"
                                placeholder="Contoh: Nasi Timbel Komplit"
                                required
                                class="w-full px-3 py-2 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8]"
                            >
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">
                                Harga (Rp) <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                step="500"
                                x-model="menuModal.form.harga"
                                placeholder="25000"
                                required
                                class="w-full px-3 py-2 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8]"
                            >
                        </div>

                        <div class="sm:col-span-3">
                            <button
                                type="submit"
                                :disabled="menuModal.isSubmitting"
                                class="w-full py-2 px-3 rounded-lg bg-[#1A73E8] hover:bg-[#1557B0] text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-60"
                            >
                                <span x-text="menuModal.formMode === 'edit' ? 'Simpan Menu' : '+ Tambah Menu'"></span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Tabel Daftar Menu -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase">
                                <th class="py-2.5 px-3.5 w-12 text-center">No</th>
                                <th class="py-2.5 px-3.5">Nama Menu Makanan</th>
                                <th class="py-2.5 px-3.5 text-right">Harga</th>
                                <th class="py-2.5 px-3.5 text-center w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs sm:text-sm">
                            <template x-if="menuModal.isLoading">
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-500">Memuat daftar menu...</td>
                                </tr>
                            </template>

                            <template x-if="!menuModal.isLoading && menuModal.menus.length === 0">
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-500">Belum ada menu untuk tempat makan ini.</td>
                                </tr>
                            </template>

                            <template x-for="(menu, idx) in menuModal.menus" :key="menu.id_menu">
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 px-3.5 text-center text-slate-500 font-medium" x-text="idx + 1"></td>
                                    <td class="py-2.5 px-3.5 font-semibold text-slate-800" x-text="menu.nama_menu"></td>
                                    <td class="py-2.5 px-3.5 text-right font-bold text-slate-900" x-text="formatRupiah(menu.harga)"></td>
                                    <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="startEditMenu(menu)"
                                            class="px-2.5 py-1 rounded bg-blue-50 hover:bg-blue-100 text-[#1A73E8] border border-blue-200 text-xs font-semibold mr-1 transition"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteMenuItem(menu)"
                                            class="px-2.5 py-1 rounded bg-rose-50 hover:bg-rose-100 text-[#E11D48] border border-rose-200 text-xs font-semibold transition"
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Modal Menu -->
            <div class="px-6 py-3.5 bg-[#F8FAFC] border-t border-slate-200 flex items-center justify-between shrink-0">
                <span class="text-xs font-semibold text-slate-500" x-text="`Total: ${menuModal.menus.length} menu terdaftar`"></span>
                <button
                    type="button"
                    @click="closeManageMenuModal()"
                    class="px-4 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs sm:text-sm font-semibold transition"
                >
                    Selesai &amp; Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         6. MODAL KELOLA KATEGORI MAKANAN
         ========================================================= -->
    <div
        x-show="categoryModal.isOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/55 backdrop-blur-[1px] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0" @click="closeCategoryModal()"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-10 flex flex-col max-h-[90vh]">
            <!-- Header Modal Kategori -->
            <div class="px-6 py-4 bg-[#F8FAFC] border-b border-slate-200 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900">Kelola Kategori Makanan</h3>
                    <p class="text-xs text-slate-500">Kategori yang masih terikat dengan tempat makan dilindungi dari penghapusan.</p>
                </div>
                <button
                    type="button"
                    @click="closeCategoryModal()"
                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body Modal Kategori -->
            <div class="p-6 overflow-y-auto custom-scrollbar space-y-5 flex-1">
                <!-- Form Tambah / Edit Kategori -->
                <form @submit.prevent="submitCategoryForm()" class="bg-[#F8FAFC] border border-slate-200 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4
                            class="text-xs font-bold text-slate-700 uppercase tracking-wider"
                            x-text="categoryModal.formMode === 'edit' ? 'Edit Kategori Makanan' : 'Tambah Kategori Baru'"
                        ></h4>
                        <template x-if="categoryModal.formMode === 'edit'">
                            <button
                                type="button"
                                @click="resetCategoryForm()"
                                class="text-xs font-semibold text-slate-500 hover:text-slate-800 underline"
                            >
                                Batal Edit
                            </button>
                        </template>
                    </div>

                    <!-- Pesan Peringatan / Error Proteksi Relasi -->
                    <template x-if="categoryModal.error !== ''">
                        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs font-medium text-[#E11D48] flex items-start gap-2">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="categoryModal.error"></span>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div class="sm:col-span-4">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Kategori <span class="text-rose-500 font-bold">*</span></label>
                            <input
                                type="text"
                                x-model="categoryModal.form.nama_kategori"
                                placeholder="Contoh: Roti & Bakery"
                                required
                                class="w-full px-3 py-2 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8]"
                            >
                        </div>

                        <div class="sm:col-span-5">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Deskripsi Singkat</label>
                            <input
                                type="text"
                                x-model="categoryModal.form.deskripsi"
                                placeholder="Aneka kue, roti panggang, dan pastry"
                                class="w-full px-3 py-2 text-xs sm:text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-[#1A73E8]"
                            >
                        </div>

                        <div class="sm:col-span-3">
                            <button
                                type="submit"
                                :disabled="categoryModal.isSubmitting"
                                class="w-full py-2 px-3 rounded-lg bg-[#1A73E8] hover:bg-[#1557B0] text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-60"
                            >
                                <span x-text="categoryModal.formMode === 'edit' ? 'Simpan' : '+ Tambah'"></span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Tabel Daftar Kategori -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase">
                                <th class="py-2.5 px-3.5 w-12 text-center">ID</th>
                                <th class="py-2.5 px-3.5">Nama Kategori &amp; Deskripsi</th>
                                <th class="py-2.5 px-3.5 text-center w-28">Terikat</th>
                                <th class="py-2.5 px-3.5 text-center w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-xs sm:text-sm">
                            <template x-for="cat in categories" :key="cat.id_kategori">
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 px-3.5 text-center font-mono text-xs text-slate-500" x-text="cat.id_kategori"></td>
                                    <td class="py-2.5 px-3.5">
                                        <div class="font-bold text-slate-900" x-text="cat.nama_kategori"></div>
                                        <div class="text-xs text-slate-500 mt-0.5" x-text="cat.deskripsi || '-'"></div>
                                    </td>
                                    <td class="py-2.5 px-3.5 text-center">
                                        <span
                                            class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                            :class="cat.place_count > 0 ? 'bg-blue-50 text-[#1A73E8]' : 'bg-slate-100 text-slate-500'"
                                            x-text="`${cat.place_count || 0} Tempat`"
                                        ></span>
                                    </td>
                                    <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="startEditCategory(cat)"
                                            class="px-2.5 py-1 rounded bg-blue-50 hover:bg-blue-100 text-[#1A73E8] border border-blue-200 text-xs font-semibold mr-1 transition"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteCategory(cat)"
                                            class="px-2.5 py-1 rounded bg-rose-50 hover:bg-rose-100 text-[#E11D48] border border-rose-200 text-xs font-semibold transition"
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Modal Kategori -->
            <div class="px-6 py-3.5 bg-[#F8FAFC] border-t border-slate-200 flex items-center justify-end shrink-0">
                <button
                    type="button"
                    @click="closeCategoryModal()"
                    class="px-4 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs sm:text-sm font-semibold transition"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         7. FLOATING TOAST NOTIFICATION
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
            :class="toast.type === 'error' ? 'bg-[#E11D48]' : 'bg-[#059669]'"
        ></span>
        <p class="text-xs sm:text-sm font-medium text-slate-800" x-text="toast.message"></p>
    </div>

    <!-- Google Maps JavaScript API untuk Coordinate Picker -->
    <script async defer src="<?= htmlspecialchars($gmapsScriptUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
