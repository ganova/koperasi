<?php
// Start session jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/config.php';

// Pastikan hanya teller yang bisa akses
if (!isTeller()) {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini";
    header('Location: ../dashboard.php');
    exit();
}

$page_title = "Status Pencairan Pinjaman";

// PERBAIKAN: Ambil data koperasi dari database
$koperasi_query = "SELECT * FROM koperasi WHERE id = 1";
$koperasi_stmt = $db->prepare($koperasi_query);
$koperasi_stmt->execute();
$koperasi = $koperasi_stmt->fetch(PDO::FETCH_ASSOC);

// Jika tidak ada data koperasi, buat default
if (!$koperasi) {
    $koperasi = [
        'nama' => 'Koperasi Simpan Pinjam Sejahtera',
        'alamat' => 'Jl. Belum Diatur',
        'telepon' => '-',
        'email' => '-',
        'rekening_bank' => '-',
        'logo' => null
    ];
}

// Upload melebihi post_max_size membuat $_POST kosong; tampilkan pesan yang jelas
cekPostMelebihiBatas();

// ============================================
// PROSES PENCAIRAN (hanya Teller, foto nasabah wajib)
// Persetujuan Admin hanya mengubah status; pinjaman + jadwal angsuran dibuat di sini,
// jatuh tempo dihitung dari tanggal pencairan (hari ini).
// ============================================
if ($_POST && isset($_POST['proses_pencairan'])) {
    $id = (int) $_POST['id'];
    $foto_path = null;
    $errmode = $db->getAttribute(PDO::ATTR_ERRMODE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        $foto = validasiFotoUpload('foto_nasabah', 'Foto nasabah', 10 * 1024 * 1024, true);
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM pengajuan_pinjaman WHERE id = ? AND status = 'disetujui' FOR UPDATE");
        $stmt->execute([$id]);
        $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pengajuan) {
            throw new Exception("Pengajuan tidak ditemukan atau belum disetujui Admin");
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM pinjaman WHERE pengajuan_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception("Pengajuan ini sudah dicairkan");
        }

        $foto_path = simpanFotoUpload($foto, 'pencairan');
        $pinjaman_id = buatPinjamanDanJadwal($db, $pengajuan, date('Y-m-d'));
        if (!$pinjaman_id) {
            throw new Exception("Pinjaman gagal dibuat");
        }
        $db->prepare("UPDATE pinjaman SET foto_pencairan = ?, dicairkan_oleh = ? WHERE id = ?")
           ->execute([$foto_path, $_SESSION['user_id'], $pinjaman_id]);
        $db->prepare("UPDATE pengajuan_pinjaman SET status = 'dicairkan' WHERE id = ?")->execute([$id]);

        $db->commit();
        $_SESSION['success'] = "Pinjaman berhasil dicairkan. Jadwal angsuran sudah dibuat dari tanggal pencairan hari ini.";
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        hapusFotoUpload($foto_path);
        $_SESSION['error'] = "Gagal memproses pencairan: " . htmlspecialchars($e->getMessage())
            . (migrasiRevisiBelumDijalankan($db) ? " (database belum di-update, jalankan file migrasi)" : "");
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, $errmode);
    header("Location: pencairan.php");
    exit();
}

