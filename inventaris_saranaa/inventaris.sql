-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 13, 2026 at 03:52 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventaris`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_detail_pinjam`
--

CREATE TABLE `inventaris_sarana_detail_pinjam` (
  `id_detail_pinjam` int NOT NULL,
  `id_inventaris` int NOT NULL,
  `id_peminjaman` int NOT NULL,
  `jumlah` int NOT NULL DEFAULT '1',
  `status_item` enum('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_detail_pinjam`
--

INSERT INTO `inventaris_sarana_detail_pinjam` (`id_detail_pinjam`, `id_inventaris`, `id_peminjaman`, `jumlah`, `status_item`) VALUES
(1, 9, 1, 1, 'menunggu'),
(2, 2, 2, 1, 'menunggu'),
(3, 1, 3, 1, 'menunggu'),
(5, 8, 5, 1, 'menunggu'),
(6, 3, 6, 1, 'menunggu'),
(7, 4, 6, 1, 'menunggu'),
(8, 6, 7, 2, 'disetujui'),
(9, 7, 7, 1, 'ditolak'),
(10, 7, 8, 1, 'disetujui'),
(11, 4, 8, 1, 'ditolak'),
(12, 7, 9, 1, 'disetujui'),
(13, 9, 9, 1, 'ditolak');

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_inventaris`
--

CREATE TABLE `inventaris_sarana_inventaris` (
  `id_inventaris` int NOT NULL,
  `nama` varchar(50) NOT NULL,
  `kondisi` varchar(50) DEFAULT NULL,
  `keterangan` varchar(30) DEFAULT NULL,
  `jumlah` int NOT NULL DEFAULT '1',
  `jumlah_rusak` int NOT NULL DEFAULT '0',
  `id_jenis` int NOT NULL,
  `tanggal_register` date NOT NULL,
  `id_ruang` int NOT NULL,
  `kode_inventaris` int NOT NULL,
  `id_petugas` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_inventaris`
--

INSERT INTO `inventaris_sarana_inventaris` (`id_inventaris`, `nama`, `kondisi`, `keterangan`, `jumlah`, `jumlah_rusak`, `id_jenis`, `tanggal_register`, `id_ruang`, `kode_inventaris`, `id_petugas`) VALUES
(1, 'Laptop Lenovo IdeaPad', 'Baik', 'Core i5', 10, 0, 1, '2024-01-15', 1, 1001, 2),
(2, 'Proyektor Epson EB-X05', 'Rusak', '3LCD XGA', 6, 0, 1, '2024-01-15', 1, 1002, 2),
(3, 'Meja Siswa', 'Baik', 'Kayu Jati', 39, 0, 2, '2023-07-10', 4, 1003, 2),
(4, 'Kursi Lipat', 'Baik', 'Rangka besi', 39, 0, 2, '2023-07-10', 4, 1004, 2),
(5, 'Printer Canon IP2870', 'Rusak Ringan', 'A4', 2, 0, 1, '2022-08-01', 3, 1005, 2),
(6, 'Lemari Arsip', 'Baik', 'Besi 4 pintu', 1, 0, 2, '2023-03-20', 3, 1006, 2),
(7, 'Bola Basket', 'Baik', 'Ukuran 7', 2, 0, 4, '2024-02-01', 3, 1007, 2),
(8, 'Mikroskop', 'Rusak Ringan', 'Binokuler', 5, 1, 5, '2023-11-10', 1, 1008, 2),
(9, 'PC', 'Rusak Ringan', 'enak', 5, 3, 1, '2026-03-10', 1, 1009, 2);

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_jenis`
--

CREATE TABLE `inventaris_sarana_jenis` (
  `id_jenis` int NOT NULL,
  `nama_jenis` varchar(30) NOT NULL,
  `kode_jenis` int NOT NULL,
  `keterangan` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_jenis`
--

INSERT INTO `inventaris_sarana_jenis` (`id_jenis`, `nama_jenis`, `kode_jenis`, `keterangan`) VALUES
(1, 'Elektronik', 101, 'Peralatan elektronik'),
(2, 'Furniture', 102, 'Meja, kursi, lemari'),
(3, 'Alat Tulis', 103, 'ATK dan perlengkapan tulis'),
(4, 'Olahraga', 104, 'Peralatan olahraga'),
(5, 'Laboratorium', 105, 'Alat lab & praktikum');

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_pegawai`
--

CREATE TABLE `inventaris_sarana_pegawai` (
  `id_pegawai` int NOT NULL,
  `nama_pegawai` varchar(50) NOT NULL,
  `nip` varchar(25) NOT NULL,
  `alamat` varchar(50) DEFAULT NULL,
  `username` varchar(25) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `tipe_peminjam` enum('guru','staff','siswa') DEFAULT 'guru'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_pegawai`
--

INSERT INTO `inventaris_sarana_pegawai` (`id_pegawai`, `nama_pegawai`, `nip`, `alamat`, `username`, `password`, `tipe_peminjam`) VALUES
(1, 'Budi Santoso', '2147483647', 'Jl. Merdeka No. 1', 'budi', '9c5fa085ce256c7c598f6710584ab25d', 'staff'),
(2, 'Siti Rahayu', '2147483647', 'Jl. Mawar No. 5', 'siti', '5c2e4a2563f9f4427955422fe1402762', 'siswa'),
(3, 'Ahmad Fauzi', '2147483647', 'Jl. Anggrek No. 10', 'ahmad', '8de13959395270bf9d6819f818ab1a00', 'guru'),
(4, 'Noviati indah', '21474982645', 'Kota Batu', 'novia', 'af2f690d195131f75627ff2b354a9daf', 'guru');

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_peminjaman`
--

CREATE TABLE `inventaris_sarana_peminjaman` (
  `id_peminjaman` int NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `status_peminjaman` varchar(20) NOT NULL DEFAULT 'menunggu',
  `id_pegawai` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_peminjaman`
--

INSERT INTO `inventaris_sarana_peminjaman` (`id_peminjaman`, `tanggal_pinjam`, `tanggal_kembali`, `status_peminjaman`, `id_pegawai`) VALUES
(1, '2026-03-10', '2026-03-10', 'dikembalikan', 1),
(2, '2026-03-10', '2026-03-11', 'dikembalikan', 4),
(3, '2026-03-10', '2026-03-11', 'terlambat', 3),
(5, '2026-03-11', '2026-03-11', 'dikembalikan', 1),
(6, '2026-03-12', '2026-03-13', 'dipinjam', 3),
(7, '2026-03-12', '2026-03-13', 'dipinjam', 1),
(8, '2026-03-12', '2026-03-13', 'dipinjam', 2),
(9, '2026-03-12', '2026-03-13', 'dipinjam', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_petugas`
--

CREATE TABLE `inventaris_sarana_petugas` (
  `id_petugas` int NOT NULL,
  `username` varchar(25) NOT NULL,
  `password` varchar(225) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nama_petugas` varchar(50) NOT NULL,
  `id_level` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_petugas`
--

INSERT INTO `inventaris_sarana_petugas` (`id_petugas`, `username`, `password`, `nama_petugas`, `id_level`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'Administrator', 1),
(2, 'operator', '2407bd807d6ca01d1bcd766c730cec9a', 'Operator', 2);

-- --------------------------------------------------------

--
-- Table structure for table `inventaris_sarana_ruang`
--

CREATE TABLE `inventaris_sarana_ruang` (
  `id_ruang` int NOT NULL,
  `nama_ruang` varchar(30) NOT NULL,
  `kode_ruang` int NOT NULL,
  `keterangan` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventaris_sarana_ruang`
--

INSERT INTO `inventaris_sarana_ruang` (`id_ruang`, `nama_ruang`, `kode_ruang`, `keterangan`) VALUES
(1, 'Lab Komputer 1', 201, 'Laboratorium komputer lantai 1'),
(2, 'Lab Komputer 2', 202, 'Laboratorium komputer lantai 2'),
(3, 'Ruang Guru', 203, 'Ruang guru dan staf'),
(4, 'Ruang Kelas A', 204, 'Kelas reguler A'),
(5, 'Perpustakaan', 205, 'Ruang baca & perpustakaan');

-- --------------------------------------------------------

--
-- Table structure for table `level`
--

CREATE TABLE `level` (
  `id_level` int NOT NULL,
  `nama_level` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `level`
--

INSERT INTO `level` (`id_level`, `nama_level`) VALUES
(1, 'admin'),
(2, 'operator'),
(3, 'pegawai');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventaris_sarana_detail_pinjam`
--
ALTER TABLE `inventaris_sarana_detail_pinjam`
  ADD PRIMARY KEY (`id_detail_pinjam`),
  ADD KEY `id_inventaris` (`id_inventaris`),
  ADD KEY `id_peminjaman` (`id_peminjaman`);

--
-- Indexes for table `inventaris_sarana_inventaris`
--
ALTER TABLE `inventaris_sarana_inventaris`
  ADD PRIMARY KEY (`id_inventaris`),
  ADD KEY `id_jenis` (`id_jenis`),
  ADD KEY `id_ruang` (`id_ruang`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `inventaris_sarana_jenis`
--
ALTER TABLE `inventaris_sarana_jenis`
  ADD PRIMARY KEY (`id_jenis`);

--
-- Indexes for table `inventaris_sarana_pegawai`
--
ALTER TABLE `inventaris_sarana_pegawai`
  ADD PRIMARY KEY (`id_pegawai`);

--
-- Indexes for table `inventaris_sarana_peminjaman`
--
ALTER TABLE `inventaris_sarana_peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `id_pegawai` (`id_pegawai`);

--
-- Indexes for table `inventaris_sarana_petugas`
--
ALTER TABLE `inventaris_sarana_petugas`
  ADD PRIMARY KEY (`id_petugas`),
  ADD KEY `id_level` (`id_level`);

--
-- Indexes for table `inventaris_sarana_ruang`
--
ALTER TABLE `inventaris_sarana_ruang`
  ADD PRIMARY KEY (`id_ruang`);

--
-- Indexes for table `level`
--
ALTER TABLE `level`
  ADD PRIMARY KEY (`id_level`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventaris_sarana_detail_pinjam`
--
ALTER TABLE `inventaris_sarana_detail_pinjam`
  MODIFY `id_detail_pinjam` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `inventaris_sarana_inventaris`
--
ALTER TABLE `inventaris_sarana_inventaris`
  MODIFY `id_inventaris` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventaris_sarana_jenis`
--
ALTER TABLE `inventaris_sarana_jenis`
  MODIFY `id_jenis` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventaris_sarana_pegawai`
--
ALTER TABLE `inventaris_sarana_pegawai`
  MODIFY `id_pegawai` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventaris_sarana_peminjaman`
--
ALTER TABLE `inventaris_sarana_peminjaman`
  MODIFY `id_peminjaman` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventaris_sarana_petugas`
--
ALTER TABLE `inventaris_sarana_petugas`
  MODIFY `id_petugas` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventaris_sarana_ruang`
--
ALTER TABLE `inventaris_sarana_ruang`
  MODIFY `id_ruang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `level`
--
ALTER TABLE `level`
  MODIFY `id_level` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventaris_sarana_detail_pinjam`
--
ALTER TABLE `inventaris_sarana_detail_pinjam`
  ADD CONSTRAINT `fk_detail_inv` FOREIGN KEY (`id_inventaris`) REFERENCES `inventaris_sarana_inventaris` (`id_inventaris`),
  ADD CONSTRAINT `fk_detail_pinjam` FOREIGN KEY (`id_peminjaman`) REFERENCES `inventaris_sarana_peminjaman` (`id_peminjaman`);

--
-- Constraints for table `inventaris_sarana_inventaris`
--
ALTER TABLE `inventaris_sarana_inventaris`
  ADD CONSTRAINT `fk_inv_jenis` FOREIGN KEY (`id_jenis`) REFERENCES `inventaris_sarana_jenis` (`id_jenis`),
  ADD CONSTRAINT `fk_inv_petugas` FOREIGN KEY (`id_petugas`) REFERENCES `inventaris_sarana_petugas` (`id_petugas`),
  ADD CONSTRAINT `fk_inv_ruang` FOREIGN KEY (`id_ruang`) REFERENCES `inventaris_sarana_ruang` (`id_ruang`);

--
-- Constraints for table `inventaris_sarana_peminjaman`
--
ALTER TABLE `inventaris_sarana_peminjaman`
  ADD CONSTRAINT `fk_pinjam_pegawai` FOREIGN KEY (`id_pegawai`) REFERENCES `inventaris_sarana_pegawai` (`id_pegawai`);

--
-- Constraints for table `inventaris_sarana_petugas`
--
ALTER TABLE `inventaris_sarana_petugas`
  ADD CONSTRAINT `fk_petugas_level` FOREIGN KEY (`id_level`) REFERENCES `level` (`id_level`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
