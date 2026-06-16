<?php



$success_msg = '';
$error_msg = '';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$user_photo = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';


$profile_role = 'Administrator';
if ($role === 'dokter') {
    $profile_role = 'General Practitioner';
} elseif ($role === 'owner') {
    $profile_role = 'Clinic Owner';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = isset($_POST['name']) ? trim($_POST['name']) : '';
    
    if (empty($new_name)) {
        $error_msg = 'Nama lengkap tidak boleh kosong!';
    } else {
        $photo_path = $user_photo;
        $upload_ok = true;
        
        
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['profile_photo'];
            
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_msg = 'Terjadi kesalahan saat mengunggah berkas foto.';
                $upload_ok = false;
            } else {
                
                $max_size = 2 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    $error_msg = 'Ukuran foto profil tidak boleh melebihi 2MB!';
                    $upload_ok = false;
                } else {
                    
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
                    $file_info = @getimagesize($file['tmp_name']);
                    $mime_type = $file_info ? $file_info['mime'] : '';
                    
                    if (!in_array($mime_type, $allowed_types)) {
                        $error_msg = 'Format berkas tidak valid! Hanya mendukung file JPG, JPEG, PNG, GIF, dan WebP.';
                        $upload_ok = false;
                    } else {
                        
                        $upload_dir = 'uploads/profile_pics/';
                        if (!is_dir($upload_dir)) {
                            @mkdir($upload_dir, 0755, true);
                        }
                        
                        
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        if (empty($ext)) {
                            $ext = 'jpg';
                        }
                        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                        $target_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            
                            if ($user_photo && file_exists($user_photo)) {
                                @unlink($user_photo);
                            }
                            $photo_path = $target_path;
                        } else {
                            $error_msg = 'Gagal menyimpan berkas foto profil ke folder penyimpanan!';
                            $upload_ok = false;
                        }
                    }
                }
            }
        }
        
        if ($upload_ok) {
            $db_updated = false;
            
            
            if (isset($conn) && $conn !== null && (!isset($db_error) || $db_error === false)) {
                try {
                    $stmt = $conn->prepare("UPDATE `users` SET `name` = ?, `photo` = ? WHERE `id` = ?");
                    $stmt->execute([$new_name, $photo_path, $user_id]);
                    $db_updated = true;
                } catch (PDOException $e) {
                    $error_msg = 'Gagal memperbarui database profil: ' . $e->getMessage();
                }
            }
            
            
            if ($db_updated || !isset($conn) || $conn === null) {
                
                if (isset($_SESSION['users'])) {
                    foreach ($_SESSION['users'] as &$u) {
                        if ((int)$u['id'] === $user_id) {
                            $u['name'] = $new_name;
                            $u['photo'] = $photo_path;
                            break;
                        }
                    }
                    unset($u);
                }
                
                
                $_SESSION['user_name'] = $new_name;
                $_SESSION['user_photo'] = $photo_path;
                
                $user_name = $new_name;
                $user_photo = $photo_path;
                
                
                db_add_audit($new_name, strtoupper($role), 'PROFILE', "Mengubah nama lengkap dan memperbarui foto profil");
                
                $success_msg = 'Profil Anda berhasil diperbarui!' . ($db_updated ? '' : ' (Penyimpanan Sesi Offline)');
            }
        }
    }
}


$words = explode(' ', $user_name);
$avatar_initials = '';
foreach ($words as $w) {
    if (!empty($w)) {
        $avatar_initials .= $w[0];
    }
}
$avatar_initials = strtoupper(substr($avatar_initials, 0, 2));
if (empty($avatar_initials)) {
    $avatar_initials = 'US';
}
?>

<style>
    .profile-edit-container {
        max-width: 640px;
        margin: 0 auto;
        padding-top: 10px;
    }
    
    .profile-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        margin-bottom: 32px;
    }
    
    .profile-banner {
        height: 120px;
        background: linear-gradient(135deg, #4f46e5, #818cf8);
        position: relative;
    }
    
    .profile-avatar-wrapper {
        display: flex;
        justify-content: center;
        margin-top: -60px;
        margin-bottom: 16px;
        position: relative;
        z-index: 10;
    }
    
    .profile-avatar-container {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid var(--bg-card);
        background: linear-gradient(135deg, #a78bfa, #8b5cf6);
        color: white;
        font-family: 'Outfit', sans-serif;
        font-size: 38px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-md);
        position: relative;
        cursor: pointer;
        overflow: hidden;
        transition: transform var(--transition-fast);
    }
    
    .profile-avatar-container:hover {
        transform: scale(1.02);
    }
    
    .profile-avatar-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-avatar-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.65);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity var(--transition-fast);
        color: #f8fafc;
        font-size: 11px;
        font-weight: 600;
        gap: 6px;
    }
    
    .profile-avatar-container:hover .profile-avatar-overlay {
        opacity: 1;
    }
    
    .profile-info {
        text-align: center;
        padding: 0 24px;
        margin-bottom: 24px;
    }
    
    .profile-display-name {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
    }
    
    .profile-display-role {
        font-size: 12px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 4px;
        font-weight: 600;
    }
    
    .profile-form-body {
        padding: 0 32px 32px 32px;
    }
    
    .alert-container {
        margin: 0 32px 20px 32px;
    }
    
    .file-input-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background-color: #f1f5f9;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: #475569;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition-fast);
        margin-top: 8px;
    }
    
    .file-input-btn:hover {
        background-color: #e2e8f0;
    }
    
    .profile-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        background-color: #f8fafc;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 28px;
    }
    
    .info-label {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 14px;
        color: #1e293b;
        font-weight: 500;
        margin-top: 4px;
    }
