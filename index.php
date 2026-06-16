<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}


if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$selected_role = 'admin'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'admin';

    if (!empty($email) && !empty($password)) {
        require_once 'includes/db.php';
        
        if ($db_error === false && $conn !== null) {
            
            try {
                $stmt = $conn->prepare("SELECT * FROM `users` WHERE `username` = ? AND `role` = ? LIMIT 1");
                $stmt->execute([$email, $role]);
                $user = $stmt->fetch();
                
                if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
                    $_SESSION['logged_in'] = true;
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['username'];
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    
                    db_add_audit($user['name'], strtoupper($user['role']), 'LOGIN', 'Login Berhasil via Portal');
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = 'Email/Username, Password, atau Role salah!';
                    $selected_role = $role;
                }
            } catch (PDOException $e) {
                $error = 'Gagal mengakses database: ' . $e->getMessage();
                $selected_role = $role;
            }
        } else {
            
            $_SESSION['logged_in'] = true;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;
            if ($role === 'admin') {
                $_SESSION['user_id'] = 1;
                $_SESSION['user_name'] = 'Admin Utama';
            } elseif ($role === 'owner') {
                $_SESSION['user_id'] = 4;
                $_SESSION['user_name'] = 'Dr. Raka Aji';
            } else {
                if (stripos($email, 'rani') !== false) {
                    $_SESSION['user_id'] = 3;
                    $_SESSION['user_name'] = 'Dr. Rani dwi hapsari';
                } else {
                    $_SESSION['user_id'] = 2;
                    $_SESSION['user_name'] = 'Dr. Raka Aji';
                }
            }
            
            db_add_audit($_SESSION['user_name'], strtoupper($role), 'LOGIN', 'Login Berhasil via Portal (Offline Mode)');
            
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = 'Email/Username dan Password wajib diisi!';
        $selected_role = $role;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medicare Pro</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">

    <div class="login-container">
        
        <div class="login-left-pane">
            <div class="login-left-content">
                <h1 class="login-left-title">Sistem Pendataan Pasien</h1>
                <p class="login-left-desc">Solusi manajemen data medis terintegrasi untuk efisiensi layanan kesehatan Klinik Medika.</p>
                
                <div class="access-section-title">Akses Tersedia Untuk</div>
                
                <div class="role-badge-row">
                    <button type="button" class="role-badge-btn <?php echo $selected_role === 'admin' ? 'active' : ''; ?>" data-role="admin" onclick="selectRole('admin')">
                        <span class="badge-dot admin"></span> Admin
                    </button>
                    <button type="button" class="role-badge-btn <?php echo $selected_role === 'dokter' ? 'active' : ''; ?>" data-role="dokter" onclick="selectRole('dokter')">
                        <span class="badge-dot dokter"></span> Dokter
                    </button>
                    <button type="button" class="role-badge-btn <?php echo $selected_role === 'owner' ? 'active' : ''; ?>" data-role="owner" onclick="selectRole('owner')">
                        <span class="badge-dot owner"></span> Owner
                    </button>
                </div>
            </div>
        </div>

        
        <div class="login-right-pane">
            <div class="login-form-container">
                <h2 class="login-form-title">Welcome Back</h2>
                <p class="login-form-desc">Silakan masuk untuk mengelola data pasien.</p>

                <?php if (!empty($error)): ?>
                    <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 24px; text-align: left; font-weight: 500;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="index.php" method="POST" class="login-form">
                    
                    <input type="hidden" name="role" id="selected-role" value="<?php echo htmlspecialchars($selected_role); ?>">

                    <div class="form-group">
                        <label class="form-label" for="email">Email atau Username</label>
                        <input type="text" name="email" id="email" class="form-control" placeholder="nama@klinik.com" value="" required>
                    </div>

                    <div class="form-group">
                        <div class="label-row">
                            <label class="form-label" for="password">Password</label>
                            <a href="#" class="forgot-link" onclick="alert('Silakan hubungi IT Administrator Anda untuk menyetel ulang kata sandi.')">Lupa Password?</a>
                        </div>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" value="" required>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember"> Remember Me
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
            </div>

            <div class="login-footer">
                <p>&copy; 2024 Klinik Medika. All rights reserved.</p>
                <p>Versi 2.4.0 Build 20241012</p>
            </div>
        </div>
    </div>

    <script>
        function selectRole(role) {
            document.getElementById('selected-role').value = role;
            
            const buttons = document.querySelectorAll('.role-badge-btn');
            buttons.forEach(btn => {
                if(btn.getAttribute('data-role') === role) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
