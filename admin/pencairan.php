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

// Check if user has access to this page
if (!isAdmin() && !isTeller()) {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini";
    header("Location: dashboard.php");
    exit();
}

$page_title = "Pencairan Pinjaman";

// ============================================
// SINKRONISASI DATA
// ============================================
if (isset($_GET['sync'])) {
    try {
        $db->beginTransaction();
        
        // Update status pengajuan yang sudah ada di tabel pinjaman menjadi 'dicairkan'
        $query_sync = "UPDATE pengajuan_pinjaman pp 
                       JOIN pinjaman p ON pp.id = p.pengajuan_id 
                       SET pp.status = 'dicairkan' 
                       WHERE pp.status != 'dicairkan'";
        $stmt_sync = $db->prepare($query_sync);
        $stmt_sync->execute();
        
        $affected = $stmt_sync->rowCount();
        
        $db->commit();
        $_SESSION['success'] = "Sinkronisasi data berhasil. $affected data diupdate.";
        header("Location: pencairan.php");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal sinkronisasi: " . $e->getMessage();
    }
}

// Handle disbursement
if ($_POST && isset($_POST['cairkan'])) {
    $id = $_POST['id'];
    
    try {
        $db->beginTransaction();
        
        // Get approved loan application
        $query_get = "SELECT * FROM pengajuan_pinjaman WHERE id = :id AND status = 'disetujui'";
        $stmt_get = $db->prepare($query_get);
        $stmt_get->bindParam(':id', $id);
        $stmt_get->execute();
        $pengajuan = $stmt_get->fetch(PDO::FETCH_ASSOC);
        
        if ($pengajuan) {
            // Update status to dicairkan
            $query_update = "UPDATE pengajuan_pinjaman SET status = 'dicairkan' WHERE id = :id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':id', $id);
            $stmt_update->execute();
            
            // Buat record pinjaman setelah pencairan
            $kode_pinjaman = 'PJN-' . date('Ymd') . '-' . str_pad($pengajuan['id'], 4, '0', STR_PAD_LEFT);
            
            // Hitung total pinjaman dengan bunga
            $jumlah_pinjaman = $pengajuan['jumlah_pinjaman'];
            $bunga = $pengajuan['bunga'];
            $tenor = $pengajuan['tenor'];
            
            // Rumus bunga per tahun
            $bunga_per_tahun = $bunga / 100;
            $bunga_total = $jumlah_pinjaman * $bunga_per_tahun * ($tenor / 12);
            $total_pinjaman = $jumlah_pinjaman + $bunga_total;
            $angsuran_per_bulan = $total_pinjaman / $tenor;
            
            // Cek apakah pinjaman sudah ada
            $query_cek_pinjaman = "SELECT id FROM pinjaman WHERE pengajuan_id = :pengajuan_id";
            $stmt_cek = $db->prepare($query_cek_pinjaman);
            $stmt_cek->bindParam(':pengajuan_id', $id);
            $stmt_cek->execute();
            
            if ($stmt_cek->rowCount() == 0) {
                // Insert ke tabel pinjaman
                $query_pinjaman = "INSERT INTO pinjaman 
                    (pengajuan_id, kode_pinjaman, anggota_id, jenis_pinjaman_id, 
                    jumlah_pinjaman, sisa_pokok, tenor, bunga, total_pinjaman, 
                    angsuran_per_bulan, tanggal_pencairan, jatuh_tempo, status) 
                    VALUES 
                    (:pengajuan_id, :kode_pinjaman, :anggota_id, :jenis_pinjaman_id,
                    :jumlah_pinjaman, :sisa_pokok, :tenor, :bunga, :total_pinjaman,
                    :angsuran_per_bulan, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :tenor MONTH), 'aktif')";
                
                $stmt_pinjaman = $db->prepare($query_pinjaman);
                $stmt_pinjaman->bindParam(':pengajuan_id', $id);
                $stmt_pinjaman->bindParam(':kode_pinjaman', $kode_pinjaman);
                $stmt_pinjaman->bindParam(':anggota_id', $pengajuan['anggota_id']);
                $stmt_pinjaman->bindParam(':jenis_pinjaman_id', $pengajuan['jenis_pinjaman_id']);
                $stmt_pinjaman->bindParam(':jumlah_pinjaman', $jumlah_pinjaman);
                $stmt_pinjaman->bindParam(':sisa_pokok', $jumlah_pinjaman);
                $stmt_pinjaman->bindParam(':tenor', $tenor);
                $stmt_pinjaman->bindParam(':bunga', $bunga);
                $stmt_pinjaman->bindParam(':total_pinjaman', $total_pinjaman);
                $stmt_pinjaman->bindParam(':angsuran_per_bulan', $angsuran_per_bulan);
                $stmt_pinjaman->execute();
                
                $pinjaman_id = $db->lastInsertId();
                
                // Buat jadwal angsuran
                $pokok_per_bulan = $jumlah_pinjaman / $tenor;
                $bunga_per_bulan = $bunga_total / $tenor;
                
                for ($i = 1; $i <= $tenor; $i++) {
                    $query_angsuran = "INSERT INTO angsuran 
                        (pinjaman_id, angsuran_ke, jumlah_angsuran, pokok, bunga, 
                        tanggal_jatuh_tempo, status) 
                        VALUES 
                        (:pinjaman_id, :angsuran_ke, :jumlah_angsuran, :pokok, :bunga,
                        DATE_ADD(CURDATE(), INTERVAL :angsuran_ke MONTH), 'belum_bayar')";
                    
                    $stmt_angsuran = $db->prepare($query_angsuran);
                    $stmt_angsuran->bindParam(':pinjaman_id', $pinjaman_id);
                    $stmt_angsuran->bindParam(':angsuran_ke', $i);
                    $stmt_angsuran->bindParam(':jumlah_angsuran', $angsuran_per_bulan);
                    $stmt_angsuran->bindParam(':pokok', $pokok_per_bulan);
                    $stmt_angsuran->bindParam(':bunga', $bunga_per_bulan);
                    $stmt_angsuran->execute();
                }
            }
            
            $db->commit();
            $_SESSION['success'] = "Pinjaman berhasil dicairkan dan jadwal angsuran telah dibuat";
            
            // Redirect untuk refresh data
            header("Location: pencairan.php");
            exit();
        } else {
            throw new Exception("Pengajuan tidak ditemukan atau belum disetujui");
        }
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal mencairkan pinjaman: " . $e->getMessage();
    }
}

