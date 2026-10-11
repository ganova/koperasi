<?php
/**
 * Koreksi pembayaran angsuran (hanya Admin).
 *
 * Dipakai bila Teller salah menekan "Bayar": pembayaran dibatalkan, cicilan kembali "belum bayar",
 * dan pembukuan dibalikkan dengan pola yang sama seperti admin/koreksi.php:
 *   - transaksi pembayaran dijadikan Rp 0 + catatan "DIBATALKAN: alasan"
 *   - saldo simpanan 'ANG' dikurangi, baris kas jadi Rp 0 dan saldo kas sesudahnya disesuaikan
 *   - dicatat di log_koreksi (siapa, kapan, alasan)
 *   - sisa pokok pinjaman bertambah lagi; pinjaman kembali 'aktif' bila tadinya lunas
 * Cicilan yang dibayar lewat Pelunasan tidak punya transaksi kas; hanya statusnya yang dikembalikan.
 */

if (!function_exists('batalkanPembayaranAngsuran')) {
    /**
     * @return string ringkasan untuk pesan sukses
     * @throws Exception
     */
    function batalkanPembayaranAngsuran($db, $angsuran_id, $alasan, $user_id) {
        if (!isAdmin()) {
            throw new Exception("Hanya Admin yang dapat membatalkan pembayaran");
        }
        $alasan = trim($alasan);
        if (strlen($alasan) < 5) {
            throw new Exception("Alasan pembatalan wajib diisi (minimal 5 karakter)");
        }
        if (strlen($alasan) > 250) {
            $alasan = substr($alasan, 0, 250);
        }

        $errmode = $db->getAttribute(PDO::ATTR_ERRMODE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT a.*, p.anggota_id, p.kode_pinjaman, p.jumlah_pinjaman
                                  FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id
                                  WHERE a.id = ? FOR UPDATE");
            $stmt->execute([$angsuran_id]);
            $a = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$a || $a['status'] !== 'lunas') {
                throw new Exception("Angsuran tidak ditemukan atau belum dibayar");
            }

            // ---- pembukuan: transaksi pembayaran terakhir untuk cicilan ini
            $ket = 'Pembayaran angsuran ke-' . $a['angsuran_ke'] . ' - ' . $a['kode_pinjaman'];
            $stmt = $db->prepare("SELECT * FROM transaksi
                                  WHERE (keterangan = ? OR keterangan LIKE CONCAT(?, ' |%')) AND jumlah > 0
                                  ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$ket, $ket]);
            $t = $stmt->fetch(PDO::FETCH_ASSOC);

            $dikembalikan = 0.0;
            if ($t) {
                $dikembalikan = (float) $t['jumlah'];
                $catatan = ' | DIBATALKAN: ' . $alasan;

                $db->prepare("UPDATE transaksi SET jumlah = 0, saldo_sesudah = saldo_sesudah - ?,
                              keterangan = CONCAT(keterangan, ?) WHERE id = ?")
                   ->execute([$dikembalikan, $catatan, $t['id']]);

                $db->prepare("UPDATE simpanan SET saldo = saldo - ? WHERE anggota_id = ? AND jenis_simpanan_id = ?")
                   ->execute([$dikembalikan, $t['anggota_id'], $t['jenis_simpanan_id']]);

                $stmt = $db->prepare("SELECT * FROM kas WHERE transaksi_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$t['id']]);
                $k = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($k) {
                    $db->prepare("UPDATE kas SET jumlah = 0, saldo_sesudah = saldo_sebelum,
                                  keterangan = CONCAT(COALESCE(keterangan, ''), ?) WHERE id = ?")
                       ->execute([$catatan, $k['id']]);
                    // Saldo kas baris-baris sesudahnya ikut turun agar buku kas tetap berkesinambungan
                    $db->prepare("UPDATE kas SET saldo_sebelum = saldo_sebelum - ?, saldo_sesudah = saldo_sesudah - ?
                                  WHERE id > ?")
                       ->execute([(float) $k['jumlah'], (float) $k['jumlah'], $k['id']]);
                }

                $db->prepare("INSERT INTO log_koreksi (transaksi_id, jumlah_lama, jumlah_baru, selisih, keterangan, user_id)
                              VALUES (?, ?, 0, ?, ?, ?)")
                   ->execute([$t['id'], $dikembalikan, -$dikembalikan, 'Pembatalan pembayaran angsuran: ' . $alasan, $user_id]);
            }

            // ---- cicilan kembali belum dibayar (terlambat bila sudah lewat jatuh tempo)
            $hitung = hitungDendaAngsuran($db, $a, $a['jumlah_pinjaman']);
            $telat = $hitung['hari_telat'] > 0;
            $set = "status = ?, tanggal_bayar = NULL, denda = ?, keterlambatan_hari = ?";
            if (kolomAda($db, 'angsuran', 'denda_manual')) {
                $set .= ", denda_manual = 0";
            }
            if (kolomAda($db, 'angsuran', 'cara_bayar')) {
                $set .= ", cara_bayar = 'cicilan'";
            }
            $db->prepare("UPDATE angsuran SET $set WHERE id = ?")
               ->execute([$telat ? 'telat' : 'belum_bayar', $telat ? $hitung['denda'] : 0, $hitung['hari_telat'], $angsuran_id]);

            // ---- pinjaman: sisa pokok bertambah, aktif lagi
            $set = "sisa_pokok = sisa_pokok + ?, status = 'aktif'";
            if (kolomAda($db, 'pinjaman', 'tanggal_lunas')) {
                $set .= ", tanggal_lunas = NULL";
            }
            $db->prepare("UPDATE pinjaman SET $set WHERE id = ?")->execute([(float) $a['pokok'], $a['pinjaman_id']]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, $errmode);
            throw $e;
        }
        $db->setAttribute(PDO::ATTR_ERRMODE, $errmode);

        return 'Pembayaran angsuran ke-' . $a['angsuran_ke'] . ' (' . $a['kode_pinjaman'] . ') dibatalkan'
            . ($dikembalikan > 0 ? '. Kas dikurangi ' . formatRupiah($dikembalikan) : ' (dibayar lewat pelunasan, tidak ada catatan kas)');
    }
}
