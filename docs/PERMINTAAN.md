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
| Denda cicilan mingguan: nominal per hari menurut besar pinjaman (1 jt = 1.500/hari, 2 jt = 2.500/hari, 3 jt = 3.500/hari) **[di luar cakupan P1]** | **Belum**, menunggu jawaban klien (pinjaman di antara/di atas 3 jt, berlaku mingguan saja atau bulanan juga, batas maksimal) | — |
| Tabel angsuran resmi dari klien | **Menunggu klien** | — |

## Permintaan 2 — screenshot level admin + contoh slip bukti angsuran

| # | Isi | Status | Commit |
|---|---|---|---|
| 2.1 | Tambah anggota (Admin): kode anggota otomatis **[di luar cakupan P1]** | Selesai | 9ec4046 |
| 2.2 | Tambah anggota (Admin): hapus field email **[di luar cakupan P1]** | Selesai | 9ec4046 |
| 2.3 | Edit anggota (Admin): tombol Update tidak terlihat | Selesai (modal terpotong, diperbaiki) | 9ec4046 |
| 2.4 | Slip **Bukti Pembayaran Angsuran** (jenis angsuran, nama & alamat anggota, lunas/pelunasan, terbilang, TTD Admin & Teller) **[di luar cakupan P1]** | Selesai; butuh migrasi `2026_10b_cara_bayar_angsuran.sql` | 9ec4046 |

Perlu konfirmasi klien untuk 2.4: arti "Terbilang 0" (sekarang = nominal dalam kata + sisa tagihan terpisah),
dan judul slip ("Bukti Pembayaran Angsuran" sesuai gambar vs "Bukti Angsuran Lunas" sesuai catatan).

## Hal bawaan (bukan permintaan klien)

Banner "Database belum di-update", README, repo GitHub, `sql_mode` per koneksi di `config/config.php`,
pesan error tidak lagi hilang otomatis, perbaikan INSERT `admin/pengajuan.php`.
Bug lama yang **belum** diubah: tabel `pelunasan_dini` tidak ada (catatan pelunasan dini tidak tersimpan),
pinjaman skema lama menyisakan `sisa_pokok` 0,01, tombol Bayar tidak mencegah bayar ganda,
`admin/jenis_pinjaman.php` tanpa cek role.