// ============================================
// AMBIL DATA PENGUJIAN YANG BENAR-BENAR SIAP DICAIRKAN
// ============================================

// Ambil semua pengajuan yang statusnya 'disetujui' 
// TAPI belum memiliki data di tabel pinjaman
$query = "SELECT pp.*, a.nama_lengkap, a.kode_anggota, jp.nama_jenis, jp.maksimal_pinjaman, u.nama_lengkap as admin_name
          FROM pengajuan_pinjaman pp
          JOIN anggota a ON pp.anggota_id = a.id
          JOIN jenis_pinjaman jp ON pp.jenis_pinjaman_id = jp.id
          LEFT JOIN users u ON pp.admin_id = u.id
          WHERE pp.status = 'disetujui'
          AND pp.id NOT IN (SELECT pengajuan_id FROM pinjaman WHERE pengajuan_id IS NOT NULL)
          ORDER BY pp.tanggal_persetujuan DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pengajuan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get disbursed loans dengan JOIN lengkap
$query_dicairkan = "SELECT p.*, a.nama_lengkap, a.kode_anggota, jp.nama_jenis
                   FROM pinjaman p
                   LEFT JOIN anggota a ON p.anggota_id = a.id
                   LEFT JOIN jenis_pinjaman jp ON p.jenis_pinjaman_id = jp.id
                   ORDER BY p.tanggal_pencairan DESC";