</style>

<div class="content-header">
    <div>
        <h1>Edit Profil Saya</h1>
        <p class="page-title-desc">Kelola nama lengkap dan unggah foto profil akun Anda di sistem Medicare Pro.</p>
    </div>
</div>

<div class="profile-edit-container">
    <div class="profile-card">
        <div class="profile-banner"></div>
        
        
        <div class="profile-avatar-wrapper">
            <div class="profile-avatar-container" onclick="triggerFileInput()">
                <?php if ($user_photo && file_exists($user_photo)): ?>
                    <img id="avatar-preview" src="<?php echo htmlspecialchars($user_photo); ?>" alt="Profile Photo">
                <?php else: ?>
                    <div id="avatar-initials-preview"><?php echo $avatar_initials; ?></div>
                    <img id="avatar-preview" src="" alt="Profile Photo" style="display: none;">
                <?php endif; ?>
                
                <div class="profile-avatar-overlay">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Ubah Foto</span>
                </div>
            </div>
        </div>
        
        <div class="profile-info">
            <div class="profile-display-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="profile-display-role"><?php echo htmlspecialchars($profile_role); ?></div>
        </div>
        
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert-container">
                <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 14px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?php echo $success_msg; ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert-container">
                <div style="background-color: var(--status-critical-bg); border: 1px solid var(--status-critical); color: #991b1b; padding: 14px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo $error_msg; ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        
        <form method="POST" enctype="multipart/form-data" action="dashboard.php?page=edit_profile">
            
            <input type="file" name="profile_photo" id="profile_photo_input" style="display: none;" accept="image/*" onchange="previewImage(this)">
            
            <div class="profile-form-body">
                
                <div class="profile-info-grid">
                    <div>
                        <div class="info-label">ID Pengguna</div>
                        <div class="info-value">#<?php echo $user_id; ?></div>
                    </div>
                    <div>
                        <div class="info-label">Hak Akses / Role</div>
                        <div class="info-value"><?php echo strtoupper($role); ?></div>
                    </div>
                    <div style="grid-column: span 2; margin-top: 8px; border-top: 1px solid rgba(226,232,240,0.6); padding-top: 12px;">
                        <div class="info-label">Username / Alamat Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                </div>
                
                
                <div class="form-group">
                    <label class="form-label" for="profile_name">Nama Lengkap Anda</label>
                    <input type="text" name="name" id="profile_name" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>" required placeholder="Masukkan nama lengkap Anda">
                </div>
                
                <div class="form-group" style="margin-bottom: 32px;">
                    <label class="form-label">Unggah Foto Profil Baru</label>
                    <div style="display: flex; flex-direction: column; align-items: flex-start;">
                        <button type="button" class="file-input-btn" onclick="triggerFileInput()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Pilih Berkas Foto
                        </button>
                        <span id="file-name-label" style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">Format file didukung: JPG, PNG, GIF, WebP. Maks 2MB.</span>
                    </div>
                </div>
                
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                    <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function triggerFileInput() {
        document.getElementById('profile_photo_input').click();
    }
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            
            const allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif|\.webp)$/i;
            if (!allowedExtensions.exec(file.name)) {
                alert('Tipe file tidak valid. Silakan pilih file gambar (JPG, JPEG, PNG, GIF, atau WebP).');
                input.value = '';
                return;
            }
            
            
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file terlalu besar. Maksimum ukuran file adalah 2MB.');
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imgPreview = document.getElementById('avatar-preview');
                const initialsPreview = document.getElementById('avatar-initials-preview');
                
                imgPreview.src = e.target.result;
                imgPreview.style.display = 'block';
                
                if (initialsPreview) {
                    initialsPreview.style.display = 'none';
                }
                
                
                document.getElementById('file-name-label').textContent = 'Foto terpilih: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            }
            
            reader.readAsDataURL(file);
        }
    }
</script>