$query = "SELECT pp.*, a.nama_lengkap, a.kode_anggota, jp.nama_jenis,
          jp.maksimal_pinjaman, u.nama_lengkap as admin_name,
          p.id as pinjaman_id, p.kode_pinjaman, p.tanggal_pencairan, p.status as status_pinjaman,
          p.foto_pencairan, uc.nama_lengkap as teller_name
          FROM pengajuan_pinjaman pp
          JOIN anggota a ON pp.anggota_id = a.id
          JOIN jenis_pinjaman jp ON pp.jenis_pinjaman_id = jp.id
          LEFT JOIN users u ON pp.admin_id = u.id
          LEFT JOIN pinjaman p ON pp.id = p.pengajuan_id
          LEFT JOIN users uc ON p.dicairkan_oleh = uc.id
          WHERE pp.status IN ('disetujui', 'dicairkan', 'develop')
          OR (pp.status = '' AND pp.tanggal_persetujuan IS NOT NULL)
          ORDER BY pp.tanggal_persetujuan DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$pengajuan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query_stats = "SELECT 
    COUNT(*) as total_pengajuan,
    COUNT(CASE WHEN status = 'menunggu' OR (status = '' AND tanggal_persetujuan IS NULL) THEN 1 END) as menunggu,
    COUNT(CASE WHEN status = 'disetujui' OR status = 'develop' OR (status = '' AND tanggal_persetujuan IS NOT NULL) THEN 1 END) as disetujui,
    COUNT(CASE WHEN status = 'dicairkan' THEN 1 END) as dicairkan,
    COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as ditolak
    FROM pengajuan_pinjaman";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
