<?php
/**
 * Penghapusan anggota yang aman (dipakai admin/anggota.php & teller/anggota.php).
 *
 * Sebelumnya: PDO berjalan dalam mode silent, sehingga DELETE anggota yang masih
 * punya transaksi gagal diam-diam tetapi pesan "berhasil dihapus" tetap muncul,
 * sementara simpanan & file dokumennya sudah terlanjur terhapus.
 */

if (!function_exists('riwayatAnggota')) {
    /** @return array label => jumlah baris terkait yang mencegah penghapusan */
    function riwayatAnggota($db, $anggota_id) {
        $cek = [
            'transaksi simpanan/penarikan' => "SELECT COUNT(*) FROM transaksi WHERE anggota_id = ?",
            'pengajuan pinjaman' => "SELECT COUNT(*) FROM pengajuan_pinjaman WHERE anggota_id = ?",
            'pinjaman' => "SELECT COUNT(*) FROM pinjaman WHERE anggota_id = ?",
            'transaksi waserda' => "SELECT COUNT(*) FROM waserda_transaksi WHERE anggota_id = ?",
            'pembelian listrik' => "SELECT COUNT(*) FROM pembelian_listrik WHERE anggota_id = ?",
            'pembelian indihome' => "SELECT COUNT(*) FROM pembelian_indihome WHERE anggota_id = ?",
            'simpanan dengan saldo' => "SELECT COUNT(*) FROM simpanan WHERE anggota_id = ? AND saldo <> 0",
        ];
        $hasil = [];
        foreach ($cek as $label => $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$anggota_id]);
            $jumlah = (int) $stmt->fetchColumn();
            if ($jumlah > 0) {
                $hasil[$label] = $jumlah;
            }
        }
        return $hasil;
    }
}

if (!function_exists('hapusAnggota')) {
    /**
     * Hapus anggota beserta rekening simpanan (bersaldo 0), akun login anggota, dan
     * file dokumennya. Ditolak bila anggota sudah punya riwayat keuangan.
     * @throws Exception
     */
    function hapusAnggota($db, $anggota_id) {
        $errmode = $db->getAttribute(PDO::ATTR_ERRMODE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $db->prepare("SELECT a.*, u.role AS user_role FROM anggota a
                                  LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
            $stmt->execute([$anggota_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                throw new Exception("Data anggota tidak ditemukan");
            }

            $riwayat = riwayatAnggota($db, $anggota_id);
            if (!empty($riwayat)) {
                $rincian = [];
                foreach ($riwayat as $label => $jumlah) {
                    $rincian[] = "$jumlah $label";
                }
                throw new Exception("Anggota " . htmlspecialchars($data['nama_lengkap'])
                    . " tidak bisa dihapus karena sudah memiliki " . implode(', ', $rincian)
                    . ". Ubah statusnya menjadi Nonaktif.");
            }

            $db->beginTransaction();
            $db->prepare("DELETE FROM simpanan WHERE anggota_id = ?")->execute([$anggota_id]);
            $db->prepare("DELETE FROM anggota WHERE id = ?")->execute([$anggota_id]);
            // Hanya hapus akun bila memang akun anggota (data lama bisa tertaut ke akun teller)
            if ($data['user_id'] && $data['user_role'] === 'anggota') {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$data['user_id']]);
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, $errmode);
            throw $e;
        }
        $db->setAttribute(PDO::ATTR_ERRMODE, $errmode);

        // File dihapus setelah data benar-benar terhapus
        foreach (array_keys(daftarDokumenAnggota()) as $field) {
            hapusFileDokumenAnggota($data[$field]);
        }
    }
}
