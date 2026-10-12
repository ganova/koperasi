# Catatan Permintaan Revisi

Penomoran: **Permintaan 1** = `prompt-revisi-koperasi.md` (Revisi 1–6). **Permintaan 2** = kiriman klien
berikutnya (screenshot level admin + contoh slip bukti angsuran). Permintaan yang di luar isi Permintaan 1
ditandai **[di luar cakupan P1]**.

## Permintaan 1 — `prompt-revisi-koperasi.md`

| # | Isi | Status | Commit |
|---|---|---|---|
| R1 | Nominal pinjaman bebas (tidak harus bulat) | Selesai | a53e8d3 |
| R2 | Bunga flat per tenor (tarif per jenis pinjaman), batas bunga sampai 1000% | Selesai | 7348412 |
| R3 | Angsuran mingguan/bulanan + jadwal otomatis | Selesai | 7348412 |
| R4 | Upload KTP, KK, KTP suami/istri | Selesai, kini **wajib** saat tambah anggota | 7c1340c, 0d4c8c6 |
| R5 | Slip pencairan A5 (tidak ada bagian yang dihapus, sesuai konfirmasi klien) | Selesai | 5dcdc0d |
| R6 | Jadwal angsuran di dashboard anggota | Selesai | 2e4fadf |

### Tindak lanjut Permintaan 1 (balasan & laporan uji klien via WA)

| Isi | Status | Commit |
|---|---|---|
| Dokumen anggota wajib | Selesai | 0d4c8c6 |
| Bug hapus anggota (gagal diam-diam, simpanan ikut terhapus) **[di luar cakupan P1]** | Selesai | 0e5f9b4 |
| Error karena database belum dimigrasi (banner peringatan, pesan jelas) | Selesai | 869a7b1 |
| Kompatibilitas MySQL strict mode (kode transaksi 20 karakter, tanggal kosong = NULL) **[di luar cakupan P1]** | Selesai | 869a7b1 |
| Tombol **Lihat** di daftar anggota menampilkan 3 dokumen | Selesai | 869a7b1 |
| Edit anggota tidak wajib upload ulang | Selesai | 869a7b1 |
| Email dihapus dari form Tambah Anggota (Teller) **[di luar cakupan P1]** | Selesai | 869a7b1 |
| Tambah Anggota (Teller) membuat akun login anggota sendiri (bug lama) **[di luar cakupan P1]** | Selesai | 72a3198 |
| Denda cicilan: nominal per hari menurut besar pinjaman (1 jt = 1.500, 2 jt = 2.500, 3 jt = 3.500), otomatis + bisa input manual **[di luar cakupan P1]** | Selesai (11 Okt 2026). Migrasi `2026_10c_denda.sql`; tarif bisa diatur Admin; pinjaman hanya kelipatan 1-3 jt (klien); denda dihitung sampai jatuh tempo berikutnya; uji: `php tests/denda_test.php` | d7e374b |
| Tabel angsuran resmi dari klien | **Diterima & diverifikasi** (11 Okt 2026): 9 kasus (Rp 1/2/3 jt × 1/2/3 bulan) dan lampiran 12 minggu cocok persis dengan rumus yang ada, tidak ada perubahan kode. Uji ulang: `php tests/tabel_angsuran_test.php`. Admin cukup mengisi tarif 1 bln = 15%, 2 bln = 25%, 3 bln = 35% dan maksimal pinjaman ≥ Rp 3 jt | tests/ |

## Permintaan 2 — screenshot level admin + contoh slip bukti angsuran

| # | Isi | Status | Commit |
|---|---|---|---|
| 2.1 | Tambah anggota (Admin): kode anggota otomatis **[di luar cakupan P1]** | Selesai | 9ec4046 |
| 2.2 | Tambah anggota (Admin): hapus field email **[di luar cakupan P1]** | Selesai | 9ec4046 |
| 2.3 | Edit anggota (Admin): tombol Update tidak terlihat | Selesai (modal terpotong, diperbaiki) | 9ec4046 |
| 2.4 | Slip **Bukti Pembayaran Angsuran** (jenis angsuran, nama & alamat anggota, lunas/pelunasan, terbilang, TTD Admin & Teller) **[di luar cakupan P1]** | Selesai; butuh migrasi `2026_10b_cara_bayar_angsuran.sql` | 9ec4046 |

Perlu konfirmasi klien untuk 2.4: arti "Terbilang 0" (sekarang = nominal dalam kata + sisa tagihan terpisah),
dan judul slip ("Bukti Pembayaran Angsuran" sesuai gambar vs "Bukti Angsuran Lunas" sesuai catatan).

## Permintaan 3 — `prompt-revisi-koperasi-tahap2.md` (revisi tahap 2)

