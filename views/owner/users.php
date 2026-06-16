<?php

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_user') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';

    if (!empty($username) && !empty($password) && !empty($name) && !empty($role)) {
        
        $duplicate = false;
        foreach ($_SESSION['users'] as $u) {
            if (strtolower($u['username']) === strtolower($username)) {
                $duplicate = true;
                break;
            }
        }

        if ($duplicate) {
            $error_msg = "Username <strong>" . htmlspecialchars($username) . "</strong> sudah terdaftar!";
        } else {
            $new_id = null;
            $db_saved = false;
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if (isset($conn) && $conn !== null && (!isset($db_error) || $db_error === false)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO `users` (`username`, `password`, `name`, `role`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$username, $hashed_password, $name, $role, $status]);
                    $new_id = (int)$conn->lastInsertId();
                    $db_saved = true;
                } catch (PDOException $e) {
                    $error_msg = "Gagal menyimpan ke database: " . $e->getMessage();
                }
            }

            if ($db_saved || !isset($conn) || $conn === null) {
                if ($new_id === null) {
                    $max_id = 0;
                    foreach ($_SESSION['users'] as $u) {
                        if ((int)$u['id'] > $max_id) {
                            $max_id = (int)$u['id'];
                        }
                    }
                    $new_id = $max_id + 1;
                }

                $_SESSION['users'][] = [
                    'id' => $new_id,
                    'username' => $username,
                    'password' => $hashed_password,
                    'name' => $name,
                    'role' => $role,
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'OWNER', 'USERS', "Tambah User Baru #$new_id - $name ($role)");
                $success_msg = "User baru <strong>" . htmlspecialchars($name) . "</strong> berhasil ditambahkan!" . ($db_saved ? " (Tersimpan ke database)" : " (Offline Mode)");
            }
        }
    } else {
        $error_msg = "Semua kolom form wajib diisi!";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'edit_user' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';

    if (!empty($username) && !empty($name) && !empty($role)) {
        
        $duplicate = false;
        foreach ($_SESSION['users'] as $u) {
            if ((int)$u['id'] !== $user_id && strtolower($u['username']) === strtolower($username)) {
                $duplicate = true;
                break;
            }
        }

        if ($duplicate) {
            $error_msg = "Username <strong>" . htmlspecialchars($username) . "</strong> sudah terdaftar pada user lain!";
        } else {
            $db_saved = false;
            $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '';
            if (isset($conn) && $conn !== null && (!isset($db_error) || $db_error === false)) {
                try {
                    if (!empty($password)) {
                        $stmt = $conn->prepare("UPDATE `users` SET `username` = ?, `password` = ?, `name` = ?, `role` = ?, `status` = ? WHERE `id` = ?");
                        $stmt->execute([$username, $hashed_password, $name, $role, $status, $user_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE `users` SET `username` = ?, `name` = ?, `role` = ?, `status` = ? WHERE `id` = ?");
                        $stmt->execute([$username, $name, $role, $status, $user_id]);
                    }
                    $db_saved = true;
                } catch (PDOException $e) {
                    $error_msg = "Gagal memperbarui database: " . $e->getMessage();
                }
            }

            if ($db_saved || !isset($conn) || $conn === null) {
                foreach ($_SESSION['users'] as &$u) {
                    if ((int)$u['id'] === $user_id) {
                        $u['username'] = $username;
                        if (!empty($password)) {
                            $u['password'] = $hashed_password; 
                        }
                        $u['name'] = $name;
                        $u['role'] = $role;
                        $u['status'] = $status;

                        db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'OWNER', 'USERS', "Edit User #$user_id - $name ($role)");
                        $success_msg = "User <strong>" . htmlspecialchars($name) . "</strong> berhasil diperbarui!" . ($db_saved ? " (Tersimpan ke database)" : " (Offline Mode)");
                        break;
                    }
                }
                unset($u);
            }
        }
    } else {
        $error_msg = "Semua kolom form kecuali password wajib diisi!";
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
        $error_msg = "Anda tidak dapat menghapus akun Anda sendiri!";
    } else {
        $db_saved = false;
        if (isset($conn) && $conn !== null && (!isset($db_error) || $db_error === false)) {
            try {
                $stmt = $conn->prepare("DELETE FROM `users` WHERE `id` = ?");
                $stmt->execute([$user_id]);
                $db_saved = true;
            } catch (PDOException $e) {
                $error_msg = "Gagal menghapus dari database: " . $e->getMessage();
            }
        }

        if ($db_saved || !isset($conn) || $conn === null) {
            $found = false;
            foreach ($_SESSION['users'] as $key => $u) {
                if ((int)$u['id'] === $user_id) {
                    $deleted_name = $u['name'];
                    unset($_SESSION['users'][$key]);
                    $_SESSION['users'] = array_values($_SESSION['users']);

                    db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'OWNER', 'USERS', "Hapus User #$user_id - $deleted_name");
                    $success_msg = "User <strong>" . htmlspecialchars($deleted_name) . "</strong> berhasil dihapus." . ($db_saved ? " (Terhapus dari database)" : " (Offline Mode)");
                    $found = true;
                    break;
                }
            }
            if (!$found && !$db_saved) {
                $error_msg = "User tidak ditemukan!";
            }
        }
    }
}

$users = $_SESSION['users'] ?? [];
?>

