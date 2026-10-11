<?php
/**
 * Helper perhitungan pinjaman & jadwal angsuran.
 *
 * SATU-SATUNYA tempat rumus bunga/angsuran. Bila klien mengirim tabel angsuran
 * resmi, cukup sesuaikan hitungPinjaman() di sini dan hitungPinjaman() di
 * assets/js/pinjaman.js (preview form) agar hasilnya tetap sama.
 *
 * Skema bunga:
 *   'flat'    : total = pokok + (pokok x bunga%)  -> bunga untuk keseluruhan tenor
 *   'tahunan' : rumus lama (pinjaman sebelum revisi Okt 2026), tidak boleh diubah
 *               supaya nilai pinjaman lama tetap sama.
 */

if (!defined('BUNGA_MAKS')) {
    define('BUNGA_MAKS', 1000); // batas wajar bunga (%) agar tidak salah ketik
}

if (!function_exists('parseRupiah')) {
    /**
     * Validasi nominal rupiah penuh (boleh tidak bulat ribuan, mis. 112500 / 1112575).
     * @return string|false angka tanpa desimal, atau false bila tidak valid
     */
    function parseRupiah($nilai) {
        $nilai = trim((string) $nilai);
        if (!preg_match('/^\d{1,13}(\.0+)?$/', $nilai)) {
            return false;
        }
        return ltrim(preg_replace('/\.0+$/', '', $nilai), '0') ?: '0';
    }
}

if (!function_exists('hitungJumlahCicilan')) {
    function hitungJumlahCicilan($tenor_bulan, $frekuensi) {
        $tenor_bulan = (int) $tenor_bulan;
        return $frekuensi === 'mingguan' ? $tenor_bulan * 4 : $tenor_bulan;
    }
}

if (!function_exists('hitungPinjaman')) {
    /**
     * @return array [
     *   pokok, bunga_persen, skema, frekuensi, tenor, jumlah_cicilan,
     *   total_bunga, total, angsuran (nominal cicilan reguler),
     *   rincian => [ ['ke'=>1,'pokok'=>..,'bunga'=>..,'jumlah'=>..], ... ]
     * ]
     */
    function hitungPinjaman($pokok, $bunga_persen, $tenor_bulan, $frekuensi = 'bulanan', $skema = 'flat') {
        $pokok = (float) $pokok;
        $bunga_persen = (float) $bunga_persen;
        $tenor_bulan = (int) $tenor_bulan;
        $frekuensi = $frekuensi === 'mingguan' ? 'mingguan' : 'bulanan';
        $n = max(1, hitungJumlahCicilan($tenor_bulan, $frekuensi));
        $rincian = [];

        if ($skema === 'tahunan') {
            // Rumus lama (admin/pencairan.php sebelum revisi): bunga per tahun, proporsional tenor
            $total_bunga = $pokok * ($bunga_persen / 100) * ($tenor_bulan / 12);
            $total = $pokok + $total_bunga;
            for ($i = 1; $i <= $n; $i++) {
                $rincian[] = [
                    'ke' => $i,
                    'pokok' => $pokok / $n,
                    'bunga' => $total_bunga / $n,
                    'jumlah' => $total / $n,
                ];
            }
        } else {
            // Flat per tenor, dibulatkan ke rupiah penuh
            $total_bunga = round($pokok * $bunga_persen / 100);
            $total = $pokok + $total_bunga;

            // Cicilan dibulatkan; selisih pembulatan dibebankan ke cicilan terakhir
            $cicilan = round($total / $n);
            $pokok_cicilan = round($pokok / $n);
            $sum_jumlah = 0;
            $sum_pokok = 0;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n) {
                    $jumlah = $cicilan;
                    $pokok_i = $pokok_cicilan;
                } else {
                    $jumlah = $total - $sum_jumlah;
                    $pokok_i = $pokok - $sum_pokok;
                }
                $sum_jumlah += $jumlah;
                $sum_pokok += $pokok_i;
                $rincian[] = [
                    'ke' => $i,
                    'pokok' => $pokok_i,
                    'bunga' => $jumlah - $pokok_i,
                    'jumlah' => $jumlah,
                ];
            }
        }

        return [
            'pokok' => $pokok,
            'bunga_persen' => $bunga_persen,
            'skema' => $skema === 'tahunan' ? 'tahunan' : 'flat',
            'frekuensi' => $frekuensi,
            'tenor' => $tenor_bulan,
            'jumlah_cicilan' => $n,
            'total_bunga' => $total_bunga,
            'total' => $total,
            'angsuran' => $rincian[0]['jumlah'],
            'rincian' => $rincian,
        ];
    }
}

