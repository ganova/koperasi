<?php
require_once '../includes/auth.php';
require_once '../config/config.php';

// Pastikan hanya teller yang bisa akses
if ($_SESSION['role'] !== 'teller') {
    header('Location: ../dashboard.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle update profil
if ($_POST) {
    if (isset($_POST['update_profil'])) {
        $nama_lengkap = $_POST['nama_lengkap'];
        $email = $_POST['email'] ?? '';

        try {
            $query = "UPDATE users SET nama_lengkap = :nama_lengkap, email = :email 
                     WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nama_lengkap', $nama_lengkap);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                // Update session data
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                $_SESSION['email'] = $email;
                
                $_SESSION['success'] = "Profil berhasil diperbarui";
                header("Location: profil.php");
                exit();
            } else {
                throw new Exception("Gagal memperbarui profil");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }

    // Handle update password
    if (isset($_POST['update_password'])) {
        $password_lama = $_POST['password_lama'];
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];

        try {
            // Verify current password
            if (!password_verify($password_lama, $user['password'])) {
                throw new Exception("Password lama tidak sesuai");
            }

            // Validate new password
            if (strlen($password_baru) < 6) {
                throw new Exception("Password baru minimal 6 karakter");
            }

            if ($password_baru !== $konfirmasi_password) {
                throw new Exception("Konfirmasi password tidak sesuai");
            }

            // Update password
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = :password WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Password berhasil diperbarui";
                header("Location: profil.php");
                exit();
            } else {
                throw new Exception("Gagal memperbarui password");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal memperbarui password: " . $e->getMessage();
        }
    }
}

// Get user statistics untuk teller
$query_stats = "SELECT 
    (SELECT COUNT(*) FROM transaksi WHERE user_id = :user_id) as total_transaksi,
    (SELECT COUNT(*) FROM pinjaman WHERE status = 'aktif') as total_pinjaman_aktif,
    (SELECT COUNT(*) FROM anggota WHERE status = 'aktif') as total_anggota_aktif";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->bindParam(':user_id', $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Get recent activities untuk teller
$query_activities = "SELECT jenis_transaksi, jumlah, tanggal_transaksi 
                    FROM transaksi 
                    WHERE user_id = :user_id 
                    ORDER BY tanggal_transaksi DESC 
                    LIMIT 5";
$stmt_activities = $db->prepare($query_activities);
$stmt_activities->bindParam(':user_id', $user_id);
$stmt_activities->execute();
$activities = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Teller - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .nav-pills .nav-link {
            border-radius: 10px;
            margin-bottom: 5px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .activity-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
        }
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-medium { background-color: #ffc107; width: 50%; }
        .strength-strong { background-color: #28a745; width: 75%; }
        .strength-very-strong { background-color: #20c997; width: 100%; }
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
                        <i class="fas fa-user-circle me-2"></i>Profil Teller
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('d F Y'); ?>
                        </span>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-4">
                        <!-- Profile Card -->
                        <div class="card profile-card mb-4">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($user['nama_lengkap']); ?></h4>
                                <p class="mb-0">Teller</p>
                                <small>
                                    <i class="fas fa-envelope me-1"></i>
                                    <?php echo htmlspecialchars($user['email'] ?? '-'); ?>
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h5 class="mb-1"><?php echo $stats['total_transaksi']; ?></h5>
                                        <small class="text-muted">Transaksi</small>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="mb-1"><?php echo $stats['total_pinjaman_aktif']; ?></h5>
                                        <small class="text-muted">Pinjaman Aktif</small>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="mb-1"><?php echo $stats['total_anggota_aktif']; ?></h5>
                                        <small class="text-muted">Anggota Aktif</small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="user-info">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Username</small>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Email</small>
                                        <strong><?php echo htmlspecialchars($user['email'] ?? '-'); ?></strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Role</small>
                                        <span class="badge bg-info"><?php echo strtoupper($user['role']); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Status</small>
                                        <span class="badge bg-<?php echo $user['status'] == 'aktif' ? 'success' : 'danger'; ?>">
                                            <?php echo strtoupper($user['status']); ?>
                                        </span>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Bergabung Sejak</small>
                                        <strong><?php echo date('d F Y', strtotime($user['created_at'])); ?></strong>
                                    </div>
                                    <?php if ($user['last_login']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Login Terakhir</small>
                                        <strong><?php echo date('d F Y H:i', strtotime($user['last_login'])); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="card profile-card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Aktivitas Terbaru
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($activities)): ?>
                                    <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between">
                                            <strong class="text-primary">
                                                <?php 
                                                $jenis = $activity['jenis_transaksi'];
                                                $jenis_text = [
                                                    'setoran' => 'Setoran',
                                                    'penarikan' => 'Penarikan',
                                                    'pelunasan' => 'Pelunasan'
                                                ];
                                                echo $jenis_text[$jenis] ?? ucfirst($jenis); 
                                                ?>
                                            </strong>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($activity['tanggal_transaksi'])); ?>
                                            </small>
                                        </div>
                                        <small class="text-success">
                                            <?php echo formatRupiah($activity['jumlah']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($activity['tanggal_transaksi'])); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                        <p>Belum ada aktivitas</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <!-- Settings Tabs -->
                        <div class="card profile-card">
                            <div class="card-header">
                                <ul class="nav nav-pills card-header-pills">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#profil">
                                            <i class="fas fa-user-edit me-1"></i>Edit Profil
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#password">
                                            <i class="fas fa-lock me-1"></i>Ubah Password
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Edit Profil Tab -->
                                    <div class="tab-pane fade show active" id="profil">
                                        <h5 class="card-title">Informasi Profil</h5>
                                        <p class="text-muted">Perbarui informasi profil dan alamat email Anda.</p>
                                        
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Lengkap *</label>
                                                        <input type="text" name="nama_lengkap" class="form-control" 
                                                               value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Username</label>
                                                        <input type="text" class="form-control" 
                                                               value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                                        <div class="form-text">Username tidak dapat diubah</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" 
                                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                                       placeholder="Contoh: email@example.com">
                                            </div>

                                            <div class="alert alert-info">
                                                <small>
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Field yang bertanda (*) wajib diisi. Field lainnya opsional.
                                                </small>
                                            </div>

                                            <button type="submit" name="update_profil" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Simpan Perubahan
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Ubah Password Tab -->
                                    <div class="tab-pane fade" id="password">
                                        <h5 class="card-title">Ubah Password</h5>
                                        <p class="text-muted">Pastikan password baru Anda kuat dan mudah diingat.</p>
                                        
                                        <form method="POST" id="passwordForm">
                                            <div class="mb-3">
                                                <label class="form-label">Password Lama *</label>
                                                <input type="password" name="password_lama" class="form-control" required 
                                                       placeholder="Masukkan password lama Anda">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Password Baru *</label>
                                                <input type="password" name="password_baru" class="form-control" required 
                                                       placeholder="Minimal 6 karakter" id="newPassword">
                                                <div class="password-strength" id="passwordStrength"></div>
                                                <div class="form-text" id="passwordFeedback"></div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Konfirmasi Password Baru *</label>
                                                <input type="password" name="konfirmasi_password" class="form-control" required 
                                                       placeholder="Ketik ulang password baru">
                                                <div class="form-text" id="confirmFeedback"></div>
                                            </div>

                                            <div class="alert alert-warning">
                                                <small>
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Pastikan Anda mengingat password baru Anda.
                                                </small>
                                            </div>

                                            <button type="submit" name="update_password" class="btn btn-primary" id="submitPassword">
                                                <i class="fas fa-key me-1"></i> Ubah Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Tips -->
                        <div class="card profile-card mt-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>Tips Keamanan
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <small class="fw-bold">Gunakan Password Kuat</small>
                                                <p class="text-muted mb-0 small">Kombinasi huruf, angka, dan simbol</p>
                                            </div>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <small class="fw-bold">Jangan Bagikan Password</small>
                                                <p class="text-muted mb-0 small">Jaga kerahasiaan password Anda</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <small class="fw-bold">Logout Setelah Selesai</small>
                                                <p class="text-muted mb-0 small">Selalu logout dari sistem</p>
                                            </div>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <small class="fw-bold">Update Profil Berkala</small>
                                                <p class="text-muted mb-0 small">Perbarui informasi kontak Anda</p>
                                            </div>
                                        </div>
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
    <script>
        // Password strength indicator
        document.getElementById('newPassword').addEventListener('input', function(e) {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const feedback = document.getElementById('passwordFeedback');
            
            let strength = 0;
            let feedbackText = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            switch(strength) {
                case 0:
                    strengthBar.className = 'password-strength';
                    feedbackText = '';
                    break;
                case 1:
                    strengthBar.className = 'password-strength strength-weak';
                    feedbackText = 'Password lemah';
                    break;
                case 2:
                    strengthBar.className = 'password-strength strength-medium';
                    feedbackText = 'Password cukup';
                    break;
                case 3:
                    strengthBar.className = 'password-strength strength-strong';
                    feedbackText = 'Password kuat';
                    break;
                case 4:
                    strengthBar.className = 'password-strength strength-very-strong';
                    feedbackText = 'Password sangat kuat';
                    break;
            }
            
            feedback.textContent = feedbackText;
        });

        // Password confirmation check
        document.querySelector('input[name="konfirmasi_password"]').addEventListener('input', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            const feedback = document.getElementById('confirmFeedback');
            
            if (confirmPassword === '') {
                feedback.textContent = '';
                feedback.className = 'form-text';
            } else if (newPassword === confirmPassword) {
                feedback.textContent = 'Password cocok';
                feedback.className = 'form-text text-success';
            } else {
                feedback.textContent = 'Password tidak cocok';
                feedback.className = 'form-text text-danger';
            }
        });

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.querySelector('input[name="konfirmasi_password"]').value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password baru minimal 6 karakter');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Konfirmasi password tidak sesuai');
                return false;
            }
        });

        // Auto capitalize name
        document.querySelector('input[name="nama_lengkap"]').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>