$stmt_dicairkan = $db->prepare($query_dicairkan);
$stmt_dicairkan->execute();
$dicairkan_list = $stmt_dicairkan->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// STATISTIK YANG AKURAT
// ============================================

// Hitung total pengajuan
$query_stats_pengajuan = "SELECT 
    COUNT(*) as total_pengajuan,
    COUNT(CASE WHEN status = 'menunggu' THEN 1 END) as menunggu,
    COUNT(CASE WHEN status = 'disetujui' THEN 1 END) as disetujui,
    COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as ditolak
    FROM pengajuan_pinjaman";
$stmt_stats_pengajuan = $db->prepare($query_stats_pengajuan);
$stmt_stats_pengajuan->execute();
$stats_pengajuan = $stmt_stats_pengajuan->fetch(PDO::FETCH_ASSOC);

// Hitung jumlah pinjaman yang sudah dicairkan
$query_jumlah_dicairkan = "SELECT COUNT(*) as total FROM pinjaman";
$stmt_jumlah_dicairkan = $db->prepare($query_jumlah_dicairkan);
$stmt_jumlah_dicairkan->execute();
$jumlah_dicairkan = $stmt_jumlah_dicairkan->fetch(PDO::FETCH_ASSOC)['total'];

// Hitung pengajuan yang benar-benar siap dicairkan (disetujui tapi belum ada di pinjaman)
$query_siap_dicairkan = "SELECT COUNT(*) as total FROM pengajuan_pinjaman 
                         WHERE status = 'disetujui' 
                         AND id NOT IN (SELECT pengajuan_id FROM pinjaman)";
$stmt_siap_dicairkan = $db->prepare($query_siap_dicairkan);
$stmt_siap_dicairkan->execute();
$siap_dicairkan = $stmt_siap_dicairkan->fetch(PDO::FETCH_ASSOC)['total'];

// Gabungkan statistik
$stats = [
    'total_pengajuan' => $stats_pengajuan['total_pengajuan'],
    'menunggu' => $stats_pengajuan['menunggu'],
    'disetujui' => $siap_dicairkan, // Hanya yang benar-benar siap dicairkan
    'dicairkan' => $jumlah_dicairkan,
    'ditolak' => $stats_pengajuan['ditolak']
];

// Hitung total nominal
$total_nominal_dicairkan = 0;
foreach ($dicairkan_list as $d) {
    $total_nominal_dicairkan += $d['jumlah_pinjaman'];
}