// Kartu: "Disetujui" = disetujui tapi BELUM dicairkan; "Dicairkan" = sudah punya pinjaman
$stats['disetujui'] = (int) $db->query("SELECT COUNT(*) FROM pengajuan_pinjaman pp WHERE pp.status = 'disetujui'
                                        AND NOT EXISTS (SELECT 1 FROM pinjaman p WHERE p.pengajuan_id = pp.id)")->fetchColumn();
$stats['dicairkan'] = (int) $db->query("SELECT COUNT(*) FROM pinjaman")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pencairan Pinjaman - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-actions {
            white-space: nowrap;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }
        .btn-action {
            margin: 2px;
        }
        .info-alert {
            background-color: #e7f3ff;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-money-bill-wave me-2"></i>Status Pencairan Pinjaman
                    </h1>
                    <div>
                        <a href="persetujuan.php" class="btn btn-primary">
                            <i class="fas fa-check-circle me-1"></i> Persetujuan
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <!-- Info Alert -->
                <div class="alert info-alert alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle text-primary me-3 fa-lg"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Informasi Akses</h6>
                            <p class="mb-0">Pengajuan yang sudah disetujui Admin menunggu diproses di sini.
                            <strong>Teller menekan "Proses Pencairan" dan wajib mengunggah foto nasabah</strong>; jadwal angsuran dibuat dari tanggal pencairan.</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_pengajuan']; ?></h4>
                                        <p>Total Pengajuan</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-file-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['menunggu']; ?></h4>
                                        <p>Menunggu</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['disetujui']; ?></h4>
                                        <p>Disetujui</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['dicairkan']; ?></h4>
                                        <p>Dicairkan</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pengajuan Table - HANYA VIEW -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Daftar Status Pengajuan Pinjaman
                        </h5>
                        <div>
                            <span class="text-muted me-2">
                                <?php echo count($pengajuan_list); ?> data ditampilkan
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pengajuan_list)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h5 class="text-muted">Tidak ada data pengajuan</h5>
                            <p class="text-muted">Belum ada pengajuan pinjaman yang disetujui atau dicairkan.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Anggota</th>
                                        <th>Jenis Pinjaman</th>
                                        <th>Jumlah</th>
                                        <th>Tenor</th>
                                        <th>Bunga</th>
                                        <th>Status</th>
                                        <th>Tanggal Persetujuan</th>
                                        <th>Disetujui Oleh</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pengajuan_list as $pengajuan): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $pengajuan['kode_pengajuan']; ?></strong>
                                            <?php if ($pengajuan['kode_pinjaman']): ?>
                                                <br><small class="text-success"><?php echo $pengajuan['kode_pinjaman']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo $pengajuan['nama_lengkap']; ?></div>
                                            <small class="text-muted"><?php echo $pengajuan['kode_anggota']; ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo $pengajuan['nama_jenis']; ?></div>
                                            <small class="text-muted">Maks: <?php echo formatRupiah($pengajuan['maksimal_pinjaman']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-primary"><?php echo formatRupiah($pengajuan['jumlah_pinjaman']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo labelTenorRow($pengajuan); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo labelBungaRow($pengajuan); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            // Handle status
                                            $status = $pengajuan['status'];
                                            if ($status == 'develop' || ($status == '' && $pengajuan['tanggal_persetujuan'])) {
                                                $status = 'disetujui';
                                            }
                                            if (!empty($pengajuan['pinjaman_id'])) {
                                                $status = $pengajuan['status_pinjaman'] == 'lunas' ? 'lunas' : 'dicairkan';
                                            }
                                            $belum_cair = ($status == 'disetujui' && empty($pengajuan['pinjaman_id']));
                                            $status_class = [
                                                'lunas' => 'secondary',
                                                'menunggu' => 'warning',
                                                'disetujui' => 'success',
                                                'dicairkan' => 'info',
                                                'ditolak' => 'danger',
                                                'develop' => 'success'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$status] ?? 'secondary'; ?> status-badge">
                                                <?php echo $belum_cair ? 'Disetujui (belum dicairkan)' : ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($pengajuan['tanggal_persetujuan']): ?>
                                                <small><?php echo date('d/m/Y', strtotime($pengajuan['tanggal_persetujuan'])); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($pengajuan['tanggal_persetujuan'])); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo $pengajuan['admin_name'] ?: '-'; ?></small>
                                        </td>
                                        <td class="table-actions">
                                            <!-- View Details Button -->
                                            <button class="btn btn-sm btn-info btn-action" data-bs-toggle="modal" 
                                                    data-bs-target="#detailModal" 
                                                    data-kode="<?php echo $pengajuan['kode_pengajuan']; ?>"
                                                    data-nama="<?php echo $pengajuan['nama_lengkap']; ?>"
                                                    data-kode_anggota="<?php echo $pengajuan['kode_anggota']; ?>"
                                                    data-jenis="<?php echo $pengajuan['nama_jenis']; ?>"
                                                    data-jumlah="<?php echo $pengajuan['jumlah_pinjaman']; ?>"
                                                    data-tenor="<?php echo $pengajuan['tenor']; ?>"
                                                    data-bunga="<?php echo labelBungaRow($pengajuan); ?>"
                                                    data-tujuan="<?php echo $pengajuan['tujuan_pinjaman']; ?>"
                                                    data-status="<?php echo $status; ?>"
                                                    data-tanggal="<?php echo $pengajuan['tanggal_persetujuan'] ? date('d/m/Y H:i', strtotime($pengajuan['tanggal_persetujuan'])) : '-'; ?>"
                                                    data-catatan="<?php echo $pengajuan['catatan_admin']; ?>"
                                                    data-admin="<?php echo $pengajuan['admin_name'] ?: '-'; ?>"
                                                    data-kode_pinjaman="<?php echo $pengajuan['kode_pinjaman'] ?: '-'; ?>"
                                                    data-tanggal_pencairan="<?php echo $pengajuan['tanggal_pencairan'] ? date('d/m/Y', strtotime($pengajuan['tanggal_pencairan'])) : '-'; ?>"
                                                    title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($belum_cair): ?>
                                            <button class="btn btn-sm btn-primary btn-action" data-bs-toggle="modal"
                                                    data-bs-target="#pencairanModal"
                                                    data-id="<?php echo (int) $pengajuan['id']; ?>"
                                                    data-nama="<?php echo htmlspecialchars($pengajuan['nama_lengkap']); ?>"
                                                    data-kode="<?php echo htmlspecialchars($pengajuan['kode_pengajuan']); ?>"
                                                    data-jumlah="<?php echo $pengajuan['jumlah_pinjaman']; ?>"
                                                    data-ringkas="<?php echo htmlspecialchars(labelTenorRow($pengajuan) . ' | ' . ringkasanAngsuran(hitungDariPinjaman($pengajuan))); ?>">
                                                <i class="fas fa-hand-holding-usd me-1"></i>Proses Pencairan
                                            </button>
                                            <?php endif; ?>
                                            <?php if (!empty($pengajuan['foto_pencairan'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary btn-action" type="button"
                                                    onclick="lihatDokumen('<?php echo htmlspecialchars(urlFoto($pengajuan['foto_pencairan']), ENT_QUOTES); ?>', 'Foto nasabah - <?php echo htmlspecialchars($pengajuan['nama_lengkap'], ENT_QUOTES); ?>')"
                                                    title="Foto nasabah saat pencairan">
                                                <i class="fas fa-camera"></i>
                                            </button>
                                            <?php endif; ?>
                                            <!-- Slip pencairan (A5) - hanya untuk pengajuan yang sudah menjadi pinjaman -->
                                            <?php if (!empty($pengajuan['pinjaman_id'])): ?>
                                            <a class="btn btn-sm btn-success btn-action" target="_blank" rel="noopener"
                                               href="cetak_slip_pencairan.php?id=<?php echo (int) $pengajuan['pinjaman_id']; ?>"
                                               title="Cetak Slip">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Informasi Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="badge bg-warning me-2">Menunggu</span>
                                    <small>Pengajuan sedang menunggu persetujuan admin</small>
                                </div>
                                <div class="mb-3">
                                    <span class="badge bg-success me-2">Disetujui</span>
                                    <small>Pengajuan sudah disetujui dan siap dicairkan</small>
                                </div>
                                <div class="mb-3">
                                    <span class="badge bg-info me-2">Dicairkan</span>
                                    <small>Pinjaman sudah dicairkan dan aktif</small>
                                </div>
                                <div>
                                    <span class="badge bg-danger me-2">Ditolak</span>
                                    <small>Pengajuan ditolak oleh admin</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>Hak Akses Teller
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning mb-0">
                                    <small>
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Perhatian:</strong> Sebagai Teller, Anda hanya memiliki akses untuk:
                                        <ul class="mt-2 mb-0">
                                            <li>Melihat status pengajuan pinjaman</li>
                                            <li>Melakukan pembayaran angsuran</li>
                                            <li>Mengelola pembayaran denda</li>
                                            <li>Melihat riwayat transaksi</li>
                                            <li>Mencetak slip pinjaman yang sudah dicairkan</li>
                                        </ul>
                                        <strong class="mt-2 d-block">Tidak termasuk:</strong>
                                        <ul class="mb-0">
                                            <li>Menyetujui atau menolak pengajuan</li>
                                            <li>Mencairkan pinjaman</li>
                                        </ul>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pengajuan Pinjaman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Kode Pengajuan</th>
                                    <td id="detail_kode"></td>
                                </tr>
                                <tr>
                                    <th>Kode Pinjaman</th>
                                    <td id="detail_kode_pinjaman"></td>
                                </tr>
                                <tr>
                                    <th>Nama Anggota</th>
                                    <td id="detail_nama"></td>
                                </tr>
                                <tr>
                                    <th>Kode Anggota</th>
                                    <td id="detail_kode_anggota"></td>
                                </tr>
                                <tr>
                                    <th>Jenis Pinjaman</th>
                                    <td id="detail_jenis"></td>
                                </tr>
                                <tr>
                                    <th>Disetujui Oleh</th>
                                    <td id="detail_admin"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Jumlah Pinjaman</th>
                                    <td id="detail_jumlah" class="fw-bold text-primary"></td>
                                </tr>
                                <tr>
                                    <th>Tenor</th>
                                    <td id="detail_tenor"></td>
                                </tr>
                                <tr>
                                    <th>Bunga</th>
                                    <td id="detail_bunga"></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="detail_status"></td>
                                </tr>
                                <tr>
                                    <th>Tanggal Persetujuan</th>
                                    <td id="detail_tanggal"></td>
                                </tr>
                                <tr>
                                    <th>Tanggal Pencairan</th>
                                    <td id="detail_tanggal_pencairan"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tujuan Pinjaman</label>
                                <div id="detail_tujuan" class="p-3 bg-light rounded"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Catatan Admin</label>
                                <div id="detail_catatan" class="p-2 bg-light rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Proses Pencairan Modal -->
    <div class="modal fade" id="pencairanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-hand-holding-usd me-2"></i>Proses Pencairan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="pencairan_id">
                        <div class="alert alert-warning">
                            <strong id="pencairan_nama"></strong><br>
                            Kode: <span id="pencairan_kode"></span><br>
                            Jumlah: <strong id="pencairan_jumlah"></strong><br>
                            <small id="pencairan_ringkas"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Foto Nasabah (saat serah terima dana) <span class="text-danger">*</span></label>
                            <input type="file" name="foto_nasabah" id="foto_nasabah" class="form-control" required
                                   accept="image/*" capture="environment">
                            <small class="text-muted">JPG/PNG/WEBP, maksimal 10 MB. Di HP bisa langsung memotret.</small>
                            <img id="foto_nasabah_preview" class="img-thumbnail mt-2 d-none" style="max-height:160px" alt="Pratinjau foto">
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Jadwal angsuran dibuat otomatis dengan jatuh tempo dihitung dari <strong>hari ini</strong> (tanggal pencairan).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="proses_pencairan" class="btn btn-primary">
                            <i class="fas fa-check-circle me-1"></i> Cairkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include __DIR__ . '/../includes/modal_dokumen.php'; ?>
    <script>
        var pencairanModal = document.getElementById('pencairanModal');
        pencairanModal.addEventListener('show.bs.modal', function (event) {
            var b = event.relatedTarget;
            document.getElementById('pencairan_id').value = b.getAttribute('data-id');
            document.getElementById('pencairan_nama').textContent = b.getAttribute('data-nama');
            document.getElementById('pencairan_kode').textContent = b.getAttribute('data-kode');
            document.getElementById('pencairan_jumlah').textContent = 'Rp ' + Number(b.getAttribute('data-jumlah')).toLocaleString('id-ID');
            document.getElementById('pencairan_ringkas').textContent = b.getAttribute('data-ringkas');
            document.getElementById('foto_nasabah').value = '';
            document.getElementById('foto_nasabah_preview').classList.add('d-none');
        });
        document.getElementById('foto_nasabah').addEventListener('change', function () {
            var img = document.getElementById('foto_nasabah_preview');
            if (this.files.length) {
                img.src = URL.createObjectURL(this.files[0]);
                img.classList.remove('d-none');
            } else {
                img.classList.add('d-none');
            }
        });
    </script>
    <script>
        // Detail Modal
        var detailModal = document.getElementById('detailModal');
        detailModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('detail_kode').textContent = button.getAttribute('data-kode');
            document.getElementById('detail_kode_pinjaman').textContent = button.getAttribute('data-kode_pinjaman');
            document.getElementById('detail_nama').textContent = button.getAttribute('data-nama');
            document.getElementById('detail_kode_anggota').textContent = button.getAttribute('data-kode_anggota');
            document.getElementById('detail_jenis').textContent = button.getAttribute('data-jenis');
            document.getElementById('detail_jumlah').textContent = 'Rp ' + Number(button.getAttribute('data-jumlah')).toLocaleString('id-ID');
            document.getElementById('detail_tenor').textContent = button.getAttribute('data-tenor') + ' bulan';
            document.getElementById('detail_bunga').textContent = button.getAttribute('data-bunga');
            document.getElementById('detail_admin').textContent = button.getAttribute('data-admin');
            document.getElementById('detail_tanggal_pencairan').textContent = button.getAttribute('data-tanggal_pencairan');
            
            var status = button.getAttribute('data-status');
            var statusClass = {
                'menunggu': 'warning',
                'disetujui': 'success',
                'dicairkan': 'info',
                'ditolak': 'danger',
                'develop': 'success'
            };
            document.getElementById('detail_status').innerHTML = '<span class="badge bg-' + statusClass[status] + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
            
            document.getElementById('detail_tujuan').textContent = button.getAttribute('data-tujuan') || '-';
            document.getElementById('detail_tanggal').textContent = button.getAttribute('data-tanggal');
            document.getElementById('detail_catatan').textContent = button.getAttribute('data-catatan') || '-';
        });

        // Auto close alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-success'); // pesan error tetap tampil sampai ditutup
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>