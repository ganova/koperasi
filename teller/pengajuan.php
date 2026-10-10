<?php
// Start session jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include config dan auth dengan path yang benar
require_once '../config/config.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Pastikan hanya teller yang bisa akses
if (!isTeller()) {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini";
    header("Location: ../dashboard.php");
    exit();
}

// Handle form submission untuk pengajuan baru
if ($_POST && isset($_POST['ajukan_pinjaman'])) {
    $anggota_id = $_POST['anggota_id'];
    $jenis_pinjaman_id = $_POST['jenis_pinjaman_id'];
    $jumlah_pinjaman = $_POST['jumlah_pinjaman'];
    $tenor = $_POST['tenor'];
    $tujuan_pinjaman = $_POST['tujuan_pinjaman'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $db->beginTransaction();
        
        // Get jenis pinjaman info for validation
        $query_jenis = "SELECT maksimal_pinjaman, tenor_max, bunga FROM jenis_pinjaman WHERE id = :jenis_id AND status = 'aktif'";
        $stmt_jenis = $db->prepare($query_jenis);
        $stmt_jenis->bindParam(':jenis_id', $jenis_pinjaman_id);
        $stmt_jenis->execute();
        $jenis_info = $stmt_jenis->fetch(PDO::FETCH_ASSOC);
        
        if (!$jenis_info) {
            throw new Exception("Jenis pinjaman tidak valid atau tidak aktif");
        }
        
        $jumlah_pinjaman = parseRupiah($jumlah_pinjaman);
        if ($jumlah_pinjaman === false || $jumlah_pinjaman <= 0) {
            throw new Exception("Jumlah pinjaman harus berupa angka rupiah penuh lebih dari 0");
        }

        // Validate maksimal pinjaman
        if ($jumlah_pinjaman > $jenis_info['maksimal_pinjaman']) {
            throw new Exception("Jumlah pinjaman melebihi batas maksimal. Maksimal: " . formatRupiah($jenis_info['maksimal_pinjaman']));
        }
        
        // Validate tenor
        if ($tenor > $jenis_info['tenor_max']) {
            throw new Exception("Tenor melebihi batas maksimal. Maksimal: " . $jenis_info['tenor_max'] . " bulan");
        }
        
        // Generate kode pengajuan
        $kode_pengajuan = 'PJG-' . date('YmdHis') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Insert pengajuan
        $query = "INSERT INTO pengajuan_pinjaman (kode_pengajuan, anggota_id, jenis_pinjaman_id, 
                  jumlah_pinjaman, tenor, bunga, tujuan_pinjaman, user_id) 
                  VALUES (:kode_pengajuan, :anggota_id, :jenis_pinjaman_id, 
                  :jumlah_pinjaman, :tenor, :bunga, :tujuan_pinjaman, :user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':kode_pengajuan', $kode_pengajuan);
        $stmt->bindParam(':anggota_id', $anggota_id);
        $stmt->bindParam(':jenis_pinjaman_id', $jenis_pinjaman_id);
        $stmt->bindParam(':jumlah_pinjaman', $jumlah_pinjaman);
        $stmt->bindParam(':tenor', $tenor);
        $stmt->bindParam(':bunga', $jenis_info['bunga']);
        $stmt->bindParam(':tujuan_pinjaman', $tujuan_pinjaman);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $db->commit();
        $_SESSION['success'] = "Pengajuan pinjaman berhasil dibuat. Kode: " . $kode_pengajuan . " - Menunggu persetujuan admin";
        header("Location: pengajuan.php");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal mengajukan pinjaman: " . $e->getMessage();
    }
}

// Get data for dropdowns
$query_anggota = "SELECT * FROM anggota WHERE status = 'aktif' ORDER BY nama_lengkap";
$stmt_anggota = $db->prepare($query_anggota);
$stmt_anggota->execute();
$anggota_list = $stmt_anggota->fetchAll(PDO::FETCH_ASSOC);

$query_jenis = "SELECT * FROM jenis_pinjaman WHERE status = 'aktif' ORDER BY nama_jenis";
$stmt_jenis = $db->prepare($query_jenis);
$stmt_jenis->execute();
$jenis_pinjaman = $stmt_jenis->fetchAll(PDO::FETCH_ASSOC);

