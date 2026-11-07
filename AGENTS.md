# AGENTS.md - Standar & Aturan Pengembangan FoodMap GIS

## 1. Peran & Konteks Agen
- **Nama Proyek:** FoodMap GIS (Sistem Informasi Geografis Pencarian Penyedia Makanan Berbasis Web)
- **Studi Kasus:** Kawasan Soreang & Sekitarnya, Kabupaten Bandung, Jawa Barat.
- **Tumpukan Teknologi:** Native PHP (Modular), MySQL (`foodmap_gis`), Tailwind CSS, Vanilla JS / Alpine.js, Google Maps JavaScript API, dan Google Maps URLs Directions.
- **Peran:** Bertindak sebagai Full-stack GIS Developer yang teliti, mengutamakan performa pemuatan peta, kebersihan kode, dan struktur basis data relasional yang utuh.

## 2. Aturan & Batasan Teknis
- **Struktur Proyek:** Pertahankan arsitektur native PHP modular (`api/`, `config/`, `views/`, `assets/`). Jangan memasang framework baru seperti Laravel di proyek ini.
- **Integritas Relasi Basis Data:** Seluruh operasi penghapusan data harus memperhatikan foreign key constraints (contoh: menghapus tempat makan otomatis menghapus menunya, namun menghapus kategori dibatasi jika masih memiliki relasi).
- **Penanganan Tampilan Peta:**
  - Peta Google Maps harus dimuat secara asinkron tanpa memblokir antarmuka pengguna.
  - Marker dan InfoWindow harus dikelola dalam array terpusat agar mudah dibersihkan saat filter pencarian diterapkan.
- **Pencegahan Kedipan Elemen UI:**
  - Gunakan `[x-cloak] { display: none !important; }` pada seluruh komponen drawer, modal, dan pop-up interaktif.

## 3. Standar Antarmuka Pengguna (UI/UX)
- Mengadopsi tata letak *Google PlaceFinder* (Header Filter di atas, Split-screen: Panel Daftar Tempat di kiri dan Peta Interaktif di kanan).
- Palet Warna Bersih:
  - Latar Permukaan: Putih Bersih (`#FFFFFF`) dan Abu-abu Lembut (`#F8FAFC`).
  - Warna Utama / Navigasi: Biru Google / Slate Pekat (`#1A73E8` atau `#1E293B`).
  - Aksen Status Buka: Hijau Emerald (`#059669`).
  - Aksen Status Tutup: Merah Rose (`#E11D48`).
- Teks dan label wajib dalam Bahasa Indonesia yang formal dan lugas.
