# Koperasi Simpan Pinjam Sejahtera

Aplikasi koperasi simpan pinjam (PHP + MySQL) dengan 3 level login: Admin, Teller, Anggota.

## Kebutuhan

- PHP **7.0 atau lebih baru** dengan ekstensi `pdo_mysql` dan `fileinfo`
- MySQL / MariaDB
- Apache dengan `AllowOverride All` (agar `.htaccess` di folder upload berlaku), misalnya XAMPP

## Instalasi

1. Clone ke folder web server, misalnya `C:\xampp\htdocs\koperasi2`:
   ```bash
   git clone https://github.com/ganova/koperasi.git koperasi2
   ```
2. Buat database `koperasi_db`, lalu import struktur & data awal:
   ```bash
   mysql -u root -e "CREATE DATABASE koperasi_db"
   mysql -u root koperasi_db < koperasi_db.sql
   ```
3. Jalankan migrasi revisi **sekali saja**:
   ```bash
   mysql -u root koperasi_db < database/migrations/2026_10_revisi_koperasi.sql
   ```
   Untuk database yang sudah berisi data, **backup dulu** sebelum menjalankan migrasi.
4. Sesuaikan koneksi database di `config/database.php` bila user/password MySQL berbeda.
5. Atur `php.ini` lalu restart Apache:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 40M
   extension=fileinfo
   ```
6. Pastikan folder `uploads/anggota/` bisa ditulis oleh web server.
7. Buka `http://localhost/koperasi2/`.

## Jika muncul pesan merah "Database belum di-update"

Berarti langkah 3 (file migrasi) belum dijalankan pada database yang dipakai. Tanpa migrasi,
muncul error seperti `Undefined index: frekuensi_diizinkan` / `foto_kk` dan dokumen anggota
tidak tersimpan. Jalankan migrasinya sekali (bisa juga lewat phpMyAdmin → database
`koperasi_db` → tab **Import** → pilih `database/migrations/2026_10_revisi_koperasi.sql`).

## Setelah instalasi

- Jenis pinjaman lama (Umum, Pendidikan, Usaha, Mendesak) perlu diisi **tarif bunga flat per tenor** di menu Admin → Jenis Pinjaman sebelum bisa dipakai di form pengajuan.
- Anggota baru yang ditambahkan Teller mendapat akun login dengan password awal `123456`.

## Catatan revisi Oktober 2026

- Rumus bunga & angsuran ada di satu tempat: `includes/pinjaman_helper.php` (PHP) dan `assets/js/pinjaman.js` (preview form).
- Pinjaman sebelum migrasi tetap memakai skema bunga lama (`skema_bunga = 'tahunan'`); pinjaman baru memakai bunga flat per tenor.
- Dokumen anggota (KTP, KK, KTP suami/istri) **wajib** dan disimpan di `uploads/anggota/{id_anggota}/`. Daftar dokumen wajib diatur di `DOKUMEN_ANGGOTA_WAJIB` (`includes/dokumen_anggota.php`).
