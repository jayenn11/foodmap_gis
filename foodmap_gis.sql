-- ========================================================
-- FOODMAP GIS DATABASE SCHEMA & SEEDER
-- Area: Soreang & Sekitarnya, Kabupaten Bandung
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS menu_makanan;
DROP TABLE IF EXISTS penyedia_makanan;
DROP TABLE IF EXISTS kategori_makanan;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Tabel kategori_makanan
CREATE TABLE kategori_makanan (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel penyedia_makanan (Data Spasial & Non-Spasial)
CREATE TABLE penyedia_makanan (
    id_penyedia INT AUTO_INCREMENT PRIMARY KEY,
    id_kategori INT NOT NULL,
    nama_penyedia VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    jam_buka TIME NOT NULL,
    jam_tutup TIME NOT NULL,
    no_telepon VARCHAR(20),
    foto VARCHAR(255),
    FOREIGN KEY (id_kategori) REFERENCES kategori_makanan(id_kategori) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel menu_makanan
CREATE TABLE menu_makanan (
    id_menu INT AUTO_INCREMENT PRIMARY KEY,
    id_penyedia INT NOT NULL,
    nama_menu VARCHAR(100) NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_penyedia) REFERENCES penyedia_makanan(id_penyedia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- DATA KATEGORI
-- ========================================================
INSERT INTO kategori_makanan (id_kategori, nama_kategori, deskripsi) VALUES
(1, 'Rumah Makan Tradisional & Sunda', 'Masakan khas Sunda, nasi timbel, sambal dadak, dan olahan ayam/ikan lokal'),
(2, 'Kafe & Kopi', 'Tempat kopi santai dengan kudapan ringan, kopi susu kekinian, dan suasana modern'),
(3, 'Restoran Cepat Saji & Modern', 'Hidangan ayam goreng renyah, burger, olahan cepat saji ramah keluarga'),
(4, 'Bakso, Mie & Soto', 'Aneka hidangan kuah hangat Nusantara, bakso urat, mie yamin, dan soto'),
(5, 'Seafood & Bakaran', 'Olahan aneka ikan air tawar, hidangan laut, cumi, dan udang bumbu bakar');

-- ========================================================
-- DATA 20 PENYEDIA MAKANAN (AREA SOREANG, KABUPATEN BANDUNG)
-- ========================================================
INSERT INTO penyedia_makanan (id_penyedia, id_kategori, nama_penyedia, alamat, latitude, longitude, jam_buka, jam_tutup, no_telepon, foto) VALUES
(1, 1, 'Warung Nasi Ampera Soreang', 'Jl. Raya Soreang - Banjaran No. 120, Cingcin, Soreang', -7.03215000, 107.52840000, '08:00:00', '21:30:00', '081234567801', 'ampera.jpg'),
(2, 1, 'Rumah Makan Sindang Reret Soreang', 'Jl. Raya Soreang No. 25, Pamekaran, Soreang', -7.02980000, 107.52560000, '09:00:00', '21:00:00', '081234567802', 'sindang_reret.jpg'),
(3, 2, 'Kopi Nako Soreang', 'Jl. Al-Fathu No. 88, Pamekaran, Soreang', -7.03050000, 107.53120000, '08:00:00', '23:00:00', '081234567803', 'kopi_nako.jpg'),
(4, 2, 'Armor Kopi Sadu', 'Jl. Raya Soreang - Ciwidey No. 15, Sadu, Soreang', -7.04560000, 107.51890000, '09:00:00', '22:00:00', '081234567804', 'armor_kopi.jpg'),
(5, 4, 'Mie Gacoan Soreang', 'Jl. Raya Soreang No. 340, Panyirapan, Soreang', -7.03320000, 107.53450000, '09:00:00', '23:00:00', '081234567805', 'gacoan.jpg'),
(6, 4, 'Bakso Mas Eko Maju Soreang', 'Jl. Gading Tutuka 1 No. 45, Cingcin, Soreang', -7.02450000, 107.53320000, '10:00:00', '21:00:00', '081234567806', 'bakso_eko.jpg'),
(7, 3, 'Richeese Factory Soreang', 'Jl. Al-Fathu, Pamekaran, Soreang', -7.03120000, 107.53050000, '10:00:00', '22:00:00', '081234567807', 'richeese.jpg'),
(8, 3, 'McDonald\'s Soreang', 'Jl. Tol Soreang - Pasir Koja KM 1, Cingcin, Soreang', -7.02150000, 107.53780000, '06:00:00', '23:59:00', '081234567808', 'mcd.jpg'),
(9, 1, 'Saung Soreang Resto & Cafe', 'Jl. Al-Fathu No. 10, Pamekaran, Soreang', -7.02890000, 107.52980000, '09:00:00', '21:00:00', '081234567809', 'saung_soreang.jpg'),
(10, 5, 'D\'Riam Riverside Culinary', 'Jl. Raya Soreang - Ciwidey KM 25, Cukanggenteng, Soreang', -7.06890000, 107.49850000, '09:00:00', '20:30:00', '081234567810', 'driam.jpg'),
(11, 4, 'Soto Kudus Menara Soreang', 'Jl. Raya Kopo - Soreang No. 89, Katapang', -7.01250000, 107.54560000, '07:00:00', '19:00:00', '081234567811', 'soto_kudus.jpg'),
(12, 1, 'RM Ibu Haji Ciganea Soreang', 'Jl. Terusan Kopo - Soreang KM 14, Cingcin, Soreang', -7.02340000, 107.53500000, '08:00:00', '21:00:00', '081234567812', 'ciganea.jpg'),
(13, 2, 'Fore Coffee Soreang Drive Thru', 'Jl. Gading Tutuka 2 Blok A No. 3, Cingcin, Soreang', -7.02280000, 107.53200000, '07:00:00', '22:00:00', '081234567813', 'fore.jpg'),
(14, 5, 'RM Ikan Bakar Danau Soreang', 'Jl. Gading Tutuka No. 18, Cingcin, Soreang', -7.02610000, 107.53100000, '10:00:00', '21:00:00', '081234567814', 'ikan_bakar.jpg'),
(15, 4, 'Baso Solo Samrat Gading Tutuka', 'Jl. Gading Tutuka 1 No. 12, Cingcin, Soreang', -7.02500000, 107.53250000, '10:00:00', '21:30:00', '081234567815', 'baso_samrat.jpg'),
(16, 2, 'Point Coffee Indomaret Fresh Al-Fathu', 'Jl. Al-Fathu No. 45, Pamekaran, Soreang', -7.03010000, 107.53020000, '07:00:00', '22:00:00', '081234567816', 'point_coffee.jpg'),
(17, 3, 'HokBen Soreang', 'Jl. Terusan Soreang Cipatik No. 20, Cingcin, Soreang', -7.02050000, 107.53650000, '09:00:00', '22:00:00', '081234567817', 'hokben.jpg'),
(18, 1, 'Kupat Tahu Cingcin Putra Soreang', 'Jl. Raya Soreang - Banjaran No. 5, Soreang', -7.03150000, 107.52700000, '06:00:00', '13:00:00', '081234567818', 'kupat_tahu.jpg'),
(19, 5, 'Seafood Kiloan Bang Bopak Soreang', 'Jl. Raya Gading Tutuka 2 No. 7, Cingcin, Soreang', -7.02180000, 107.53150000, '15:00:00', '23:00:00', '081234567819', 'bopak.jpg'),
(20, 2, 'Janji Jiwa & Jiwa Toast Soreang', 'Jl. Raya Soreang No. 78, Soreang', -7.03280000, 107.52900000, '08:00:00', '21:00:00', '081234567820', 'janji_jiwa.jpg');

-- ========================================================
-- DATA MENU MAKANAN (MINIMAL 3 MENU PER TEMPAT)
-- ========================================================
INSERT INTO menu_makanan (id_penyedia, nama_menu, harga) VALUES
(1, 'Ayam Bakar Khas Ampera', 24000.00), (1, 'Nasi Timbel Komplit', 32000.00), (1, 'Sayur Asem Gurih', 10000.00),
(2, 'Gurame Terbang Sindang Reret', 75000.00), (2, 'Karedok Leunca', 18000.00), (2, 'Es Kelapa Jeruk Segar', 15000.00),
(3, 'Es Kopi Nako', 21000.00), (3, 'Croissant Cokelat Almond', 25000.00), (3, 'Nasi Goreng Merem Melek', 35000.00),
(4, 'Kopi Tubruk Robusta Sadu', 18000.00), (4, 'Pisang Goreng Keju Aren', 20000.00), (4, 'Cireng Bumbu Rujak', 16000.00),
(5, 'Mie Gacoan Level 3', 13000.00), (5, 'Dimsum Udang Keju', 12000.00), (5, 'Es Gobak Sodor', 11000.00),
(6, 'Bakso Urat Spesial Mas Eko', 22000.00), (6, 'Mie Yamin Manis Bakso', 24000.00), (6, 'Es Campur Oyen', 12000.00),
(7, 'Fire Chicken Combo Level 3', 45000.00), (7, 'Pink Lava Float', 14000.00), (7, 'Fire Wings 4 Pcs', 30000.00),
(8, 'PaNas Spesial Ayam Krispy', 42000.00), (8, 'Big Mac Burger', 43000.00), (8, 'McFlurry Oreo', 14000.00),
(9, 'Ayam Kampung Goreng Saung', 28000.00), (9, 'Pecel Gurame Saung Soreang', 68000.00), (9, 'Sambal Dadak Khas Sunda', 7000.00),
(10, 'Iga Bakar Madu Riverside', 65000.00), (10, 'Sop Buntut Gurih Rempah', 60000.00), (10, 'Mojito Mint Ice Segar', 22000.00),
(11, 'Soto Kudus Mangkuk Campur', 18000.00), (11, 'Sate Paru Goreng Gurih', 12000.00), (11, 'Perkedel Kentang Hangat', 5000.00),
(12, 'Ayam Goreng Basah Ciganea', 26000.00), (12, 'Pepes Ikan Mas Duri Lunak', 25000.00), (12, 'Lalapan Sambal Terasi Dadak', 8000.00),
(13, 'Butterscotch Sea Salt Latte', 29000.00), (13, 'Aren Latte Fore Signature', 24000.00), (13, 'Cinnamon Roll Warm', 22000.00),
(14, 'Nila Bakar Bumbu Cobek', 38000.00), (14, 'Cah Kangkung Belacan', 16000.00), (14, 'Es Jeruk Nipis Madu', 12000.00),
(15, 'Bakso Tenis Rusuk Samrat', 38000.00), (15, 'Pangsit Goreng Renyah', 8000.00), (15, 'Es Pisang Ijo Khas Makassar', 18000.00),
(16, 'Caffe Dolce Iced', 22000.00), (16, 'Matcha Green Tea Frappe', 25000.00), (16, 'Brown Sugar Fresh Milk', 20000.00),
(17, 'Simple Set Teriyaki 1', 42000.00), (17, 'Bento Special 1 Lengkap', 58000.00), (17, 'Shrimp Roll Isi 3', 25000.00),
(18, 'Kupat Tahu Bumbu Kacang Padat', 15000.00), (18, 'Kupat Tahu Telur Rebus', 18000.00), (18, 'Teh Manis Melati Hangat', 4000.00),
(19, 'Paket Seafood Saus Padang Spesial', 85000.00), (19, 'Kerang Dara Saus Tiram', 35000.00), (19, 'Cumi Goreng Tepung Crispy', 38000.00),
(20, 'Kopi Janji Jiwa Mantan', 20000.00), (20, 'Toast Crispy Chicken Mentai', 28000.00), (20, 'Earl Grey Tea Iced', 16000.00);
