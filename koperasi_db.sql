-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Mar 2026 pada 15.36
-- Versi server: 10.1.38-MariaDB
-- Versi PHP: 7.3.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `koperasi_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `kode_anggota` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `alamat` text,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `pekerjaan` varchar(50) DEFAULT NULL,
  `ktp` varchar(20) DEFAULT NULL,
  `foto_ktp` varchar(255) DEFAULT NULL,
  `rekening_bank` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `tanggal_daftar` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `anggota`
--

INSERT INTO `anggota` (`id`, `kode_anggota`, `nama_lengkap`, `alamat`, `telepon`, `email`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `pekerjaan`, `ktp`, `foto_ktp`, `rekening_bank`, `user_id`, `status`, `tanggal_daftar`, `created_at`) VALUES
(1, '64534225', 'smp2 siak', '', '', '', '', NULL, 'L', '', '', '', '', 7, 'aktif', '2026-03-05', '2026-02-24 16:38:57'),
(2, '434343', 'SMAN1 Kolaka', 'Jl. Pendidikan No. 45 Kel. Laloeha Kec. Kolaka', '08114056704', 'babbaimar@gmail.com', 'Lumajang', '2023-02-07', 'L', 'Karyawan Swasta', '3511111809990053', '', '978655645', 8, 'aktif', '2026-03-07', '2026-03-07 22:06:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `angsuran`
--

CREATE TABLE `angsuran` (
  `id` int(11) NOT NULL,
  `pinjaman_id` int(11) NOT NULL,
  `angsuran_ke` int(11) NOT NULL,
  `jumlah_angsuran` decimal(15,2) NOT NULL,
  `pokok` decimal(15,2) NOT NULL,
  `bunga` decimal(15,2) NOT NULL,
  `denda` decimal(15,2) DEFAULT '0.00',
  `tanggal_jatuh_tempo` date NOT NULL,
  `tanggal_bayar` date DEFAULT NULL,
  `status` enum('belum_bayar','lunas','telat') DEFAULT 'belum_bayar',
  `keterlambatan_hari` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_pinjaman`
--