// Get pengajuan yang dibuat oleh teller ini
$query_pengajuan = "SELECT 
                    pp.*, 
                    a.nama_lengkap, 
                    a.kode_anggota, 
                    jp.nama_jenis, 
                    jp.maksimal_pinjaman, 
                    u.nama_lengkap as admin_name,
                    pp.tanggal_pengajuan,
                    pp.tanggal_persetujuan
                    FROM pengajuan_pinjaman pp
                    JOIN anggota a ON pp.anggota_id = a.id
                    JOIN jenis_pinjaman jp ON pp.jenis_pinjaman_id = jp.id
                    LEFT JOIN users u ON pp.admin_id = u.id
                    WHERE pp.user_id = :user_id
                    ORDER BY pp.tanggal_pengajuan DESC";

$stmt_pengajuan = $db->prepare($query_pengajuan);
$stmt_pengajuan->bindParam(':user_id', $_SESSION['user_id']);
$stmt_pengajuan->execute();
$pengajuan_list = $stmt_pengajuan->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if data is retrieved
error_log("Pengajuan found: " . count($pengajuan_list));
if (count($pengajuan_list) > 0) {
    error_log("First pengajuan: " . print_r($pengajuan_list[0], true));
}

// Get statistics untuk teller
$query_stats = "SELECT 
    COUNT(*) as total_pengajuan,
    COUNT(CASE WHEN status = 'menunggu' THEN 1 END) as menunggu,
    COUNT(CASE WHEN status = 'disetujui' THEN 1 END) as disetujui,
    COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as ditolak
    FROM pengajuan_pinjaman 
    WHERE user_id = :user_id";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->bindParam(':user_id', $_SESSION['user_id']);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Pinjaman - <?php echo APP_NAME; ?></title>
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
        .form-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .calculation-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .pengajuan-table th {
            background-color: #f8f9fa;
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
                        <i class="fas fa-file-alt me-2"></i>Pengajuan Pinjaman
                    </h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

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
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['ditolak']; ?></h4>
                                        <p>Ditolak</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Form Pengajuan -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>Form Pengajuan Pinjaman Baru
                                </h5>
                            </div>
                            <div class="card-body form-card">
                                <form method="POST" id="formPengajuan">
                                    <div class="mb-3">
                                        <label class="form-label">Pilih Anggota *</label>
                                        <select name="anggota_id" class="form-select" required id="anggotaSelect">
                                            <option value="">-- Pilih Anggota --</option>
                                            <?php foreach ($anggota_list as $anggota): ?>
                                            <option value="<?php echo $anggota['id']; ?>">
                                                <?php echo $anggota['kode_anggota'] . ' - ' . $anggota['nama_lengkap']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Jenis Pinjaman *</label>
                                        <select name="jenis_pinjaman_id" class="form-select" required id="jenisPinjamanSelect">
                                            <option value="">-- Pilih Jenis Pinjaman --</option>
                                            <?php foreach ($jenis_pinjaman as $jenis): ?>
                                            <option value="<?php echo $jenis['id']; ?>" 
                                                    data-maksimal="<?php echo $jenis['maksimal_pinjaman']; ?>"
                                                    data-tenor="<?php echo $jenis['tenor_max']; ?>"
                                                    data-bunga="<?php echo $jenis['bunga']; ?>">
                                                <?php echo $jenis['nama_jenis']; ?>
                                                (Maks: <?php echo formatRupiah($jenis['maksimal_pinjaman']); ?> | 
                                                Tenor: <?php echo $jenis['tenor_max']; ?> bln)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Jumlah Pinjaman (Rp) *</label>
                                                <input type="number" name="jumlah_pinjaman" class="form-control" 
                                                       required step="1" min="1" id="jumlahPinjaman"
                                                       placeholder="Masukkan jumlah pinjaman">
                                                <small class="text-muted" id="maxInfo"></small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Tenor (Bulan) *</label>
                                                <select name="tenor" class="form-select" required id="tenorSelect">
                                                    <option value="">-- Pilih Tenor --</option>
                                                    <?php for ($i = 1; $i <= 36; $i++): ?>
                                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> bulan</option>
                                                    <?php endfor; ?>
                                                </select>
                                                <small class="text-muted" id="tenorInfo"></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tujuan Pinjaman *</label>
                                        <textarea name="tujuan_pinjaman" class="form-control" rows="3" required
                                                  placeholder="Jelaskan tujuan penggunaan pinjaman..."></textarea>
                                    </div>
                                    
                                    <!-- Informasi Kalkulasi -->
                                    <div class="calculation-card p-3 mb-3" id="calculationInfo" style="display: none;">
                                        <h6 class="text-white mb-3">Perhitungan Pinjaman</h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small>Bunga</small>
                                                <div class="h5 fw-bold" id="displayBunga">0%</div>
                                            </div>
                                            <div class="col-4">
                                                <small>Total Pinjaman</small>
                                                <div class="h5 fw-bold" id="displayTotal">Rp 0</div>
                                            </div>
                                            <div class="col-4">
                                                <small>Angsuran/Bulan</small>
                                                <div class="h5 fw-bold" id="displayAngsuran">Rp 0</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Pengajuan pinjaman akan dikirim untuk persetujuan admin. Proses persetujuan membutuhkan waktu 1-2 hari kerja.
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="ajukan_pinjaman" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i> Ajukan Pinjaman
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Pengajuan -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Riwayat Pengajuan Saya
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo count($pengajuan_list); ?> pengajuan
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pengajuan_list)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada pengajuan</h5>
                                    <p class="text-muted">Ajukan pinjaman pertama untuk anggota</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm pengajuan-table">
                                        <thead>
                                            <tr>
                                                <th>Kode</th>
                                                <th>Anggota</th>
                                                <th>Jenis</th>
                                                <th>Jumlah</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pengajuan_list as $pengajuan): ?>
                                            <tr>
                                                <td>
                                                    <small class="fw-bold"><?php echo $pengajuan['kode_pengajuan']; ?></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($pengajuan['tanggal_pengajuan'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo $pengajuan['nama_lengkap']; ?></div>
                                                    <small class="text-muted"><?php echo $pengajuan['kode_anggota']; ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo $pengajuan['nama_jenis']; ?></small>
                                                    <br>
                                                    <small class="text-muted"><?php echo $pengajuan['tenor']; ?> bln</small>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-primary"><?php echo formatRupiah($pengajuan['jumlah_pinjaman']); ?></div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'menunggu' => 'warning',
                                                        'disetujui' => 'success',
                                                        'ditolak' => 'danger',
                                                        'dicairkan' => 'info'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class[$pengajuan['status']]; ?> status-badge">
                                                        <?php echo ucfirst($pengajuan['status']); ?>
                                                    </span>
                                                    <?php if ($pengajuan['admin_name']): ?>
                                                        <br>
                                                        <small class="text-muted">Oleh: <?php echo $pengajuan['admin_name']; ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($pengajuan['tanggal_persetujuan']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y', strtotime($pengajuan['tanggal_persetujuan'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="table-actions">
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                            data-bs-target="#detailModal" 
                                                            data-kode="<?php echo $pengajuan['kode_pengajuan']; ?>"
                                                            data-nama="<?php echo $pengajuan['nama_lengkap']; ?>"
                                                            data-kode_anggota="<?php echo $pengajuan['kode_anggota']; ?>"
                                                            data-jenis="<?php echo $pengajuan['nama_jenis']; ?>"
                                                            data-jumlah="<?php echo $pengajuan['jumlah_pinjaman']; ?>"
                                                            data-tenor="<?php echo $pengajuan['tenor']; ?>"
                                                            data-bunga="<?php echo $pengajuan['bunga']; ?>"
                                                            data-tujuan="<?php echo $pengajuan['tujuan_pinjaman']; ?>"
                                                            data-status="<?php echo $pengajuan['status']; ?>"
                                                            data-tanggal="<?php echo date('d/m/Y H:i', strtotime($pengajuan['tanggal_pengajuan'])); ?>"
                                                            data-catatan="<?php echo $pengajuan['catatan_admin']; ?>"
                                                            data-admin="<?php echo $pengajuan['admin_name']; ?>"
                                                            data-tanggal_persetujuan="<?php echo $pengajuan['tanggal_persetujuan'] ? date('d/m/Y H:i', strtotime($pengajuan['tanggal_persetujuan'])) : ''; ?>"
                                                            title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tanggal Pengajuan</label>
                                <div id="detail_tanggal" class="p-2 bg-light rounded"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Diajukan Oleh</label>
                                <div class="p-2 bg-light rounded"><?php echo $_SESSION['nama_lengkap']; ?> (Teller)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($pengajuan['admin_name']): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Disetujui/Ditolak Oleh</label>
                                <div id="detail_admin" class="p-2 bg-light rounded"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tanggal Persetujuan</label>
                                <div id="detail_tanggal_persetujuan" class="p-2 bg-light rounded"></div>
                            </div>
                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update informasi jenis pinjaman
        document.getElementById('jenisPinjamanSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const maksimal = selectedOption.getAttribute('data-maksimal');
            const tenorMax = selectedOption.getAttribute('data-tenor');
            const bunga = selectedOption.getAttribute('data-bunga');
            
            document.getElementById('maxInfo').textContent = 'Maksimal: Rp ' + parseInt(maksimal).toLocaleString('id-ID');
            document.getElementById('tenorInfo').textContent = 'Maksimal tenor: ' + tenorMax + ' bulan';
            
            // Update tenor options
            const tenorSelect = document.getElementById('tenorSelect');
            tenorSelect.innerHTML = '<option value="">-- Pilih Tenor --</option>';
            for (let i = 1; i <= parseInt(tenorMax); i++) {
                tenorSelect.innerHTML += `<option value="${i}">${i} bulan</option>`;
            }
            
            updateCalculation();
        });

        // Update calculation when amount or tenor changes
        document.getElementById('jumlahPinjaman').addEventListener('input', updateCalculation);
        document.getElementById('tenorSelect').addEventListener('change', updateCalculation);

        function updateCalculation() {
            const selectedOption = document.getElementById('jenisPinjamanSelect').options[document.getElementById('jenisPinjamanSelect').selectedIndex];
            const jumlah = parseInt(document.getElementById('jumlahPinjaman').value) || 0;
            const tenor = parseInt(document.getElementById('tenorSelect').value) || 0;
            const calculationInfo = document.getElementById('calculationInfo');
            
            if (selectedOption && jumlah > 0 && tenor > 0) {
                const bunga = parseFloat(selectedOption.getAttribute('data-bunga'));
                const totalPinjaman = jumlah * (1 + (bunga / 100) * (tenor / 12));
                const angsuranPerBulan = totalPinjaman / tenor;
                
                document.getElementById('displayBunga').textContent = bunga + '%';
                document.getElementById('displayTotal').textContent = 'Rp ' + Math.round(totalPinjaman).toLocaleString('id-ID');
                document.getElementById('displayAngsuran').textContent = 'Rp ' + Math.round(angsuranPerBulan).toLocaleString('id-ID');
                
                calculationInfo.style.display = 'block';
            } else {
                calculationInfo.style.display = 'none';
            }
        }

        // Detail Modal
        var detailModal = document.getElementById('detailModal');
        detailModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('detail_kode').textContent = button.getAttribute('data-kode');
            document.getElementById('detail_nama').textContent = button.getAttribute('data-nama');
            document.getElementById('detail_kode_anggota').textContent = button.getAttribute('data-kode_anggota');
            document.getElementById('detail_jenis').textContent = button.getAttribute('data-jenis');
            document.getElementById('detail_jumlah').textContent = 'Rp ' + Number(button.getAttribute('data-jumlah')).toLocaleString('id-ID');
            document.getElementById('detail_tenor').textContent = button.getAttribute('data-tenor') + ' bulan';
            document.getElementById('detail_bunga').textContent = button.getAttribute('data-bunga') + '%';
            
            var status = button.getAttribute('data-status');
            var statusClass = {
                'menunggu': 'warning',
                'disetujui': 'success',
                'ditolak': 'danger',
                'dicairkan': 'info'
            };
            document.getElementById('detail_status').innerHTML = '<span class="badge bg-' + statusClass[status] + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
            
            document.getElementById('detail_tujuan').textContent = button.getAttribute('data-tujuan') || '-';
            document.getElementById('detail_tanggal').textContent = button.getAttribute('data-tanggal');
            document.getElementById('detail_catatan').textContent = button.getAttribute('data-catatan') || '-';
            document.getElementById('detail_admin').textContent = button.getAttribute('data-admin') || '-';
            document.getElementById('detail_tanggal_persetujuan').textContent = button.getAttribute('data-tanggal_persetujuan') || '-';
        });

        // Form validation
        document.getElementById('formPengajuan').addEventListener('submit', function(e) {
            const selectedOption = document.getElementById('jenisPinjamanSelect').options[document.getElementById('jenisPinjamanSelect').selectedIndex];
            const maksimal = selectedOption ? parseInt(selectedOption.getAttribute('data-maksimal')) : 0;
            const tenorMax = selectedOption ? parseInt(selectedOption.getAttribute('data-tenor')) : 0;
            const jumlah = parseInt(document.getElementById('jumlahPinjaman').value);
            const tenor = parseInt(document.getElementById('tenorSelect').value);
            
            if (jumlah > maksimal) {
                e.preventDefault();
                alert('Jumlah pinjaman melebihi batas maksimal! Maksimal: Rp ' + maksimal.toLocaleString('id-ID'));
                return false;
            }
            
            if (tenor > tenorMax) {
                e.preventDefault();
                alert('Tenor melebihi batas maksimal! Maksimal: ' + tenorMax + ' bulan');
                return false;
            }
            
            return true;
        });

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('anggotaSelect').focus();
        });
    </script>
</body>
</html>