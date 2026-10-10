<?php
require_once '../includes/auth.php';
require_once '../config/config.php';

// Handle form actions
if ($_POST) {
    if (isset($_POST['update_bagi_hasil'])) {
        foreach ($_POST['bagi_hasil'] as $jenis_id => $persentase) {
            $query = "UPDATE jenis_simpanan SET bagi_hasil = :bagi_hasil WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':bagi_hasil', $persentase);
            $stmt->bindParam(':id', $jenis_id);
            $stmt->execute();
        }
        $_SESSION['success'] = "Bagi hasil berhasil diupdate";
    }
    
    if (isset($_POST['update_denda'])) {
        $denda_keterlambatan = $_POST['denda_keterlambatan'];
        $persentase_denda = $_POST['persentase_denda'];
        
        // Simpan ke tabel settings (buat tabel jika belum ada)
        $query = "INSERT INTO settings (setting_key, setting_value) 
                  VALUES ('denda_keterlambatan', :denda_keterlambatan),
                         ('persentase_denda', :persentase_denda)
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':denda_keterlambatan', $denda_keterlambatan);
        $stmt->bindParam(':persentase_denda', $persentase_denda);
        $stmt->execute();
        
        $_SESSION['success'] = "Pengaturan denda berhasil diupdate";
    }
}

// Get jenis simpanan untuk bagi hasil
$query_jenis = "SELECT * FROM jenis_simpanan WHERE status = 'aktif' ORDER BY nama_jenis";
$stmt_jenis = $db->prepare($query_jenis);
$stmt_jenis->execute();
$jenis_simpanan = $stmt_jenis->fetchAll(PDO::FETCH_ASSOC);

// Get pengaturan denda
$query_denda = "SELECT * FROM settings WHERE setting_key IN ('denda_keterlambatan', 'persentase_denda')";
$stmt_denda = $db->prepare($query_denda);
$stmt_denda->execute();
$settings = $stmt_denda->fetchAll(PDO::FETCH_KEY_PAIR);

// Create settings table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Pinjaman & Tabungan - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cog me-2"></i>Pengaturan Pinjaman & Tabungan
                    </h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Bagi Hasil Tabungan -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-percentage me-2"></i>Bagi Hasil Tabungan
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Jenis Simpanan</th>
                                                <th>Bagi Hasil (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($jenis_simpanan as $jenis): ?>
                                            <tr>
                                                <td><?php echo $jenis['nama_jenis']; ?></td>
                                                <td>
                                                    <input type="number" name="bagi_hasil[<?php echo $jenis['id']; ?>]" 
                                                           class="form-control form-control-sm" 
                                                           value="<?php echo $jenis['bagi_hasil']; ?>" 
                                                           step="0.01" min="0" max="100">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="text-end">
                                        <button type="submit" name="update_bagi_hasil" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Update Bagi Hasil
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Pengaturan Denda -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Pengaturan Denda
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Denda Keterlambatan (Rp)</label>
                                        <input type="number" name="denda_keterlambatan" class="form-control" 
                                               value="<?php echo $settings['denda_keterlambatan'] ?? 0; ?>" 
                                               step="1000" min="0">
                                        <small class="text-muted">Jumlah denda tetap untuk keterlambatan pembayaran</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Persentase Denda (%)</label>
                                        <input type="number" name="persentase_denda" class="form-control" 
                                               value="<?php echo $settings['persentase_denda'] ?? 0; ?>" 
                                               step="0.01" min="0" max="100">
                                        <small class="text-muted">Persentase denda dari jumlah pinjaman</small>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" name="update_denda" class="btn btn-warning">
                                            <i class="fas fa-save me-1"></i> Update Denda
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informasi Sistem -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Informasi Sistem
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Bagi Hasil Tabungan:</h6>
                                        <ul>
                                            <li>Bagi hasil dihitung per bulan</li>
                                            <li>Persentase berdasarkan saldo rata-rata</li>
                                            <li>Pembayaran bagi hasil setiap akhir bulan</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Ketentuan Denda:</h6>
                                        <ul>
                                            <li>Denda berlaku untuk keterlambatan > 7 hari</li>
                                            <li>Perhitungan denda harian</li>
                                            <li>Maksimal denda 30% dari pokok pinjaman</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>