CREATE TABLE `jenis_pinjaman` (
  `id` int(11) NOT NULL,
  `kode_jenis` varchar(10) NOT NULL,
  `nama_jenis` varchar(50) NOT NULL,
  `maksimal_pinjaman` decimal(15,2) NOT NULL,
  `tenor_max` int(11) NOT NULL,
  `bunga` decimal(5,2) NOT NULL,
  `deskripsi` text,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `jenis_pinjaman`
--

INSERT INTO `jenis_pinjaman` (`id`, `kode_jenis`, `nama_jenis`, `maksimal_pinjaman`, `tenor_max`, `bunga`, `deskripsi`, `status`, `created_at`) VALUES
(1, 'UMUM', 'Pinjaman Umum', '5000000.00', 12, '12.00', 'Pinjaman untuk keperluan umum', 'aktif', '2025-10-26 03:24:29'),
(2, 'PENDID', 'Pinjaman Pendidikan', '10000000.00', 24, '8.00', 'Pinjaman untuk biaya pendidikan', 'aktif', '2025-10-26 03:24:29'),
(3, 'USAHA', 'Pinjaman Usaha', '20000000.00', 36, '10.00', 'Pinjaman untuk modal usaha', 'aktif', '2025-10-26 03:24:29'),
(4, 'MDRH', 'Pinjaman Mendesak', '3000000.00', 6, '15.00', 'Pinjaman untuk kebutuhan mendesak', 'aktif', '2025-10-26 03:24:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_simpanan`
--

CREATE TABLE `jenis_simpanan` (
  `id` int(11) NOT NULL,
  `kode_jenis` varchar(10) NOT NULL,
  `nama_jenis` varchar(50) NOT NULL,
  `deskripsi` text,
  `minimum` decimal(15,2) DEFAULT '0.00',
  `maksimum` decimal(15,2) DEFAULT '0.00',
  `bagi_hasil` decimal(5,2) DEFAULT '0.00',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `jenis_simpanan`
--

INSERT INTO `jenis_simpanan` (`id`, `kode_jenis`, `nama_jenis`, `deskripsi`, `minimum`, `maksimum`, `bagi_hasil`, `status`, `created_at`) VALUES
(1, 'POKOK', 'Simpanan Pokok', 'Simpanan pokok anggota', '50000.00', '1000000.00', '0.00', 'aktif', '2025-10-26 02:02:45'),
(2, 'WAJIB', 'Simpanan Wajib', 'Simpanan wajib bulanan', '25000.00', '1000000.00', '0.00', 'aktif', '2025-10-26 02:02:45'),
(3, 'SUKARELA', 'Simpanan Sukarela', 'Simpanan sukarela', '50000.00', '1000000.00', '0.00', 'aktif', '2025-10-26 02:02:45'),
(4, 'DEPOSITO', 'Deposito', 'Simpanan berjangka', '1000000.00', '20000000.00', '0.50', 'aktif', '2025-10-26 02:02:45'),
(5, 'HAJI', 'Haji', 'Simpanan haji', '5000.00', '1000000.00', '0.00', 'aktif', '2025-10-26 02:02:45'),
(6, 'UMROH', 'Umroh', 'Simpanan umroh', '300000.00', '1000000.00', '10.00', 'aktif', '2025-10-26 02:02:45'),
(7, 'ANG', 'Pembayaran Angsuran', 'Pembayaran angsuran pinjaman', '0.00', '0.00', '0.00', 'aktif', '2026-03-01 15:16:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kas`
--

CREATE TABLE `kas` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `jenis` enum('masuk','keluar') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `saldo_sebelum` decimal(15,2) NOT NULL,
  `saldo_sesudah` decimal(15,2) NOT NULL,
  `keterangan` text,
  `tanggal` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `kas`
--

INSERT INTO `kas` (`id`, `transaksi_id`, `jenis`, `jumlah`, `saldo_sebelum`, `saldo_sesudah`, `keterangan`, `tanggal`) VALUES
(1, 1, 'masuk', '1000000.00', '0.00', '1000000.00', '', '2026-03-06 22:53:59'),
(2, 2, 'keluar', '100000.00', '1000000.00', '900000.00', 'g', '2026-03-06 22:56:03'),
(3, 3, 'masuk', '100000.00', '900000.00', '1000000.00', '', '2026-03-07 05:02:10'),
(4, 4, 'masuk', '1000000.00', '1000000.00', '2000000.00', '', '2026-03-07 05:02:26'),
(5, 5, 'keluar', '100000.00', '2000000.00', '1900000.00', 'gf', '2026-03-07 05:12:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `koperasi`
--

CREATE TABLE `koperasi` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `alamat` text,
  `logo` varchar(255) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rekening_bank` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `koperasi`
--

INSERT INTO `koperasi` (`id`, `nama`, `alamat`, `logo`, `telepon`, `email`, `rekening_bank`, `created_at`) VALUES
(1, 'Koperasi Simpan Pinjam Sejahtera', 'Jl. Koperasi No. 123, Jakarta', 'logo_1771843672.png', '(021) 123-4567', 'info@koperasi.com', '745367534', '2025-10-26 02:02:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_koreksi`
--

CREATE TABLE `log_koreksi` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `jumlah_lama` decimal(15,2) NOT NULL,
  `jumlah_baru` decimal(15,2) NOT NULL,
  `selisih` decimal(15,2) NOT NULL,
  `keterangan` text,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_indihome`
--

CREATE TABLE `pembelian_indihome` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `no_pelanggan` varchar(50) NOT NULL,
  `paket_layanan` varchar(100) NOT NULL,
  `bulan_tagihan` date NOT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `metode_bayar` enum('tunai','transfer') NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_listrik`
--

CREATE TABLE `pembelian_listrik` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `nomor_token` varchar(50) NOT NULL,
  `jumlah_kwh` decimal(10,2) NOT NULL,
  `harga_per_kwh` decimal(10,2) NOT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `metode_bayar` enum('tunai','transfer') NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengajuan_pinjaman`
--

CREATE TABLE `pengajuan_pinjaman` (
  `id` int(11) NOT NULL,
  `kode_pengajuan` varchar(20) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `jenis_pinjaman_id` int(11) NOT NULL,
  `jumlah_pinjaman` decimal(15,2) NOT NULL,
  `tenor` int(11) NOT NULL,
  `bunga` decimal(5,2) NOT NULL,
  `tujuan_pinjaman` text,
  `status` enum('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
  `catatan_admin` text,
  `admin_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tanggal_pengajuan` datetime DEFAULT CURRENT_TIMESTAMP,
  `tanggal_persetujuan` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `periode`
--

CREATE TABLE `periode` (
  `id` int(11) NOT NULL,
  `tahun` year(4) NOT NULL,
  `bulan` tinyint(4) NOT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'nonaktif',
  `tanggal_buka` date DEFAULT NULL,
  `tanggal_tutup` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pinjaman`
--

CREATE TABLE `pinjaman` (
  `id` int(11) NOT NULL,
  `pengajuan_id` int(11) NOT NULL,
  `kode_pinjaman` varchar(20) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `jenis_pinjaman_id` int(11) NOT NULL,
  `jumlah_pinjaman` decimal(15,2) NOT NULL,
  `sisa_pokok` decimal(15,2) NOT NULL,
  `tenor` int(11) NOT NULL,
  `bunga` decimal(5,2) NOT NULL,
  `total_pinjaman` decimal(15,2) NOT NULL,
  `angsuran_per_bulan` decimal(15,2) NOT NULL,
  `tanggal_pencairan` date NOT NULL,
  `jatuh_tempo` date NOT NULL,
  `status` enum('aktif','lunas','macet') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `simpanan`
--

CREATE TABLE `simpanan` (
  `id` int(11) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `jenis_simpanan_id` int(11) NOT NULL,
  `saldo` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `simpanan`
--

INSERT INTO `simpanan` (`id`, `anggota_id`, `jenis_simpanan_id`, `saldo`, `created_at`, `updated_at`) VALUES
(1, 1, 4, '800000.00', '2026-03-06 15:53:44', '2026-03-06 22:12:28'),
(2, 1, 1, '100000.00', '2026-03-06 22:02:01', '2026-03-06 22:02:10'),
(3, 1, 3, '1000000.00', '2026-03-06 22:02:22', '2026-03-06 22:02:26'),
(4, 2, 1, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55'),
(5, 2, 2, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55'),
(6, 2, 3, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55'),
(7, 2, 4, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55'),
(8, 2, 5, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55'),
(9, 2, 6, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55'),
(10, 2, 7, '0.00', '2026-03-07 22:06:55', '2026-03-07 22:06:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `jenis_simpanan_id` int(11) NOT NULL,
  `jenis_transaksi` enum('setoran','penarikan') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `saldo_sebelum` decimal(15,2) NOT NULL,
  `saldo_sesudah` decimal(15,2) NOT NULL,
  `keterangan` text,
  `user_id` int(11) NOT NULL,
  `tanggal_transaksi` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `anggota_id`, `jenis_simpanan_id`, `jenis_transaksi`, `jumlah`, `saldo_sebelum`, `saldo_sesudah`, `keterangan`, `user_id`, `tanggal_transaksi`, `created_at`) VALUES
(1, 'SET-20260306-9302', 1, 4, 'setoran', '1000000.00', '0.00', '1000000.00', '', 1, '2026-03-06 22:53:59', '2026-03-06 15:53:59'),
(2, 'TAR-20260306165603-5', 1, 4, 'penarikan', '100000.00', '1000000.00', '900000.00', 'g', 4, '2026-03-06 22:56:03', '2026-03-06 15:56:03'),
(3, 'SET-20260306230210-6', 1, 1, 'setoran', '100000.00', '0.00', '100000.00', '', 4, '2026-03-07 05:02:10', '2026-03-06 22:02:10'),
(4, 'SET-20260306230226-9', 1, 3, 'setoran', '1000000.00', '0.00', '1000000.00', '', 4, '2026-03-07 05:02:26', '2026-03-06 22:02:26'),
(5, 'TAR-20260306231228-7', 1, 4, 'penarikan', '100000.00', '900000.00', '800000.00', 'gf', 4, '2026-03-07 05:12:28', '2026-03-06 22:12:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','teller','anggota') NOT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `status`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$TmC51loUtY.HKkEp04ip/.g3et9ECT1PklcUK5AxHECnefjs16uEq', 'Administrator', 'admin', 'aktif', '2026-03-12 14:05:48', '2025-10-26 02:02:45'),
(4, 'santoso', '$2y$10$M1zq6dKYV7UINrI78evnrufm1d8ehEakw.QSjQML1IStUcTkNpEG6', 'santoso ambles', 'teller', 'aktif', '2026-03-12 14:07:08', '2025-10-26 08:26:19'),
(5, 'LUTFILLAH', '$2y$10$5iBi1JxmNnD8VjoBLKBDSe1q8DYyvjfKjZHjmoi0R87K/qgfani7e', 'LUTFILLAH', 'teller', 'aktif', NULL, '2026-02-24 16:26:11'),
(7, '64534225', '$2y$10$faB90MTiVklwaaVF7B4v3OQNW/2yz7kmOEry8Nzd710qVUp.jq/Oy', 'smp2 siak', 'anggota', 'aktif', '2026-03-12 14:08:53', '2026-02-24 16:38:57'),
(8, '434343', '$2y$10$kp3Euj3IGPAYfJXbWaWfqOMavfFHBnW2HdH76ilfdIYyel/WCsR9S', 'SMAN1 Kolaka', 'anggota', 'aktif', '2026-03-12 14:08:36', '2026-03-07 22:06:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `waserda_transaksi`
--

CREATE TABLE `waserda_transaksi` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `items` text NOT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `metode_bayar` enum('tunai','transfer') NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_anggota` (`kode_anggota`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `angsuran`
--
ALTER TABLE `angsuran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pinjaman_id` (`pinjaman_id`);

--
-- Indeks untuk tabel `jenis_pinjaman`
--
ALTER TABLE `jenis_pinjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_jenis` (`kode_jenis`);

--
-- Indeks untuk tabel `jenis_simpanan`
--
ALTER TABLE `jenis_simpanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_jenis` (`kode_jenis`);

--
-- Indeks untuk tabel `kas`
--
ALTER TABLE `kas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`);

--
-- Indeks untuk tabel `koperasi`
--
ALTER TABLE `koperasi`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `log_koreksi`
--
ALTER TABLE `log_koreksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `pembelian_indihome`
--
ALTER TABLE `pembelian_indihome`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `pembelian_listrik`
--
ALTER TABLE `pembelian_listrik`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `pengajuan_pinjaman`
--
ALTER TABLE `pengajuan_pinjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pengajuan` (`kode_pengajuan`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `jenis_pinjaman_id` (`jenis_pinjaman_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `periode`
--
ALTER TABLE `periode`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pinjaman`
--
ALTER TABLE `pinjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pinjaman` (`kode_pinjaman`),
  ADD KEY `pengajuan_id` (`pengajuan_id`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `jenis_pinjaman_id` (`jenis_pinjaman_id`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `simpanan`
--
ALTER TABLE `simpanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_anggota_jenis` (`anggota_id`,`jenis_simpanan_id`),
  ADD KEY `jenis_simpanan_id` (`jenis_simpanan_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `jenis_simpanan_id` (`jenis_simpanan_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `waserda_transaksi`
--
ALTER TABLE `waserda_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `angsuran`
--
ALTER TABLE `angsuran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jenis_pinjaman`
--
ALTER TABLE `jenis_pinjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `jenis_simpanan`
--
ALTER TABLE `jenis_simpanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `kas`
--
ALTER TABLE `kas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `koperasi`
--
ALTER TABLE `koperasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `log_koreksi`
--
ALTER TABLE `log_koreksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembelian_indihome`
--
ALTER TABLE `pembelian_indihome`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembelian_listrik`
--
ALTER TABLE `pembelian_listrik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengajuan_pinjaman`
--
ALTER TABLE `pengajuan_pinjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `periode`
--
ALTER TABLE `periode`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pinjaman`
--
ALTER TABLE `pinjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `simpanan`
--
ALTER TABLE `simpanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `waserda_transaksi`
--
ALTER TABLE `waserda_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `anggota`
--
ALTER TABLE `anggota`
  ADD CONSTRAINT `anggota_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `angsuran`
--
ALTER TABLE `angsuran`
  ADD CONSTRAINT `angsuran_ibfk_1` FOREIGN KEY (`pinjaman_id`) REFERENCES `pinjaman` (`id`);

--
-- Ketidakleluasaan untuk tabel `log_koreksi`
--
ALTER TABLE `log_koreksi`
  ADD CONSTRAINT `log_koreksi_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`),
  ADD CONSTRAINT `log_koreksi_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembelian_indihome`
--
ALTER TABLE `pembelian_indihome`
  ADD CONSTRAINT `pembelian_indihome_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `pembelian_indihome_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembelian_listrik`
--
ALTER TABLE `pembelian_listrik`
  ADD CONSTRAINT `pembelian_listrik_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `pembelian_listrik_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pengajuan_pinjaman`
--
ALTER TABLE `pengajuan_pinjaman`
  ADD CONSTRAINT `pengajuan_pinjaman_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `pengajuan_pinjaman_ibfk_2` FOREIGN KEY (`jenis_pinjaman_id`) REFERENCES `jenis_pinjaman` (`id`),
  ADD CONSTRAINT `pengajuan_pinjaman_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pengajuan_pinjaman_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pinjaman`
--
ALTER TABLE `pinjaman`
  ADD CONSTRAINT `pinjaman_ibfk_1` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan_pinjaman` (`id`),
  ADD CONSTRAINT `pinjaman_ibfk_2` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `pinjaman_ibfk_3` FOREIGN KEY (`jenis_pinjaman_id`) REFERENCES `jenis_pinjaman` (`id`);

--
-- Ketidakleluasaan untuk tabel `simpanan`
--
ALTER TABLE `simpanan`
  ADD CONSTRAINT `simpanan_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `simpanan_ibfk_2` FOREIGN KEY (`jenis_simpanan_id`) REFERENCES `jenis_simpanan` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`jenis_simpanan_id`) REFERENCES `jenis_simpanan` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `waserda_transaksi`
--
ALTER TABLE `waserda_transaksi`
  ADD CONSTRAINT `waserda_transaksi_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `waserda_transaksi_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