if (!function_exists('hitungJatuhTempo')) {
    /**
     * Tanggal jatuh tempo cicilan ke-$ke dari $tanggal_mulai (Y-m-d).
     * Mingguan: +7 hari per cicilan. Bulanan: +1 bulan per cicilan; bila tanggal
     * tidak ada di bulan tujuan dipakai akhir bulan (sama dengan DATE_ADD MySQL).
     */
    function hitungJatuhTempo($tanggal_mulai, $ke, $frekuensi) {
        $mulai = new DateTime($tanggal_mulai);
        if ($frekuensi === 'mingguan') {
            $mulai->modify('+' . (7 * (int) $ke) . ' days');
            return $mulai->format('Y-m-d');
        }
        $hari = (int) $mulai->format('d');
        $target = new DateTime($mulai->format('Y-m-01'));
        $target->modify('+' . (int) $ke . ' months');
        $hari = min($hari, (int) $target->format('t'));
        return $target->format('Y-m-') . str_pad($hari, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('labelFrekuensi')) {
    function labelFrekuensi($frekuensi) {
        return $frekuensi === 'mingguan' ? 'Mingguan' : 'Bulanan';
    }
}

if (!function_exists('labelPeriode')) {
    /** "Minggu" / "Bulan" untuk label seperti "Angsuran/Minggu". */
    function labelPeriode($frekuensi) {
        return $frekuensi === 'mingguan' ? 'Minggu' : 'Bulan';
    }
}

if (!function_exists('labelBunga')) {
    /** Teks bunga sesuai skema, mis. "35% flat" atau "12% / tahun". */
    function labelBunga($bunga, $skema) {
        $angka = rtrim(rtrim(number_format((float) $bunga, 2, ',', '.'), '0'), ',');
        return $skema === 'tahunan' ? $angka . '% / tahun' : $angka . '% flat';
    }
}

if (!function_exists('labelBungaRow')) {
    /** labelBunga() untuk baris pinjaman/pengajuan; baris tanpa skema dianggap skema lama. */
    function labelBungaRow($row) {
        return labelBunga($row['bunga'], isset($row['skema_bunga']) ? $row['skema_bunga'] : 'tahunan');
    }
}

if (!function_exists('labelTenorRow')) {
    /** Contoh: "3 bulan · Mingguan 12×" atau "3 bulan · Bulanan". */
    function labelTenorRow($row) {
        $frekuensi = isset($row['frekuensi']) ? $row['frekuensi'] : 'bulanan';
        $teks = $row['tenor'] . ' bulan · ' . labelFrekuensi($frekuensi);
        if ($frekuensi === 'mingguan') {
            $teks .= ' ' . hitungJumlahCicilan($row['tenor'], $frekuensi) . '×';
        }
        return $teks;
    }
}

if (!function_exists('ringkasanAngsuran')) {
    /** Contoh: "Mingguan — 12× Rp 112.500" */
    function ringkasanAngsuran($hitung) {
        return labelFrekuensi($hitung['frekuensi']) . ' — ' . $hitung['jumlah_cicilan'] . '× Rp '
            . number_format(round($hitung['angsuran']), 0, ',', '.');
    }
}

if (!function_exists('getTarifJenisPinjaman')) {
    /** @return array tenor_bulan => bunga (%) */
    function getTarifJenisPinjaman($db, $jenis_pinjaman_id) {
        $stmt = $db->prepare("SELECT tenor_bulan, bunga FROM jenis_pinjaman_tarif
                              WHERE jenis_pinjaman_id = ? ORDER BY tenor_bulan");
        $stmt->execute([(int) $jenis_pinjaman_id]);
        $tarif = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tarif[(int) $row['tenor_bulan']] = (float) $row['bunga'];
        }
        return $tarif;
    }
}

if (!function_exists('hitungDariPinjaman')) {
    /** Hitung ulang dari baris tabel pinjaman / pengajuan_pinjaman (membaca skema & frekuensi). */
    function hitungDariPinjaman($row) {
        return hitungPinjaman(
            $row['jumlah_pinjaman'],
            $row['bunga'],
            $row['tenor'],
            isset($row['frekuensi']) ? $row['frekuensi'] : 'bulanan',
            isset($row['skema_bunga']) ? $row['skema_bunga'] : 'tahunan'
        );
    }
}

if (!function_exists('buatPinjamanDanJadwal')) {
    /**
     * Membuat record pinjaman + jadwal angsuran dari pengajuan (dipanggil di dalam
     * transaksi oleh halaman persetujuan / pencairan). Tidak membuat ganda bila
     * pinjaman untuk pengajuan ini sudah ada.
     *
     * @return int|null id pinjaman yang dibuat, null bila sudah ada
     */
    function buatPinjamanDanJadwal($db, $pengajuan, $tanggal_mulai = null) {
        $cek = $db->prepare("SELECT id FROM pinjaman WHERE pengajuan_id = ?");
        $cek->execute([$pengajuan['id']]);
        if ($cek->fetch()) {
            return null;
        }

        if ($tanggal_mulai === null) {
            $tanggal_mulai = date('Y-m-d');
        }
        $hitung = hitungDariPinjaman($pengajuan);
        $n = $hitung['jumlah_cicilan'];
        $jatuh_tempo_akhir = hitungJatuhTempo($tanggal_mulai, $n, $hitung['frekuensi']);
        $kode_pinjaman = 'PJN-' . date('Ymd') . '-' . str_pad($pengajuan['id'], 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO pinjaman
            (pengajuan_id, kode_pinjaman, anggota_id, jenis_pinjaman_id, jumlah_pinjaman, sisa_pokok,
             tenor, bunga, skema_bunga, frekuensi, jumlah_cicilan, total_pinjaman, angsuran_per_bulan,
             tanggal_pencairan, jatuh_tempo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
        $stmt->execute([
            $pengajuan['id'], $kode_pinjaman, $pengajuan['anggota_id'], $pengajuan['jenis_pinjaman_id'],
            $hitung['pokok'], $hitung['pokok'], $hitung['tenor'], $hitung['bunga_persen'],
            $hitung['skema'], $hitung['frekuensi'], $n, $hitung['total'], $hitung['angsuran'],
            $tanggal_mulai, $jatuh_tempo_akhir,
        ]);
        $pinjaman_id = $db->lastInsertId();

        $stmt_angsuran = $db->prepare("INSERT INTO angsuran
            (pinjaman_id, angsuran_ke, jumlah_angsuran, pokok, bunga, tanggal_jatuh_tempo, status)
            VALUES (?, ?, ?, ?, ?, ?, 'belum_bayar')");
        foreach ($hitung['rincian'] as $r) {
            $stmt_angsuran->execute([
                $pinjaman_id, $r['ke'], $r['jumlah'], $r['pokok'], $r['bunga'],
                hitungJatuhTempo($tanggal_mulai, $r['ke'], $hitung['frekuensi']),
            ]);
        }

        return $pinjaman_id;
    }
}

if (!function_exists('terbilang')) {
    /** Angka -> kata, mis. 2550000 => "dua juta lima ratus lima puluh ribu". */
    function terbilang($x) {
        $x = (int) abs(round($x));
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($x < 12) return $satuan[$x];
        if ($x < 20) return terbilang($x - 10) . ' belas';
        if ($x < 100) return terbilang(intdiv($x, 10)) . ' puluh' . ($x % 10 ? ' ' . terbilang($x % 10) : '');
        if ($x < 200) return 'seratus' . ($x - 100 ? ' ' . terbilang($x - 100) : '');
        if ($x < 1000) return terbilang(intdiv($x, 100)) . ' ratus' . ($x % 100 ? ' ' . terbilang($x % 100) : '');
        if ($x < 2000) return 'seribu' . ($x - 1000 ? ' ' . terbilang($x - 1000) : '');
        if ($x < 1000000) return terbilang(intdiv($x, 1000)) . ' ribu' . ($x % 1000 ? ' ' . terbilang($x % 1000) : '');
        if ($x < 1000000000) return terbilang(intdiv($x, 1000000)) . ' juta' . ($x % 1000000 ? ' ' . terbilang($x % 1000000) : '');
        if ($x < 1000000000000) return terbilang(intdiv($x, 1000000000)) . ' miliar' . ($x % 1000000000 ? ' ' . terbilang($x % 1000000000) : '');
        return terbilang(intdiv($x, 1000000000000)) . ' triliun' . ($x % 1000000000000 ? ' ' . terbilang($x % 1000000000000) : '');
    }
}

if (!function_exists('terbilangRupiah')) {
    function terbilangRupiah($x) {
        $kata = (int) round($x) === 0 ? 'nol' : terbilang($x);
        return ucfirst($kata) . ' rupiah';
    }
}

if (!function_exists('kolomAda')) {
    function kolomAda($db, $tabel, $kolom) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$tabel, $kolom]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('tandaiLunasBilaSelesai')) {
    /**
     * Setelah pembayaran: bila SEMUA cicilan pinjaman sudah lunas, tandai pinjaman lunas
     * (status, sisa_pokok = 0, tanggal_lunas = hari ini). Mengatasi sisa pembulatan 0,01 pada
     * pinjaman skema lama yang sebelumnya membuat status tidak pernah berubah jadi lunas.
     * @return bool true bila pinjaman sudah lunas
     */
    function tandaiLunasBilaSelesai($db, $pinjaman_id) {
        $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(status <> 'lunas') AS belum
                              FROM angsuran WHERE pinjaman_id = ?");
        $stmt->execute([$pinjaman_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int) $r['total'] === 0 || (int) $r['belum'] > 0) {
            return false;
        }
        $set = "status = 'lunas', sisa_pokok = 0";
        if (kolomAda($db, 'pinjaman', 'tanggal_lunas')) {
            $set .= ", tanggal_lunas = COALESCE(tanggal_lunas, CURDATE())";
        }
        $db->prepare("UPDATE pinjaman SET $set WHERE id = ?")->execute([$pinjaman_id]);
        return true;
    }
}
