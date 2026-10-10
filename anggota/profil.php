<?php
require_once '../includes/auth.php';
checkAnggota();

$anggota_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Debug: cek session
error_log("User ID dari session: " . $anggota_id);

// Get anggota data with related user info
$query_anggota = "SELECT a.*, u.username, u.email, u.password 
                  FROM anggota a 
                  JOIN users u ON a.user_id = u.id 
                  WHERE a.user_id = :user_id";
$stmt_anggota = $db->prepare($query_anggota);
$stmt_anggota->bindParam(':user_id', $anggota_id);
$stmt_anggota->execute();

if ($stmt_anggota->rowCount() == 0) {
    error_log("Data anggota tidak ditemukan untuk user_id: " . $anggota_id);
    $anggota_data = [
        'id' => 0,
        'kode_anggota' => '-',
        'nama_lengkap' => '-',
        'username' => '-',
        'email' => '-',
        'telepon' => '',
        'alamat' => '',
        'tempat_lahir' => '',
        'tanggal_lahir' => null,
        'pekerjaan' => '',
        'ktp' => '',
        'rekening_bank' => '',
        'foto_ktp' => '',
        'status' => 'aktif',
        'tanggal_daftar' => date('Y-m-d')
    ];
} else {
    $anggota_data = $stmt_anggota->fetch(PDO::FETCH_ASSOC);
    error_log("Data anggota ditemukan: " . print_r($anggota_data, true));
}

