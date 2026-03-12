-- ============================================================
-- DATABASE: inventaris_sarana
-- UKK RPL 2025/2026 - Paket 3
-- Aplikasi Inventaris Sarana dan Prasarana SMK
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `inventaris`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `inventaris`;

-- ──────────────────────────────────────────
-- Tabel: level
-- ──────────────────────────────────────────
CREATE TABLE `level` (
  `id_level`   INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_level` VARCHAR(25)  NOT NULL,
  PRIMARY KEY (`id_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `level` VALUES
(1, 'admin'),
(2, 'operator'),
(3, 'peminjam');

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_petugas
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_petugas` (
  `id_petugas`   INT(11)      NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(25)  NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `nama_petugas` VARCHAR(50)  NOT NULL,
  `id_level`     INT(11)      NOT NULL,
  PRIMARY KEY (`id_petugas`),
  KEY `id_level` (`id_level`),
  CONSTRAINT `fk_petugas_level`
    FOREIGN KEY (`id_level`) REFERENCES `level` (`id_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventaris_sarana_petugas` VALUES
(1, 'admin',    MD5('admin123'),    'Administrator',   1),
(2, 'operator', MD5('operator123'), 'Operator Gudang', 2);

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_pegawai
-- (digunakan sebagai tabel peminjam: guru, staff, siswa)
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_pegawai` (
  `id_pegawai`    INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_pegawai`  VARCHAR(50)  NOT NULL,
  `nip`           VARCHAR(30)  DEFAULT NULL,
  `alamat`        VARCHAR(100) DEFAULT NULL,
  `tipe_peminjam` ENUM('guru','staff','siswa') NOT NULL DEFAULT 'guru',
  `username`      VARCHAR(25)  DEFAULT NULL,
  `password`      VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_pegawai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventaris_sarana_pegawai` VALUES
(1, 'Budi Santoso',   '197801012005011001', 'Jl. Merdeka No. 1',  'guru',  'budi',  MD5('budi123')),
(2, 'Siti Rahayu',    '198503152010012002', 'Jl. Mawar No. 5',    'staff', 'siti',  MD5('siti123')),
(3, 'Ahmad Fauzi',    '0067/2024',          'Jl. Anggrek No. 10', 'siswa', 'ahmad', MD5('ahmad123'));

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_jenis
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_jenis` (
  `id_jenis`    INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_jenis`  VARCHAR(30)  NOT NULL,
  `kode_jenis`  INT(11)      NOT NULL,
  `keterangan`  VARCHAR(30)  DEFAULT NULL,
  PRIMARY KEY (`id_jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventaris_sarana_jenis` VALUES
(1, 'Elektronik',    101, 'Peralatan elektronik'),
(2, 'Furniture',     102, 'Meja, kursi, lemari'),
(3, 'Alat Tulis',    103, 'ATK dan perlengkapan tulis'),
(4, 'Olahraga',      104, 'Peralatan olahraga'),
(5, 'Laboratorium',  105, 'Alat lab & praktikum');

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_ruang
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_ruang` (
  `id_ruang`    INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_ruang`  VARCHAR(30)  NOT NULL,
  `kode_ruang`  INT(11)      NOT NULL,
  `keterangan`  VARCHAR(30)  DEFAULT NULL,
  PRIMARY KEY (`id_ruang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventaris_sarana_ruang` VALUES
(1, 'Lab Komputer 1',  201, 'Laboratorium komputer lantai 1'),
(2, 'Lab Komputer 2',  202, 'Laboratorium komputer lantai 2'),
(3, 'Ruang Guru',      203, 'Ruang guru dan staf'),
(4, 'Ruang Kelas A',   204, 'Kelas reguler A'),
(5, 'Perpustakaan',    205, 'Ruang baca & perpustakaan');

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_inventaris
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_inventaris` (
  `id_inventaris`    INT(11)      NOT NULL AUTO_INCREMENT,
  `nama`             VARCHAR(50)  NOT NULL,
  `kondisi`          VARCHAR(50)  DEFAULT NULL,
  `keterangan`       VARCHAR(30)  DEFAULT NULL,
  `jumlah`           INT(11)      NOT NULL DEFAULT 1,
  `jumlah_rusak`     INT(11)      NOT NULL DEFAULT 0,
  `id_jenis`         INT(11)      NOT NULL,
  `tanggal_register` DATE         NOT NULL,
  `id_ruang`         INT(11)      NOT NULL,
  `kode_inventaris`  INT(11)      NOT NULL,
  `id_petugas`       INT(11)      NOT NULL,
  PRIMARY KEY (`id_inventaris`),
  KEY `id_jenis`    (`id_jenis`),
  KEY `id_ruang`    (`id_ruang`),
  KEY `id_petugas`  (`id_petugas`),
  CONSTRAINT `fk_inv_jenis`    FOREIGN KEY (`id_jenis`)   REFERENCES `inventaris_sarana_jenis`   (`id_jenis`),
  CONSTRAINT `fk_inv_ruang`    FOREIGN KEY (`id_ruang`)   REFERENCES `inventaris_sarana_ruang`   (`id_ruang`),
  CONSTRAINT `fk_inv_petugas`  FOREIGN KEY (`id_petugas`) REFERENCES `inventaris_sarana_petugas` (`id_petugas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventaris_sarana_inventaris` VALUES
(1, 'Laptop Lenovo IdeaPad',  'Baik',        'Core i5',       10, 0, 1, '2024-01-15', 1, 1001, 2),
(2, 'Proyektor Epson EB-X05', 'Baik',        '3LCD XGA',       5, 0, 1, '2024-01-15', 1, 1002, 2),
(3, 'Meja Siswa',             'Baik',        'Kayu Jati',     40, 0, 2, '2023-07-10', 4, 1003, 2),
(4, 'Kursi Lipat',            'Baik',        'Rangka besi',   40, 0, 2, '2023-07-10', 4, 1004, 2),
(5, 'Printer Canon IP2870',   'Rusak Ringan','A4',             2, 1, 1, '2022-08-01', 3, 1005, 2),
(6, 'Lemari Arsip',           'Baik',        'Besi 4 pintu',   3, 0, 2, '2023-03-20', 3, 1006, 2),
(7, 'Bola Basket',            'Baik',        'Ukuran 7',       4, 0, 4, '2024-02-01', 3, 1007, 2),
(8, 'Mikroskop',              'Baik',        'Binokuler',      5, 0, 5, '2023-11-10', 1, 1008, 2);

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_peminjaman
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_peminjaman` (
  `id_peminjaman`     INT(11)      NOT NULL AUTO_INCREMENT,
  `tanggal_pinjam`    DATE         NOT NULL,
  `tanggal_kembali`   DATE         DEFAULT NULL,
  `status_peminjaman` VARCHAR(20)  NOT NULL DEFAULT 'menunggu',
  `id_pegawai`        INT(11)      NOT NULL,
  PRIMARY KEY (`id_peminjaman`),
  KEY `id_pegawai` (`id_pegawai`),
  CONSTRAINT `fk_pinjam_pegawai`
    FOREIGN KEY (`id_pegawai`) REFERENCES `inventaris_sarana_pegawai` (`id_pegawai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────
-- Tabel: inventaris_sarana_detail_pinjam
-- ──────────────────────────────────────────
CREATE TABLE `inventaris_sarana_detail_pinjam` (
  `id_detail_pinjam` INT(11) NOT NULL AUTO_INCREMENT,
  `id_inventaris`    INT(11) NOT NULL,
  `id_peminjaman`    INT(11) NOT NULL,
  `jumlah`           INT(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_detail_pinjam`),
  KEY `id_inventaris` (`id_inventaris`),
  KEY `id_peminjaman` (`id_peminjaman`),
  CONSTRAINT `fk_detail_inv`    FOREIGN KEY (`id_inventaris`) REFERENCES `inventaris_sarana_inventaris` (`id_inventaris`),
  CONSTRAINT `fk_detail_pinjam` FOREIGN KEY (`id_peminjaman`) REFERENCES `inventaris_sarana_peminjaman` (`id_peminjaman`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
