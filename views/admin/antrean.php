<?php


$admin_name = $_SESSION['user_name'] ?? 'Admin Utama';


$success_msg = '';
if (isset($_GET['action']) && isset($_GET['no'])) {
    $q_no = $_GET['no'];
    $act = $_GET['action'];
    
    foreach ($_SESSION['queues'] as &$q) {
        if ($q['no'] === $q_no) {
            if ($act === 'panggil') {
                $q['status'] = 'DIPANGGIL';
                db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Memanggil No Antrean $q_no");
                $success_msg = "Antrean <strong>$q_no</strong> dipanggil.";
            } elseif ($act === 'lewati') {
                $q['status'] = 'TERLEWATI';
                db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Melewati No Antrean $q_no");
                $success_msg = "Antrean <strong>$q_no</strong> ditandai terlewati.";
            } elseif ($act === 'periksa') {
                $q['status'] = 'DIPERIKSA';
                db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Memulai pemeriksaan untuk No Antrean $q_no");
                $success_msg = "Antrean <strong>$q_no</strong> masuk pemeriksaan.";
            } elseif ($act === 'selesai') {
                $q['status'] = 'SELESAI';
                
                
                $is_billed = false;
                foreach ($_SESSION['payments'] as $p) {
                    if ($p['patient_id'] === $q['patient_id'] && $p['status'] === 'Pending') {
                        $is_billed = true;
                        break;
                    }
                }
                if (!$is_billed) {
                    $trx_id = db_generate_payment_id();
                    $_SESSION['payments'][] = [
                        'id' => $trx_id,
                        'patient_id' => $q['patient_id'],
                        'date' => date('Y-m-d H:i'),
                        'service' => $q['poly'],
                        'method' => 'CASH',
                        'amount' => 150000, 
                        'status' => 'Pending'
                    ];
                }
                
                db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Menyelesaikan No Antrean $q_no");
                $success_msg = "Antrean <strong>$q_no</strong> selesai diperiksa. Invoice tagihan otomatis dibuat.";
            } elseif ($act === 'panggil_ulang') {
                $q['status'] = 'MENUNGGU';
                db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Memanggil ulang No Antrean $q_no");
                $success_msg = "Antrean <strong>$q_no</strong> diantrekan ulang.";
            }
            break;
        }
    }
    unset($q);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_queue') {
    $patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
    $poly = isset($_POST['poly']) ? $_POST['poly'] : '';
    
    if (!empty($patient_id) && !empty($poly)) {
        
        $patient_exists = false;
        foreach ($_SESSION['patients'] as $p) {
            if ($p['id'] === $patient_id) {
                $patient_exists = true;
                break;
            }
        }
        
        if ($patient_exists) {
            $queue_no = db_generate_queue_no($poly);
            
            $_SESSION['queues'][] = [
                'no' => $queue_no,
                'patient_id' => $patient_id,
                'time' => date('H:i'),
                'poly' => $poly,
                'status' => 'MENUNGGU'
            ];
            db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Antrean Baru $queue_no untuk pasien $patient_id");
            $success_msg = "Antrean baru <strong>$queue_no</strong> berhasil didaftarkan!";
        }
    }
}


$queues = $_SESSION['queues'];
$patients = db_get_patients();

$c_total = count($queues);
$c_menunggu = 0;
$c_periksa = 0;
$c_selesai = 0;

foreach ($queues as $q) {
    if ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL' || $q['status'] === 'TERLEWATI') {
        $c_menunggu++;
    } elseif ($q['status'] === 'DIPERIKSA') {
        $c_periksa++;
    } elseif ($q['status'] === 'SELESAI') {
        $c_selesai++;
    }
}
?>

<div class="content-header">
    <div>
        <h1>Manajemen Antrean</h1>
        <p class="page-title-desc">Pantau dan kelola alur pasien secara real-time untuk efisiensi layanan.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="export_pdf.php?type=antrean" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export PDF
        </a>
        <button class="btn btn-primary" onclick="openQueueModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Antrean
        </button>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>