// Handle form submission for profile update
if ($_POST && isset($_POST['update_profil'])) {
    $telepon = $_POST['telepon'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $email = $_POST['email'] ?? $anggota_data['email'];
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
    $pekerjaan = $_POST['pekerjaan'] ?? '';
    $rekening_bank = $_POST['rekening_bank'] ?? '';
    $ktp = $_POST['ktp'] ?? '';
    
    // Validate tanggal_lahir format if provided
    if (!empty($tanggal_lahir) && !DateTime::createFromFormat('Y-m-d', $tanggal_lahir)) {
        $errors[] = "Format tanggal lahir tidak valid";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update anggota table
            $query_update = "UPDATE anggota SET 
                            telepon = :telepon, 
                            alamat = :alamat,
                            tempat_lahir = :tempat_lahir,
                            tanggal_lahir = :tanggal_lahir,
                            pekerjaan = :pekerjaan,
                            rekening_bank = :rekening_bank,
                            ktp = :ktp
                            WHERE user_id = :user_id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':telepon', $telepon);
            $stmt_update->bindParam(':alamat', $alamat);
            $stmt_update->bindParam(':tempat_lahir', $tempat_lahir);
            $stmt_update->bindParam(':tanggal_lahir', $tanggal_lahir);
            $stmt_update->bindParam(':pekerjaan', $pekerjaan);
            $stmt_update->bindParam(':rekening_bank', $rekening_bank);
            $stmt_update->bindParam(':ktp', $ktp);
            $stmt_update->bindParam(':user_id', $anggota_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal memperbarui data anggota");
            }
            
            // Update email in users table if changed
            if ($email != $anggota_data['email']) {
                // Check if email already exists
                $check_email = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $stmt_check = $db->prepare($check_email);
                $stmt_check->bindParam(':email', $email);
                $stmt_check->bindParam(':user_id', $anggota_id);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() > 0) {
                    throw new Exception("Email sudah digunakan oleh pengguna lain");
                }
                
                $query_email = "UPDATE users SET email = :email WHERE id = :user_id";
                $stmt_email = $db->prepare($query_email);
                $stmt_email->bindParam(':email', $email);
                $stmt_email->bindParam(':user_id', $anggota_id);
                
                if (!$stmt_email->execute()) {
                    throw new Exception("Gagal memperbarui email");
                }
            }
            
            $db->commit();
            $success = "Profil berhasil diperbarui";
            
            // Refresh data
            $stmt_anggota->execute();
            $anggota_data = $stmt_anggota->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Handle KTP photo upload
if ($_POST && isset($_POST['upload_ktp']) && isset($_FILES['foto_ktp'])) {
    $file = $_FILES['foto_ktp'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $upload_dir = '../uploads/ktp/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'ktp_' . $anggota_data['kode_anggota'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old KTP if exists
                if (!empty($anggota_data['foto_ktp']) && file_exists($upload_dir . $anggota_data['foto_ktp'])) {
                    unlink($upload_dir . $anggota_data['foto_ktp']);
                }
                
                $query_ktp = "UPDATE anggota SET foto_ktp = :foto_ktp WHERE user_id = :user_id";
                $stmt_ktp = $db->prepare($query_ktp);
                $stmt_ktp->bindParam(':foto_ktp', $filename);
                $stmt_ktp->bindParam(':user_id', $anggota_id);
                $stmt_ktp->execute();
                
                $success = "Foto KTP berhasil diupload";
                
                // Refresh data
                $stmt_anggota->execute();
                $anggota_data = $stmt_anggota->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = "Gagal mengupload file";
            }
        } else {
            $errors[] = "File harus berupa gambar (JPG/PNG) dan maksimal 2MB";
        }
    } else {
        $errors[] = "Terjadi kesalahan saat upload file";
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "Semua field password harus diisi";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Password baru dan konfirmasi tidak cocok";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password baru minimal 6 karakter";
    } else {
        try {
            // Verify current password
            $query_check = "SELECT password FROM users WHERE id = :user_id";
            $stmt_check = $db->prepare($query_check);
            $stmt_check->bindParam(':user_id', $anggota_id);
            $stmt_check->execute();
            $user = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query_password = "UPDATE users SET password = :password WHERE id = :user_id";
                $stmt_password = $db->prepare($query_password);
                $stmt_password->bindParam(':password', $hashed_password);
                $stmt_password->bindParam(':user_id', $anggota_id);
                $stmt_password->execute();
                
                $success = "Password berhasil diubah";
            } else {
                $errors[] = "Password saat ini salah";
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Fungsi format rupiah (dibiarkan untuk digunakan di tempat lain jika perlu)
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 280px;
            margin-top: 10px;
            padding: 15px;
        }
        .page-header {
            padding-top: 5px;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .profile-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .info-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 10px 0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6c757d;
            font-size: 0.8rem;
            margin-bottom: 2px;
        }
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        .ktp-preview {
            max-height: 200px;
            max-width: 100%;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
        }
        .stat-card {
            border-left: 4px solid #3498db;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            height: 100%;
        }
        .stat-card.simpanan { border-left-color: #27ae60; }
        .stat-card.pinjaman { border-left-color: #f39c12; }
        .stat-card.transaksi { border-left-color: #9b59b6; }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                margin-top: 60px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center page-header border-bottom">
                    <h1 class="h3">
                        <i class="fas fa-user me-2"></i>Profil Saya
                    </h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- STATISTIK CARDS TELAH DIHAPUS -->

                <div class="row">
                    <!-- Data Diri -->
                    <div class="col-lg-8">
                        <div class="card profile-card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-id-card me-2"></i>Data Diri
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Kode Anggota</label>
                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo htmlspecialchars($anggota_data['kode_anggota'] ?? '-'); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Nama Lengkap</label>
                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo htmlspecialchars($anggota_data['nama_lengkap'] ?? '-'); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Username</label>
                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo htmlspecialchars($anggota_data['username'] ?? '-'); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Email <span class="text-danger">*</span></label>
                                                <input type="email" name="email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($anggota_data['email'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">No. KTP</label>
                                                <input type="text" name="ktp" class="form-control form-control-sm" value="<?php echo htmlspecialchars($anggota_data['ktp'] ?? ''); ?>" placeholder="Masukkan nomor KTP">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">No. HP / Telepon</label>
                                                <input type="text" name="telepon" class="form-control form-control-sm" value="<?php echo htmlspecialchars($anggota_data['telepon'] ?? ''); ?>" placeholder="Masukkan nomor telepon">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Tempat Lahir</label>
                                                <input type="text" name="tempat_lahir" class="form-control form-control-sm" value="<?php echo htmlspecialchars($anggota_data['tempat_lahir'] ?? ''); ?>" placeholder="Contoh: Jakarta">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Tanggal Lahir</label>
                                                <input type="date" name="tanggal_lahir" class="form-control form-control-sm" value="<?php echo $anggota_data['tanggal_lahir'] ?? ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Pekerjaan</label>
                                                <input type="text" name="pekerjaan" class="form-control form-control-sm" value="<?php echo htmlspecialchars($anggota_data['pekerjaan'] ?? ''); ?>" placeholder="Contoh: Wiraswasta">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Rekening Bank</label>
                                                <input type="text" name="rekening_bank" class="form-control form-control-sm" value="<?php echo htmlspecialchars($anggota_data['rekening_bank'] ?? ''); ?>" placeholder="Nomor rekening">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small">Alamat Lengkap</label>
                                        <textarea name="alamat" class="form-control form-control-sm" rows="3" placeholder="Masukkan alamat lengkap"><?php echo htmlspecialchars($anggota_data['alamat'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Tanggal Daftar</label>
                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo $anggota_data['tanggal_daftar'] ? date('d/m/Y', strtotime($anggota_data['tanggal_daftar'])) : '-'; ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label small">Status</label>
                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo ucfirst($anggota_data['status'] ?? 'aktif'); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_profil" class="btn btn-sm btn-primary">
                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Akun & Upload KTP -->
                    <div class="col-lg-4">
                        <!-- Foto KTP -->
                        <div class="card profile-card mb-3">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-id-card me-2"></i>Foto KTP
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="text-center mb-3">
                                    <?php if (!empty($anggota_data['foto_ktp']) && file_exists('../uploads/ktp/' . $anggota_data['foto_ktp'])): ?>
                                        <img src="../uploads/ktp/<?php echo $anggota_data['foto_ktp']; ?>" alt="KTP" class="ktp-preview img-fluid mb-2">
                                        <small class="text-muted d-block">KTP Sudah Diupload</small>
                                    <?php else: ?>
                                        <div class="ktp-preview d-flex align-items-center justify-content-center mb-2" style="min-height: 150px;">
                                            <i class="fas fa-id-card fa-4x text-muted"></i>
                                        </div>
                                        <small class="text-muted d-block">Belum ada foto KTP</small>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-2">
                                        <label class="form-label small">Upload Foto KTP</label>
                                        <input type="file" name="foto_ktp" class="form-control form-control-sm" accept="image/*" required>
                                        <small class="text-muted">Format: JPG/PNG, Maks: 2MB</small>
                                    </div>
                                    <button type="submit" name="upload_ktp" class="btn btn-sm btn-info w-100">
                                        <i class="fas fa-upload me-1"></i> Upload KTP
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Informasi Akun -->
                        <div class="card profile-card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Informasi Akun
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="text-center mb-3">
                                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 70px; height: 70px;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($anggota_data['nama_lengkap'] ?? '-'); ?></h6>
                                    <p class="text-muted mb-2 small"><?php echo htmlspecialchars($anggota_data['kode_anggota'] ?? '-'); ?></p>
                                </div>
                                
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                        <span class="small">Status Akun</span>
                                        <span class="badge bg-success"><?php echo ucfirst($anggota_data['status'] ?? 'aktif'); ?></span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                        <span class="small">Role</span>
                                        <span class="badge bg-primary">Anggota</span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                        <span class="small">Terdaftar Sejak</span>
                                        <span class="small"><?php echo $anggota_data['tanggal_daftar'] ? date('d/m/Y', strtotime($anggota_data['tanggal_daftar'])) : '-'; ?></span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                        <span class="small">Username</span>
                                        <span class="small"><?php echo htmlspecialchars($anggota_data['username'] ?? '-'); ?></span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                        <span class="small">Email</span>
                                        <span class="small"><?php echo htmlspecialchars($anggota_data['email'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <!-- Change Password Form -->
                                <h6 class="mb-2">Ubah Password</h6>
                                <form method="POST">
                                    <div class="mb-2">
                                        <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Password saat ini" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Password baru" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="Konfirmasi password baru" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-sm btn-warning w-100">
                                        <i class="fas fa-key me-1"></i> Ganti Password
                                    </button>
                                </form>
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