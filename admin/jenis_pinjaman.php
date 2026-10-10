<?php
require_once '../includes/auth.php';
require_once '../config/config.php';

// Handle form actions
if ($_POST) {
    if (isset($_POST['add'])) {
        $kode_jenis = $_POST['kode_jenis'];
        $nama_jenis = $_POST['nama_jenis'];
        $maksimal_pinjaman = $_POST['maksimal_pinjaman'];
        $tenor_max = $_POST['tenor_max'];
        $bunga = $_POST['bunga'];
        $deskripsi = $_POST['deskripsi'];
        
        try {
            $maksimal_pinjaman = parseRupiah($maksimal_pinjaman);
            if ($maksimal_pinjaman === false) {
                throw new Exception("Maksimal pinjaman harus berupa angka rupiah penuh (tanpa desimal)");
            }

            // Check if kode jenis already exists
            $check_query = "SELECT id FROM jenis_pinjaman WHERE kode_jenis = :kode_jenis";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':kode_jenis', $kode_jenis);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Kode jenis sudah digunakan");
            }
            
            $query = "INSERT INTO jenis_pinjaman (kode_jenis, nama_jenis, maksimal_pinjaman, tenor_max, bunga, deskripsi) 
                      VALUES (:kode_jenis, :nama_jenis, :maksimal_pinjaman, :tenor_max, :bunga, :deskripsi)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':kode_jenis', $kode_jenis);
            $stmt->bindParam(':nama_jenis', $nama_jenis);
            $stmt->bindParam(':maksimal_pinjaman', $maksimal_pinjaman);
            $stmt->bindParam(':tenor_max', $tenor_max);
            $stmt->bindParam(':bunga', $bunga);
            $stmt->bindParam(':deskripsi', $deskripsi);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Jenis pinjaman berhasil ditambahkan";
            } else {
                throw new Exception("Gagal menambahkan jenis pinjaman");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: jenis_pinjaman.php");
        exit();
    }
    
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $kode_jenis = $_POST['kode_jenis'];
        $nama_jenis = $_POST['nama_jenis'];
        $maksimal_pinjaman = $_POST['maksimal_pinjaman'];
        $tenor_max = $_POST['tenor_max'];
        $bunga = $_POST['bunga'];
        $deskripsi = $_POST['deskripsi'];
        $status = $_POST['status'];
        
        try {
            $maksimal_pinjaman = parseRupiah($maksimal_pinjaman);
            if ($maksimal_pinjaman === false) {
                throw new Exception("Maksimal pinjaman harus berupa angka rupiah penuh (tanpa desimal)");
            }

            // Check if kode jenis already exists (excluding current record)
            $check_query = "SELECT id FROM jenis_pinjaman WHERE kode_jenis = :kode_jenis AND id != :id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':kode_jenis', $kode_jenis);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Kode jenis sudah digunakan");
            }
            
            $query = "UPDATE jenis_pinjaman SET kode_jenis = :kode_jenis, nama_jenis = :nama_jenis, 
                      maksimal_pinjaman = :maksimal_pinjaman, tenor_max = :tenor_max, bunga = :bunga, 
                      deskripsi = :deskripsi, status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':kode_jenis', $kode_jenis);
            $stmt->bindParam(':nama_jenis', $nama_jenis);
            $stmt->bindParam(':maksimal_pinjaman', $maksimal_pinjaman);
            $stmt->bindParam(':tenor_max', $tenor_max);
            $stmt->bindParam(':bunga', $bunga);
            $stmt->bindParam(':deskripsi', $deskripsi);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Jenis pinjaman berhasil diupdate";
            } else {
                throw new Exception("Gagal mengupdate jenis pinjaman");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: jenis_pinjaman.php");
        exit();
    }
    
    // Handle delete action
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        
        try {
            // Check if this jenis pinjaman is being used in pengajuan_pinjaman table
            $check_pengajuan_query = "SELECT COUNT(*) as total FROM pengajuan_pinjaman WHERE jenis_pinjaman_id = :id";
            $check_pengajuan_stmt = $db->prepare($check_pengajuan_query);
            $check_pengajuan_stmt->bindParam(':id', $id);
            $check_pengajuan_stmt->execute();
            $used_in_pengajuan = $check_pengajuan_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($used_in_pengajuan > 0) {
                throw new Exception("Tidak dapat menghapus jenis pinjaman karena masih digunakan oleh $used_in_pengajuan data pengajuan");
            }
            
            // Check if this jenis pinjaman is being used in pinjaman table
            $check_pinjaman_query = "SELECT COUNT(*) as total FROM pinjaman WHERE jenis_pinjaman_id = :id";
            $check_pinjaman_stmt = $db->prepare($check_pinjaman_query);
            $check_pinjaman_stmt->bindParam(':id', $id);
            $check_pinjaman_stmt->execute();
            $used_in_pinjaman = $check_pinjaman_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($used_in_pinjaman > 0) {
                throw new Exception("Tidak dapat menghapus jenis pinjaman karena masih digunakan oleh $used_in_pinjaman data pinjaman");
            }
            
            $query = "DELETE FROM jenis_pinjaman WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Jenis pinjaman berhasil dihapus";
            } else {
                throw new Exception("Gagal menghapus jenis pinjaman");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: jenis_pinjaman.php");
        exit();
    }
}