<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">Total Antrean</div>
        <div class="stat-value"><?php echo $c_total; ?></div>
        <div class="stat-footer">
            <span class="stat-trend-up">+12%</span>
            <span>vs kemarin</span>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Menunggu</div>
        <div class="stat-value"><?php echo $c_menunggu; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Avg. 15 mins wait time</span>
        </div>
    </div>
    <div class="stat-card pending">
        <div class="stat-header">Sedang Diperiksa</div>
        <div class="stat-value"><?php echo $c_periksa; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">8 Doctors Active</span>
        </div>
    </div>
    <div class="stat-card safe">
        <div class="stat-header">Selesai</div>
        <div class="stat-value"><?php echo $c_selesai; ?></div>
        <div class="stat-footer">
            <span class="stat-trend-up">Efficiency 94%</span>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Live Antrean Pasien</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No. Antrean</th>
                    <th>Waktu Daftar</th>
                    <th>Layanan / Poli</th>
                    <th>Pasien</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queues as $q): 
                    $patient_name = 'Pasien';
                    $patient_id = '';
                    $patient_gender = 'Laki-laki';
                    
                    foreach ($patients as $p) {
                        if ($p['id'] === $q['patient_id']) {
                            $patient_name = $p['name'];
                            $patient_id = $p['id'];
                            $patient_gender = $p['gender'];
                            break;
                        }
                    }
                    
                    $is_female = ($patient_gender === 'Perempuan');
                    $initials = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $patient_name)));
                    $initials = substr($initials, 0, 2);
                    
                    
                    $status_class = 'menunggu';
                    if ($q['status'] === 'DIPERIKSA') $status_class = 'pending';
                    elseif ($q['status'] === 'SELESAI') $status_class = 'success';
                    elseif ($q['status'] === 'TERLEWATI') $status_class = 'danger';
                    elseif ($q['status'] === 'DIPANGGIL') $status_class = 'warning';
                ?>
                <tr>
                    <td style="font-weight: 800; color: var(--accent-primary); font-size: 15px;"><?php echo htmlspecialchars($q['no']); ?></td>
                    <td><?php echo htmlspecialchars($q['time']); ?> WIB</td>
                    <td><div style="font-weight: 500;"><?php echo htmlspecialchars($q['poly']); ?></div></td>
                    <td>
                        <div class="patient-avatar-cell">
                            <div class="avatar-circle <?php echo $is_female ? 'perempuan' : ''; ?>" style="width: 32px; height: 32px; font-size: 11px;">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #0f172a; font-size: 13px;"><?php echo htmlspecialchars($patient_name); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($patient_id); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($q['status']); ?></span></td>
                    <td>
                        <div style="display: flex; gap: 6px;">
                            <?php if ($q['status'] === 'MENUNGGU'): ?>
                                <a href="dashboard.php?page=antrean&action=panggil&no=<?php echo $q['no']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Panggil</a>
                                <a href="dashboard.php?page=antrean&action=lewati&no=<?php echo $q['no']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Lewati</a>
                            <?php elseif ($q['status'] === 'DIPANGGIL'): ?>
                                <a href="dashboard.php?page=antrean&action=periksa&no=<?php echo $q['no']; ?>" class="btn btn-success" style="padding: 6px 12px; font-size: 12px;">Periksa</a>
                                <a href="dashboard.php?page=antrean&action=lewati&no=<?php echo $q['no']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Lewati</a>
                            <?php elseif ($q['status'] === 'DIPERIKSA'): ?>
                                <a href="dashboard.php?page=antrean&action=selesai&no=<?php echo $q['no']; ?>" class="btn btn-success" style="padding: 6px 12px; font-size: 12px;">Selesai</a>
                            <?php elseif ($q['status'] === 'TERLEWATI'): ?>
                                <a href="dashboard.php?page=antrean&action=panggil_ulang&no=<?php echo $q['no']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Panggil Ulang</a>
                            <?php else: ?>
                                <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Pemeriksaan Selesai</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal-backdrop" id="addQueueModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Daftarkan Antrean Baru</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeQueueModal()">&times;</button>
        </div>
        <form method="POST" action="dashboard.php?page=antrean&action=add_queue">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="q_patient">Pilih Pasien</label>
                    <select name="patient_id" id="q_patient" class="form-control" required>
                        <option value="">-- Pilih Pasien --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="poly" value="Poli Umum">
            </div>
            
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeQueueModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Masukkan Antrean</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openQueueModal() {
        document.getElementById('addQueueModal').style.display = 'flex';
    }
    function closeQueueModal() {
        document.getElementById('addQueueModal').style.display = 'none';
    }
</script>
