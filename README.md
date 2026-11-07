# FoodMap GIS 🗺️🍽️
> **Sistem Informasi Geografis Pencarian Penyedia Makanan Berbasis Web**  
> Studi Kasus: Kawasan Soreang dan Sekitarnya, Kabupaten Bandung, Jawa Barat.

Aplikasi **FoodMap GIS** merupakan implementasi Sistem Informasi Geografis (SIG) berbasis web yang mengintegrasikan data spasial (koordinat *latitude* dan *longitude*) dengan data nonspasial (kategori kuliner, profil tempat makan, jam operasional, serta daftar menu dan harga) menggunakan Google Maps JavaScript API dan basis data relasional MySQL.

---

## 📌 Identitas Mahasiswa & Tugas
- **Nama Mahasiswa:** Fajar Try Sutrasno
- **NPM:** 41155050210053
- **Kelas:** Teknik Informatika - C (Karyawan)
- **Mata Kuliah:** Sistem Informasi Geografis (SIG)
- **Dosen Pengampu:** Dr. Yiyi Supendi, S.Kom., M.T.
- **Institusi:** Fakultas Teknik, Universitas Langlangbuana (UNLA)

---

## 🚀 Fitur Utama Sistem

Aplikasi ini memenuhi seluruh **6 Fitur Wajib** dan fitur **Pengembangan Tambahan (Nilai Bonus)**:

### 1. Fitur Wajib
1. **Peta Digital Interaktif:** Memvisualisasikan sebaran 20+ penyedia makanan riil di Soreang menggunakan Google Maps JavaScript API lengkap dengan penanda (*marker*) kustom.
2. **Pengelolaan Data (Admin CRUD):** Panel administrasi lengkap untuk Tambah, Lihat, Edit, dan Hapus data Penyedia Makanan, Kategori, dan Menu Makanan. Dilengkapi *Interactive Mini Map Coordinate Picker* untuk memilih titik koordinat langsung di peta.
3. **Pencarian & Filter Multi-Kriteria:** Filter pencarian teks langsung (nama tempat/nama menu), filter kategori, status jam buka (*Open Now*), dan rentang harga menu.
4. **Detail Penyedia & Menu:** Modal/drawer informatif yang menampilkan nama tempat, jam operasional, nomor kontak, foto, status buka/tutup, serta daftar lengkap menu beserta harga satuannya.
5. **Petunjuk Rute Google Maps:** Tombol navigasi rute instan yang menghubungkan koordinat pengguna (*origin*) ke tempat makan (*destination*) menggunakan Google Maps Directions.
6. **Deteksi Lokasi Pengguna (Geolocation):** Mendeteksi posisi pengguna secara *real-time* via *HTML5 Geolocation API* dan memplot penanda biru di peta.

### 2. Fitur Nilai Tambah (Bonus)
- **Formula Haversine:** Perhitungan estimasi jarak keruangan garis lurus (*spatial distance*) di sisi server database.
- **Filter Radius Jarak:** Memfilter tempat makan dalam radius 1 km, 3 km, atau 5 km dari posisi pengguna.
- **5 Tempat Terdekat:** Menampilkan 5 rekomendasi tempat makan terdekat dari koordinat pengguna secara otomatis.

---

## 🛠️ Tumpukan Teknologi (Tech Stack)

- **Backend:** Native PHP (Arsitektur Modular & PDO Prepared Statements)
- **Database:** MySQL 8.0 (Relational Database `foodmap_gis` dengan Foreign Keys `RESTRICT` & `CASCADE`)
- **Frontend / Styling:** HTML5, Tailwind CSS, Alpine.js, & Vanilla JavaScript
- **Peta & Geospasial:** Google Maps JavaScript API, Google Maps URLs (Directions), HTML5 Geolocation API

---

## 🗄️ Skema Basis Data (`foodmap_gis`)

Sistem mengimplementasikan tiga tabel relasional:
1. `kategori_makanan`: Menyimpan jenis tempat makan (`id_kategori`, `nama_kategori`, `deskripsi`).
2. `penyedia_makanan`: Menyimpan atribut profil dan data spasial (`id_penyedia`, `id_kategori`, `nama_penyedia`, `alamat`, `latitude`, `longitude`, `jam_buka`, `jam_tutup`, `no_telepon`, `foto`).
3. `menu_makanan`: Menyimpan daftar hidangan dan harga (`id_menu`, `id_penyedia`, `nama_menu`, `harga`).