// Get all jenis pinjaman
$query = "SELECT * FROM jenis_pinjaman ORDER BY 
          CASE WHEN status = 'aktif' THEN 1 ELSE 2 END, 
          created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$jenis_pinjaman = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query_stats = "SELECT 
    COUNT(*) as total_jenis,
    COUNT(CASE WHEN status = 'aktif' THEN 1 END) as aktif,
    COUNT(CASE WHEN status = 'nonaktif' THEN 1 END) as nonaktif
    FROM jenis_pinjaman";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jenis Pinjaman - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .feature-badge {
            font-size: 0.75rem;
        }
        .btn-delete {
            color: #dc3545;
            border-color: #dc3545;
        }
        .btn-delete:hover {
            background-color: #dc3545;
            color: white;
        }
        .table-actions {
            white-space: nowrap;
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
                        <i class="fas fa-hand-holding-usd me-2"></i>Jenis Pinjaman
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus"></i> Tambah Jenis
                    </button>
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
                    <div class="col-md-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['total_jenis']; ?></h4>
                                        <p class="mb-0">Total Jenis</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-list fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['aktif']; ?></h4>
                                        <p class="mb-0">Jenis Aktif</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['nonaktif']; ?></h4>
                                        <p class="mb-0">Jenis Nonaktif</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jenis Pinjaman Table -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-table me-2 text-primary"></i>Daftar Jenis Pinjaman
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Kode</th>
                                        <th>Nama Jenis</th>
                                        <th>Maksimal Pinjaman</th>
                                        <th>Tenor Max</th>
                                        <th>Bunga</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($jenis_pinjaman)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-hand-holding-usd fa-3x mb-3"></i>
                                            <p class="mb-2">Belum ada jenis pinjaman</p>
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                                                <i class="fas fa-plus me-1"></i> Tambah Jenis Pertama
                                            </button>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($jenis_pinjaman as $index => $jenis): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $jenis['kode_jenis']; ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo $jenis['nama_jenis']; ?></div>
                                                <?php if ($jenis['deskripsi']): ?>
                                                    <small class="text-muted"><?php echo $jenis['deskripsi']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatRupiah($jenis['maksimal_pinjaman']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $jenis['tenor_max']; ?> bulan</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $jenis['bunga']; ?>%</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $jenis['status'] == 'aktif' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($jenis['status']); ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" 
                                                        data-bs-target="#editModal" 
                                                        data-id="<?php echo $jenis['id']; ?>"
                                                        data-kode="<?php echo $jenis['kode_jenis']; ?>"
                                                        data-nama="<?php echo $jenis['nama_jenis']; ?>"
                                                        data-maksimal="<?php echo $jenis['maksimal_pinjaman']; ?>"
                                                        data-tenor="<?php echo $jenis['tenor_max']; ?>"
                                                        data-bunga="<?php echo $jenis['bunga']; ?>"
                                                        data-deskripsi="<?php echo $jenis['deskripsi']; ?>"
                                                        data-status="<?php echo $jenis['status']; ?>"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="confirmDelete(<?php echo $jenis['id']; ?>, '<?php echo $jenis['nama_jenis']; ?>')"
                                                        title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle text-primary me-2"></i>Tambah Jenis Pinjaman
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kode Jenis *</label>
                            <input type="text" name="kode_jenis" class="form-control" required 
                                   placeholder="Contoh: UMUM, USAHA" maxlength="10">
                            <small class="text-muted">Kode unik untuk jenis pinjaman</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jenis *</label>
                            <input type="text" name="nama_jenis" class="form-control" required 
                                   placeholder="Contoh: Pinjaman Umum">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Maksimal Pinjaman (Rp) *</label>
                                    <input type="number" name="maksimal_pinjaman" class="form-control" 
                                           required step="1" min="0" value="1000000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tenor Maksimal (Bulan) *</label>
                                    <input type="number" name="tenor_max" class="form-control" 
                                           required min="1" max="36" value="12">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bunga per Tahun (%) *</label>
                            <input type="number" name="bunga" class="form-control" 
                                   required step="0.01" min="0" max="100" value="12.0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3" 
                                      placeholder="Deskripsi jenis pinjaman"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit text-warning me-2"></i>Edit Jenis Pinjaman
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Kode Jenis *</label>
                            <input type="text" name="kode_jenis" id="edit_kode" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jenis *</label>
                            <input type="text" name="nama_jenis" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Maksimal Pinjaman (Rp) *</label>
                                    <input type="number" name="maksimal_pinjaman" id="edit_maksimal" 
                                           class="form-control" required step="1" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tenor Maksimal (Bulan) *</label>
                                    <input type="number" name="tenor_max" id="edit_tenor" 
                                           class="form-control" required min="1" max="36">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bunga per Tahun (%) *</label>
                            <input type="number" name="bunga" id="edit_bunga" 
                                   class="form-control" required step="0.01" min="0" max="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="delete_id">
                        <input type="hidden" name="delete" value="1">
                        <p class="mb-2">Apakah Anda yakin ingin menghapus jenis pinjaman:</p>
                        <p class="fw-bold text-danger" id="delete_nama"></p>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Tindakan ini tidak dapat dibatalkan dan hanya dapat dilakukan jika jenis pinjaman tidak memiliki data terkait (pengajuan/pinjaman).
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Modal handler
        var editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('edit_id').value = button.getAttribute('data-id');
                document.getElementById('edit_kode').value = button.getAttribute('data-kode');
                document.getElementById('edit_nama').value = button.getAttribute('data-nama');
                document.getElementById('edit_maksimal').value = button.getAttribute('data-maksimal');
                document.getElementById('edit_tenor').value = button.getAttribute('data-tenor');
                document.getElementById('edit_bunga').value = button.getAttribute('data-bunga');
                document.getElementById('edit_deskripsi').value = button.getAttribute('data-deskripsi');
                document.getElementById('edit_status').value = button.getAttribute('data-status');
            });
        }

        // Delete confirmation function
        function confirmDelete(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').innerHTML = '<strong>"' + nama + '"</strong>';
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>