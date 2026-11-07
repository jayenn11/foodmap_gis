# TECH_SPEC.md - Cetak Biru Teknis FoodMap GIS

## 1. Arsitektur Basis Data (MySQL: `foodmap_gis`)

### Tabel 1: `kategori_makanan`
- `id_kategori` (INT, PK, Auto Increment)
- `nama_kategori` (VARCHAR 100, Not Null)
- `deskripsi` (TEXT, Nullable)

### Tabel 2: `penyedia_makanan` (Data Spasial & Non-Spasial)
- `id_penyedia` (INT, PK, Auto Increment)
- `id_kategori` (INT, FK -> `kategori_makanan.id_kategori`, On Delete Restrict)
- `nama_penyedia` (VARCHAR 100, Not Null)
- `alamat` (TEXT, Not Null)
- `latitude` (DECIMAL 10,8, Not Null)
- `longitude` (DECIMAL 11,8, Not Null)
- `jam_buka` (TIME, Not Null)
- `jam_tutup` (TIME, Not Null)
- `no_telepon` (VARCHAR 20, Nullable)
- `foto` (VARCHAR 255, Nullable)

### Tabel 3: `menu_makanan`
- `id_menu` (INT, PK, Auto Increment)
- `id_penyedia` (INT, FK -> `penyedia_makanan.id_penyedia`, On Delete Cascade)
- `nama_menu` (VARCHAR 100, Not Null)
- `harga` (DECIMAL 12,2, Not Null)

---

## 2. Spesifikasi Endpoint API (JSON)

### GET `/api/places.php`
- **Fungsi:** Mengambil daftar tempat makan untuk ditampilkan pada sidebar dan peta.
- **Parameter Kueri:**
  - `search` (string): Pencarian nama tempat makan atau nama menu.
  - `kategori` (int): ID kategori penyedia makanan.
  - `open_now` (boolean): Filter tempat makan yang sedang buka pada waktu server saat ini.
  - `min_price` & `max_price` (numeric): Rentang harga makanan.
  - `user_lat` & `user_lng` (decimal): Koordinat lokasi pengguna dari browser.
  - `radius` (numeric: 1, 3, 5 km)
  - `limit_nearest` (boolean: true untuk membatasi 5 tempat terdekat)
- **Respons JSON:**
  - Objek penyedia makanan lengkap dengan koordinat spasial, kategori, rentang harga (`min_harga`, `max_harga`), status operasional (`is_open`), daftar menu, serta estimasi jarak (`distance_km`) menggunakan kalkulasi Formula Haversine.

### GET `/api/place-detail.php?id={id_penyedia}`
- **Fungsi:** Mengambil detail satu penyedia makanan beserta seluruh daftar menu untuk drawer/modal detail.

### POST/PUT/DELETE `/api/admin/...`
- **Fungsi:** Operasi CRUD data tempat makan dan menu untuk panel administrator.

---

## 3. Spesifikasi Antarmuka (PlaceFinder Split-Screen Layout)
1. **Header Navigasi & Filter:**
   - Input pencarian teks (nama tempat/menu).
   - Dropdown Kategori.
   - Dropdown Rentang Harga.
   - Checkbox "Buka Sekarang".
   - Tombol "Lokasi Saya" untuk mendeteksi koordinat lintang & bujur pengguna via HTML5 Geolocation API.
2. **Panel Kiri (Sidebar List):**
   - Menampilkan kartu tempat makan (thumbnail foto, nama, badge kategori, jam operasional, jarak dari lokasi user, dan rentang harga).
   - Sinkronisasi: Saat kartu diklik, peta bergeser (panTo) ke marker tempat tersebut.
3. **Panel Kanan (Google Maps Canvas):**
   - Menampilkan marker tempat makan (ikon restoran/kafe) dan marker lokasi user (ikon titik biru).
   - Klik marker akan memicu pembukaan InfoWindow dan drawer detail di sidebar.
4. **Navigasi Rute:**
   - Tombol "Petunjuk Rute" yang membuka tab baru ke Google Maps Directions:
     `https://www.google.com/maps/dir/?api=1&origin={user_lat},{user_lng}&destination={dest_lat},{dest_lng}`