// Fungsi format rupiah
function formatRupiah($angka) {
    if ($angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencairan Pinjaman - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card { 
            border: none; 
            border-radius: 10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .btn-sync {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .btn-sync:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46a1 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-money-bill-wave me-2 text-primary"></i>Pencairan Pinjaman
                    </h1>
                    <div>
                        <a href="?sync=1" class="btn btn-sync me-2" onclick="return confirm('Sinkronisasi data pengajuan dengan pinjaman?')">
                            <i class="fas fa-sync me-1"></i> Sinkronisasi
                        </a>
                        <a href="persetujuan.php" class="btn btn-primary">
                            <i class="fas fa-check-circle me-1"></i> Persetujuan
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
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
                                <h4 class="mb-0"><?php echo $stats['total_pengajuan']; ?></h4>
                                <p class="mb-0">Total Pengajuan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <h4 class="mb-0"><?php echo $stats['menunggu']; ?></h4>
                                <p class="mb-0">Menunggu</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h4 class="mb-0"><?php echo $stats['disetujui']; ?></h4>
                                <p class="mb-0">Siap Dicairkan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h4 class="mb-0"><?php echo $stats['dicairkan']; ?></h4>
                                <p class="mb-0">Sudah Dicairkan</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Card -->
                <?php if ($total_nominal_dicairkan > 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Total Pinjaman Dicairkan:</strong> <?php echo formatRupiah($total_nominal_dicairkan); ?> 
                    (<?php echo $stats['dicairkan']; ?> pinjaman)
                </div>
                <?php endif; ?>

                <!-- Pengajuan Siap Dicairkan -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Pengajuan Siap Dicairkan
                            <span class="badge bg-light text-dark ms-2"><?php echo count($pengajuan_list); ?> Data</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pengajuan_list)): ?>
                            <p class="text-center text-muted py-3">Tidak ada pengajuan yang siap dicairkan</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Anggota</th>
                                            <th>Jenis</th>
                                            <th>Jumlah</th>
                                            <th>Tenor</th>
                                            <th>Bunga</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pengajuan_list as $p): ?>
                                        <tr>
                                            <td><small><?php echo $p['kode_pengajuan']; ?></small></td>
                                            <td>
                                                <strong><?php echo $p['nama_lengkap']; ?></strong>
                                                <br><small class="text-muted"><?php echo $p['kode_anggota']; ?></small>
                                            </td>
                                            <td><?php echo $p['nama_jenis']; ?></td>
                                            <td class="fw-bold text-primary"><?php echo formatRupiah($p['jumlah_pinjaman']); ?></td>
                                            <td><?php echo $p['tenor']; ?> bulan</td>
                                            <td><?php echo $p['bunga']; ?>%</td>
                                            <td>
                                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                        data-bs-target="#cairkanModal" 
                                                        data-id="<?php echo $p['id']; ?>"
                                                        data-nama="<?php echo $p['nama_lengkap']; ?>"
                                                        data-kode="<?php echo $p['kode_pengajuan']; ?>"
                                                        data-jumlah="<?php echo $p['jumlah_pinjaman']; ?>">
                                                    <i class="fas fa-money-bill-wave me-1"></i>Cairkan
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

                <!-- Pinjaman yang Sudah Dicairkan -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Pinjaman yang Sudah Dicairkan
                            <span class="badge bg-light text-dark ms-2"><?php echo count($dicairkan_list); ?> Data</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dicairkan_list)): ?>
                            <p class="text-center text-muted py-3">Belum ada pinjaman yang dicairkan</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Pinjaman</th>
                                            <th>Anggota</th>
                                            <th>Jenis</th>
                                            <th>Jumlah</th>
                                            <th>Angsuran/Bulan</th>
                                            <th>Tenor</th>
                                            <th>Tanggal Cair</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dicairkan_list as $p): ?>
                                        <tr>
                                            <td><small><?php echo $p['kode_pinjaman']; ?></small></td>
                                            <td>
                                                <strong><?php echo $p['nama_lengkap']; ?></strong>
                                                <br><small class="text-muted"><?php echo $p['kode_anggota']; ?></small>
                                            </td>
                                            <td><?php echo $p['nama_jenis']; ?></td>
                                            <td class="fw-bold text-primary"><?php echo formatRupiah($p['jumlah_pinjaman']); ?></td>
                                            <td class="text-info"><?php echo formatRupiah($p['angsuran_per_bulan']); ?></td>
                                            <td><?php echo $p['tenor']; ?> bulan</td>
                                            <td><?php echo date('d/m/Y', strtotime($p['tanggal_pencairan'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $p['status'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($p['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Cairkan Modal -->
    <div class="modal fade" id="cairkanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Konfirmasi Pencairan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="cairkan_id">
                        <p>Yakin ingin mencairkan pinjaman ini?</p>
                        <div class="alert alert-warning">
                            <strong id="cairkan_nama"></strong><br>
                            Kode: <span id="cairkan_kode"></span><br>
                            Jumlah: <strong id="cairkan_jumlah"></strong>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            Jadwal angsuran akan dibuat otomatis.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="cairkan" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i> Cairkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var cairkanModal = document.getElementById('cairkanModal');
        cairkanModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('cairkan_id').value = button.getAttribute('data-id');
            document.getElementById('cairkan_nama').textContent = button.getAttribute('data-nama');
            document.getElementById('cairkan_kode').textContent = button.getAttribute('data-kode');
            document.getElementById('cairkan_jumlah').textContent = 'Rp ' + Number(button.getAttribute('data-jumlah')).toLocaleString('id-ID');
        });
    </script>
</body>
</html>