<div class="content-header">
    <div>
        <h1>Kelola User</h1>
        <p class="page-title-desc">Kelola pengguna sistem klinik (Admin, Dokter, dan Owner) beserta hak aksesnya.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openAddUserModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah User Baru
        </button>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div style="background-color: var(--status-critical-bg); border: 1px solid var(--status-critical); color: #991b1b; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Pengguna Sistem</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Pengguna</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Tanggal Registrasi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): 
                        $status_class = ($u['status'] === 'Active') ? 'success' : 'danger';
                        
                        $initials = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $u['name'])));
                        $initials = strtoupper(substr($initials, 0, 2));
                        
                        $is_self = (isset($_SESSION['user_id']) && $_SESSION['user_id'] === (int)$u['id']);
                    ?>
                    <tr>
                        <td>
                            <div class="patient-avatar-cell">
                                <div class="avatar-circle" style="background-color: #e2e8f0; color: #475569;">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($u['name']); ?> <?php if ($is_self): ?><span style="font-size: 11px; color: var(--accent-primary); font-weight: bold;">(Anda)</span><?php endif; ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);">ID: <?php echo $u['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($u['username']); ?></td>
                        <td>
                            <span class="badge" style="background-color: #f1f5f9; color: #334155; font-size: 10px;">
                                <?php echo strtoupper($u['role']); ?>
                            </span>
                        </td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($u['status']); ?></span></td>
                        <td style="color: var(--text-muted); font-size: 13px;"><?php echo isset($u['created_at']) ? date('d M Y, H:i', strtotime($u['created_at'])) . ' WIB' : '-'; ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($u)); ?>)">Edit</button>
                                <?php if (!$is_self): ?>
                                    <a href="dashboard.php?page=users&action=delete_user&id=<?php echo $u['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?')">Hapus</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Tidak ada data pengguna.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal-backdrop" id="addUserModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Tambah User Baru</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form method="POST" action="dashboard.php?page=users&action=add_user">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="user_new_name">Nama Lengkap</label>
                    <input type="text" name="name" id="user_new_name" class="form-control" placeholder="Contoh: Admin Klinik" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="user_new_username">Username / Email</label>
                    <input type="text" name="username" id="user_new_username" class="form-control" placeholder="Contoh: admin2@klinik.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="user_new_password">Password</label>
                    <input type="password" name="password" id="user_new_password" class="form-control" placeholder="Masukkan password" required>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="user_new_role">Role Pengguna</label>
                        <select name="role" id="user_new_role" class="form-control" required>
                            <option value="admin">Admin</option>
                            <option value="dokter">Dokter</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="user_new_status">Status</label>
                        <select name="status" id="user_new_status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan User</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-backdrop" id="editUserModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Edit User</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form method="POST" id="editUserForm" action="dashboard.php?page=users&action=edit_user">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="user_edit_name">Nama Lengkap</label>
                    <input type="text" name="name" id="user_edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="user_edit_username">Username / Email</label>
                    <input type="text" name="username" id="user_edit_username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="user_edit_password">Password Baru (Biarkan kosong jika tidak diubah)</label>
                    <input type="password" name="password" id="user_edit_password" class="form-control" placeholder="Kosongkan jika tetap">
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="user_edit_role">Role Pengguna</label>
                        <select name="role" id="user_edit_role" class="form-control" required>
                            <option value="admin">Admin</option>
                            <option value="dokter">Dokter</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="user_edit_status">Status</label>
                        <select name="status" id="user_edit_status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddUserModal() {
        document.getElementById('addUserModal').style.display = 'flex';
    }
    function closeAddUserModal() {
        document.getElementById('addUserModal').style.display = 'none';
    }
    function openEditUserModal(user) {
        document.getElementById('editUserForm').action = 'dashboard.php?page=users&action=edit_user&id=' + user.id;
        document.getElementById('user_edit_name').value = user.name;
        document.getElementById('user_edit_username').value = user.username;
        document.getElementById('user_edit_password').value = '';
        document.getElementById('user_edit_role').value = user.role;
        document.getElementById('user_edit_status').value = user.status;
        
        
        const selfId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
        if (parseInt(user.id) === selfId) {
            document.getElementById('user_edit_role').disabled = true;
            document.getElementById('user_edit_status').disabled = true;
            
            let hiddenRole = document.getElementById('self_hidden_role');
            if (!hiddenRole) {
                hiddenRole = document.createElement('input');
                hiddenRole.type = 'hidden';
                hiddenRole.name = 'role';
                hiddenRole.id = 'self_hidden_role';
                document.getElementById('editUserForm').appendChild(hiddenRole);
            }
            hiddenRole.value = user.role;

            let hiddenStatus = document.getElementById('self_hidden_status');
            if (!hiddenStatus) {
                hiddenStatus = document.createElement('input');
                hiddenStatus.type = 'hidden';
                hiddenStatus.name = 'status';
                hiddenStatus.id = 'self_hidden_status';
                document.getElementById('editUserForm').appendChild(hiddenStatus);
            }
            hiddenStatus.value = user.status;
        } else {
            document.getElementById('user_edit_role').disabled = false;
            document.getElementById('user_edit_status').disabled = false;
            
            const hr = document.getElementById('self_hidden_role');
            if (hr) hr.remove();
            const hs = document.getElementById('self_hidden_status');
            if (hs) hs.remove();
        }

        document.getElementById('editUserModal').style.display = 'flex';
    }
    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }
</script>
