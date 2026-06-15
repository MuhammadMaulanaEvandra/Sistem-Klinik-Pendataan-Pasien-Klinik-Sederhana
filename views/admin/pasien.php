<?php



$admin_name = $_SESSION['user_name'] ?? 'Admin Utama';
$success_msg = '';
$error_msg = '';

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
        $age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';

        if (!empty($name) && !empty($phone) && !empty($address)) {
            $new_id = db_add_patient($name, $gender, $age, $address, $phone);
            $success_msg = "Pasien <strong>" . htmlspecialchars($name) . "</strong> ($new_id) berhasil didaftarkan!";
        } else {
            $error_msg = "Semua kolom form wajib diisi!";
        }
    } elseif ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $delete_id = $_GET['id'];
        $found = false;
        foreach ($_SESSION['patients'] as $key => $p) {
            if ($p['id'] === $delete_id) {
                $patient_name = $p['name'];
                unset($_SESSION['patients'][$key]);
                
                $_SESSION['patients'] = array_values($_SESSION['patients']);
                
                
                if (isset($_SESSION['queues'])) {
                    $_SESSION['queues'] = array_values(array_filter($_SESSION['queues'], function($q) use ($delete_id) {
                        return $q['patient_id'] !== $delete_id;
                    }));
                }
                
                
                if (isset($_SESSION['payments'])) {
                    $_SESSION['payments'] = array_values(array_filter($_SESSION['payments'], function($pay) use ($delete_id) {
                        return $pay['patient_id'] !== $delete_id;
                    }));
                }
                
                
                if (isset($_SESSION['referrals'])) {
                    $_SESSION['referrals'] = array_values(array_filter($_SESSION['referrals'], function($ref) use ($delete_id) {
                        return (isset($ref['patient_id']) && $ref['patient_id'] !== $delete_id) && 
                               (isset($ref['patient_name']) && $ref['patient_name'] !== $patient_name);
                    }));
                }

                db_add_audit($admin_name, 'ADMIN', 'PATIENT', "Hapus Data Pasien #$delete_id - $patient_name");
                $success_msg = "Data pasien <strong>" . htmlspecialchars($patient_name) . "</strong> ($delete_id) dan seluruh riwayat transaksi/antreannya berhasil dihapus!";
                $found = true;
                break;
            }
        }
        if (!$found) {
            $error_msg = "Pasien dengan ID $delete_id tidak ditemukan!";
        }
    }
}

$patients = db_get_patients();

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';

$filtered_patients = [];
foreach ($patients as $p) {
    $search_match = true;
    if ($search_query !== '') {
        $search_match = (stripos($p['name'], $search_query) !== false || 
                         stripos($p['id'], $search_query) !== false || 
                         (isset($p['phone']) && stripos($p['phone'], $search_query) !== false));
    }
    
    $gender_match = true;
    if ($gender_filter !== '') {
        $gender_match = ($p['gender'] === $gender_filter);
    }
    
    if ($search_match && $gender_match) {
        $filtered_patients[] = $p;
    }
}
?>

<div class="content-header">
    <div>
        <h1>Data Pasien</h1>
        <p class="page-title-desc">Kelola informasi pasien dan riwayat medis secara terpusat.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openAddPatientModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Pasien Baru
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

<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="GET" action="dashboard.php" style="display: flex; gap: 16px; align-items: center; width: 100%;">
            <input type="hidden" name="page" value="pasien">
            
            <div style="flex-grow: 1;">
                <input type="text" name="search" class="form-control" placeholder="Cari nama, ID, atau nomor telepon pasien..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            
            <div style="width: 200px;">
                <select name="gender" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Jenis Kelamin</option>
                    <option value="Laki-laki" <?php echo $gender_filter === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                    <option value="Perempuan" <?php echo $gender_filter === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Cari & Filter</button>
            <?php if ($gender_filter !== '' || $search_query !== ''): ?>
                <a href="dashboard.php?page=pasien" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Pasien Terdaftar</h3>
        <span style="font-size: 13px; color: var(--text-muted);">Menampilkan <?php echo count($filtered_patients); ?> dari <?php echo count($patients); ?> pasien</span>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Pasien</th>
                    <th>Umur / Gender</th>
                    <th>Alamat</th>
                    <th>Nomor HP</th>
                    <th>Tanggal Registrasi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filtered_patients) > 0): ?>
                    <?php foreach ($filtered_patients as $p): 
                        $is_female = ($p['gender'] === 'Perempuan');
                        $initials = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $p['name'])));
                        $initials = substr($initials, 0, 2);
                    ?>
                    <tr>
                        <td>
                            <div class="patient-avatar-cell">
                                <div class="avatar-circle <?php echo $is_female ? 'perempuan' : ''; ?>">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($p['id']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($p['age']); ?> Tahun</div>
                            <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($p['gender']); ?></div>
                        </td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($p['address']); ?>">
                            <?php echo htmlspecialchars($p['address']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['phone']); ?></td>
                        <td><?php echo date('d M Y', strtotime($p['reg_date'])); ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="dashboard.php?page=pemeriksaan&patient_id=<?php echo $p['id']; ?>" class="btn btn-success" style="padding: 6px 12px; font-size: 12px;" title="Lakukan Pemeriksaan Vital Awal">Periksa</a>
                                <a href="dashboard.php?page=pasien&action=delete&id=<?php echo $p['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus data pasien ini?')">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px 0;">
                            Tidak ditemukan data pasien yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal-backdrop" id="addPatientModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Tambah Pendaftaran Pasien Baru</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeAddPatientModal()">&times;</button>
        </div>
        <form method="POST" action="dashboard.php?page=pasien&action=add">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="new_name">Nama Lengkap</label>
                    <input type="text" name="name" id="new_name" class="form-control" placeholder="Contoh: Budi Santoso" required>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="new_gender">Jenis Kelamin</label>
                        <select name="gender" id="new_gender" class="form-control" required>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_age">Umur (Tahun)</label>
                        <input type="number" name="age" id="new_age" class="form-control" placeholder="Contoh: 35" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_phone">Nomor HP</label>
                    <input type="text" name="phone" id="new_phone" class="form-control" placeholder="Contoh: 0812-xxxx-xxxx" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_address">Alamat Lengkap</label>
                    <textarea name="address" id="new_address" class="form-control" placeholder="Alamat lengkap rumah..." required></textarea>
                </div>
            </div>
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeAddPatientModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pasien</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddPatientModal() {
        document.getElementById('addPatientModal').style.display = 'flex';
    }
    function closeAddPatientModal() {
        document.getElementById('addPatientModal').style.display = 'none';
    }
</script>