---

## 💻 Panduan Instalasi & Menjalankan Aplikasi

Aplikasi ini menggunakan Native PHP modular murni sehingga **tidak memerlukan build tools rumit (tanpa Composer / NPM)**.

### 1. Kloning atau Unduh Repositori
```bash
git clone https://github.com/jayenn11/foodmap-gis.git
cd foodmap-gis
```

### 2. Import Basis Data
1. Buka aplikasi manajemen MySQL (TablePlus, phpMyAdmin, DBeaver, atau Navicat).
2. Buat database baru bernama: `foodmap_gis`
3. Eksekusi / Import berkas SQL yang telah disediakan pada root proyek:
   ```text
   foodmap_gis.sql
   ```
   *(Berkas ini sudah memuat skema tabel lengkap beserta data seeder 20 penyedia makanan riil kawasan Soreang dan minimal 3 menu per tempat makan).*

### 3. Konfigurasi Koneksi Database
Buka berkas `config/database.php` dan sesuaikan kredensial koneksi MySQL lokal Anda jika diperlukan:
```php
$host = '127.0.0.1';
$db   = 'foodmap_gis';
$user = 'root';
$pass = ''; // Sesuaikan jika MySQL lokal menggunakan password
```

### 4. Jalankan Web Server Lokal
Jalankan server bawaan PHP melalui terminal di dalam folder proyek:
```bash
php -S localhost:8000
```

Buka peramban (browser) dan akses:
- **Halaman Utama (Peta & Pencarian):** [http://localhost:8000](http://localhost:8000)
- **Panel Admin CRUD:** [http://localhost:8000/views/admin.php](http://localhost:8000/views/admin.php) (atau klik tombol **"Kelola Data (Admin)"** di header utama).

---

## 🧪 Skenario Pengujian yang Dapat Dicoba

1. **Uji Filter Pencarian:** Ketikkan nama makanan (contoh: *"Goreng"*, *"Kopi"*, atau *"Bakso"*) pada kolom pencarian di header.
2. **Uji Geolokasi & Haversine:**
   - Klik tombol **"Lokasi Saya"** di header atas (izinkan peramban mengakses lokasi perangkat).
   - Pilih opsi filter jarak **"5 Terdekat"** atau radius **"Radius 3 km"**. Perhatikan jarak (km) yang muncul pada setiap kartu.
3. **Uji Navigasi Rute:** Klik tombol **"Rute"** pada salah satu kartu penyedia makanan. Sistem akan membuka Google Maps Directions menuju titik koordinat tujuan.
4. **Uji Coordinate Picker di Admin:**
   - Masuk ke panel admin, klik **"Tambah Tempat Makan Baru"**.
   - Klik atau geser penanda pada *mini map* di dalam form; nilai *Latitude* dan *Longitude* akan terisi otomatis secara presisi.
5. **Uji Relasi Menu:** Buka menu **"Kelola Menu"** pada salah satu tempat makan di panel admin untuk menambah atau mengedit hidangan beserta harganya.

---

## 📂 Struktur Berkas Proyek

```text
foodmap-gis/
├── api/
│   ├── categories.php          # Endpoint JSON daftar kategori
│   ├── places.php              # Endpoint JSON data tempat (Filter & Haversine)
│   ├── place-detail.php        # Endpoint JSON detail tempat & daftar menu
│   └── admin/                  # Endpoint CRUD API untuk panel admin
├── assets/                     # Berkas pendukung antarmuka (JS/CSS/Ikon)
├── config/
│   └── database.php            # Konfigurasi koneksi PDO MySQL & Timezone WIB
├── views/
│   └── admin.php               # Halaman Panel Administrator CRUD
├── AGENTS.md                   # Spesifikasi panduan pengembang & AI context
├── foodmap_gis.sql             # Skrip DDL & DML skema database dan data seeder
├── index.php                   # Tampilan utama PlaceFinder Split-Screen Layout
├── README.md                   # Dokumentasi instalasi dan panduan proyek
└── TECH_SPEC.md                # Spesifikasi teknis & rancangan REST API
```