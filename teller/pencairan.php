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

// PERBAIKAN: Hanya query untuk view, tidak ada aksi pencairan
$query = "SELECT pp.*, a.nama_lengkap, a.kode_anggota, jp.nama_jenis, 
          jp.maksimal_pinjaman, u.nama_lengkap as admin_name,
          p.id as pinjaman_id, p.kode_pinjaman, p.tanggal_pencairan, p.status as status_pinjaman
          FROM pengajuan_pinjaman pp
          JOIN anggota a ON pp.anggota_id = a.id
          JOIN jenis_pinjaman jp ON pp.jenis_pinjaman_id = jp.id
          LEFT JOIN users u ON pp.admin_id = u.id
          LEFT JOIN pinjaman p ON pp.id = p.pengajuan_id
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
                            <p class="mb-0">Halaman ini hanya menampilkan status pencairan pinjaman. 
                            <strong>Hanya Administrator yang berhak melakukan pencairan pinjaman.</strong></p>
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
                                            if ($pengajuan['status_pinjaman'] == 'aktif') {
                                                $status = 'dicairkan';
                                            }
                                            $status_class = [
                                                'menunggu' => 'warning',
                                                'disetujui' => 'success',
                                                'dicairkan' => 'info',
                                                'ditolak' => 'danger',
                                                'develop' => 'success'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$status] ?? 'secondary'; ?> status-badge">
                                                <?php echo ucfirst($status); ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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