Migrasi: `database/migrations/2026_10_revisi_tahap2.sql` (jalankan setelah migrasi sebelumnya).

| # | Isi | Status | Commit |
|---|---|---|---|
| A | Cetak Bukti Pelunasan (Admin & Teller; hanya bila semua cicilan lunas; tanggal lunas dicatat) | Selesai | 07d11b2 |
| B | Pisahkan persetujuan dan pencairan (jadwal angsuran dibuat saat Teller mencairkan) | Selesai | e42c403 |
| C | Foto nasabah wajib saat pencairan, bisa dilihat Admin & Teller | Selesai | e42c403 |
| D | Profil anggota read-only (foto pencairan, tanpa form & upload KTP) | Selesai | 4a5b41b |
| E | Sidebar anggota: foto nasabah + badge status Aktif/Nonaktif | Selesai | 4a5b41b |
| F | Teller: Edit Profil berfungsi (+telepon, foto 2 MB), Ubah Password (min. 8, backdoor dibuang), foto di sidebar | Selesai | 6af5774 |

Perubahan di luar poin: pinjaman otomatis ditandai lunas bila semua cicilan lunas (sebelumnya pinjaman skema lama
tertahan `aktif` karena sisa pembulatan 0,01); `teller/ubah_password.php` kini cek role; backdoor password
`'password'` dibuang.

## Permintaan 4 — masukan klien setelah uji tahap 2 (screenshot, 12 Okt 2026)

| # | Isi | Status |
|---|---|---|
| 4.1 | Hapus tanda "@" (Proses Pencairan, detail pengajuan Teller/Admin, slip pencairan): kini "Mingguan — 12× Rp 112.500" | Selesai |
| 4.2 | Bukti Pembayaran Angsuran: hanya kolom Teller + nama (kolom Admin dihapus) | Selesai |
| 4.3 | Bukti Pembayaran Angsuran pada pinjaman yang sudah lunas: jumlah angsuran/denda/total/terbilang diganti kotak hijau "LUNAS" versi mini | Selesai |
| 4.4 | Bukti Pelunasan: kolom tanda tangan Admin dan Teller (sebelumnya Anggota dan Teller) | Selesai |
| 4.5 | Level Anggota: semua fitur cetak/unduh dihapus (PDF angsuran, buku tabungan, SHU); URL lama 404 **[di luar cakupan P1]** | Selesai |
| 4.6 | Pinjaman Saya (Anggota): angsuran belum lunas dan lunas dipisah jadi dua tabel | Selesai |
| 4.7 | Pembayaran Angsuran (Admin): tombol "Batalkan Pembayaran" (koreksi salah pencet bayar) dengan alasan wajib, jejak di log_koreksi; hanya Admin **[di luar cakupan P1]** | Selesai |
| 4.8 | Teks modal persetujuan diperbarui (jadwal dibuat saat pencairan, bukan saat persetujuan) | Selesai |
| 4.9 | Laporan Harian (PDF, layar, Excel): kolom Tgl Bayar dan Jatuh Tempo; kolom Waktu diperbaiki memakai jam pembayaran (sebelumnya jam jadwal dibuat) | Selesai |

Tidak ada migrasi baru untuk Permintaan 4.

## Permintaan 5 — masukan klien (screenshot, 12 Okt 2026, lanjutan)

| # | Isi | Status |
|---|---|---|
| 5.1 | Level Teller: tombol **Batalkan Pembayaran** seperti level Admin (modal + alasan wajib). Pembatasan keputusan saya: Teller hanya untuk pembayaran yang dicatat **hari ini**; koreksi lebih lama oleh Admin | Selesai |
| 5.2 | Jatuh tempo angsuran mengikuti **tanggal pengajuan/pendaftaran** (bukan tanggal pencairan). Tanggal pencairan tetap hari pencairan. Bertentangan dengan brief tahap 2 (jatuh tempo dari tanggal pencairan); bisa dikembalikan lewat `JADWAL_DARI_PENGAJUAN` di `includes/pinjaman_helper.php` | Selesai |

Catatan 5.2: bila pengajuan didaftarkan jauh sebelum pencairan, sebagian cicilan langsung terlambat dan kena denda
otomatis (contoh uji: daftar 9/9, cair 12/10 -> 4 cicilan terlambat, denda Rp 39.000 pada hari pencairan).

## Hal bawaan (bukan permintaan klien)

Banner "Database belum di-update", README, repo GitHub, `sql_mode` per koneksi di `config/config.php`,
pesan error tidak lagi hilang otomatis, perbaikan INSERT `admin/pengajuan.php`.
Bug lama yang **belum** diubah: tabel `pelunasan_dini` tidak ada (catatan pelunasan dini tidak tersimpan),
`admin/jenis_pinjaman.php` tanpa cek role.
