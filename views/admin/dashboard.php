<?php



$patients = db_get_patients();
$queues = $_SESSION['queues'];
$payments = $_SESSION['payments'];


$total_patients = count($patients);
$today_queues = count($queues);
$pending_queues = 0;
$in_progress_queues = 0;

foreach ($queues as $q) {
    if ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL') {
        $pending_queues++;
    } elseif ($q['status'] === 'DIPERIKSA') {
        $in_progress_queues++;
    }
}


$completed_payments_count = 0;
$completed_payments_sum = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $completed_payments_count++;
        $completed_payments_sum += $p['amount'];
    }
}


$days_activity = [];
$day_names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
for ($i = 6; $i >= 0; $i--) {
    $date_key = date('Y-m-d', strtotime("-$i days"));
    $days_activity[$date_key] = [
        'count' => 0,
        'label' => $day_names[(int)date('w', strtotime("-$i days"))]
    ];
}
foreach ($patients as $p) {
    if (isset($p['reg_date'])) {
        $reg_d = date('Y-m-d', strtotime($p['reg_date']));
        if (isset($days_activity[$reg_d])) {
            $days_activity[$reg_d]['count']++;
        }
    }
}
$max_activity = 0;
foreach ($days_activity as $d) {
    if ($d['count'] > $max_activity) {
        $max_activity = $d['count'];
    }
}
if ($max_activity <= 0) {
    $max_activity = 5; 
}

$points_coords = [];
$idx = 0;
foreach ($days_activity as $d_key => $d) {
    $x = 50 + ($idx * 70);
    $y = 170 - ($d['count'] / $max_activity) * 140;
    $points_coords[] = [
        'x' => $x,
        'y' => $y,
        'label' => $d['label'],
        'count' => $d['count']
    ];
    $idx++;
}
$path_d = "M " . $points_coords[0]['x'] . " " . $points_coords[0]['y'];
for ($i = 1; $i < count($points_coords); $i++) {
    $path_d .= " L " . $points_coords[$i]['x'] . " " . $points_coords[$i]['y'];
}
$area_d = $path_d . " L 470 170 L 50 170 Z";


$service_counts = [];
$total_services = 0;
foreach ($payments as $p) {
    $svc = $p['service'];
    $cat = 'Lainnya';
    if (stripos($svc, 'resep') !== false || stripos($svc, 'apotek') !== false) {
        $cat = 'Farmasi / Apotek';
    } elseif (stripos($svc, 'konsultasi') !== false || stripos($svc, 'pemeriksaan') !== false || stripos($svc, 'umum') !== false) {
        $cat = 'Pemeriksaan Umum';
    } else {
        $cat = 'Tindakan Lain';
    }
    if (!isset($service_counts[$cat])) {
        $service_counts[$cat] = 0;
    }
    $service_counts[$cat]++;
    $total_services++;
}
if ($total_services === 0) {
    $service_counts = ['Pemeriksaan Umum' => 1];
    $total_services = 1;
}
arsort($service_counts);
?>

<div class="content-header">
    <div>
        <h1>Selamat Datang, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></h1>
        <p class="page-title-desc">Overview operasional klinik hari ini.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openAddPatientModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Pendaftaran Baru
        </button>
    </div>
</div>


<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">Total Pasien</div>
        <div class="stat-value"><?php echo number_format($total_patients); ?></div>
        <div class="stat-footer">
            <span class="stat-trend-up">+12%</span>
            <span>dari bulan lalu</span>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Antrean Hari Ini</div>
        <div class="stat-value"><?php echo $today_queues; ?></div>
        <div class="stat-footer">
            <span class="badge warning" style="font-size: 10px; padding: 2px 6px;"><?php echo $pending_queues; ?> Pending</span>
        </div>
    </div>
    <div class="stat-card pending">
        <div class="stat-header">Sedang Diperiksa</div>
        <div class="stat-value"><?php echo $in_progress_queues; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Di ruang konsultasi</span>
        </div>
    </div>
    <div class="stat-card safe">
        <div class="stat-header">Transaksi Selesai</div>
        <div class="stat-value"><?php echo $completed_payments_count; ?></div>
        <div class="stat-footer">
            <span class="stat-trend-up" style="font-weight: 600;">Rp <?php echo number_format($completed_payments_sum, 0, ',', '.'); ?></span>
        </div>
    </div>
</div>


<div class="grid-2col">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Grafik Aktivitas Pasien Harian</h3>
            <span style="font-size: 12px; color: var(--text-muted);">7 Hari Terakhir</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <svg viewBox="0 0 500 200" class="svg-chart">
                    
                    <line x1="40" y1="20" x2="480" y2="20" stroke="#f1f5f9" stroke-width="1" />
                    <line x1="40" y1="70" x2="480" y2="70" stroke="#f1f5f9" stroke-width="1" />
                    <line x1="40" y1="120" x2="480" y2="120" stroke="#f1f5f9" stroke-width="1" />
                    <line x1="40" y1="170" x2="480" y2="170" stroke="#cbd5e1" stroke-width="1" />
                    
                    
                    <path d="<?php echo $path_d; ?>" fill="none" stroke="var(--accent-primary)" stroke-width="3" />
                    
                    <path d="<?php echo $area_d; ?>" fill="var(--accent-glow)" opacity="0.3" />
                    
                    
                    <?php foreach ($points_coords as $pt): ?>
                        <circle cx="<?php echo $pt['x']; ?>" cy="<?php echo $pt['y']; ?>" r="5" fill="var(--accent-primary)" />
                    <?php endforeach; ?>
                    
                    
                    <?php foreach ($points_coords as $pt): ?>
                        <text x="<?php echo $pt['x']; ?>" y="190" font-size="10" fill="#94a3b8" text-anchor="middle"><?php echo htmlspecialchars($pt['label']); ?></text>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Distribusi Layanan</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php 
                $svc_colors = [
                    'Pemeriksaan Umum' => 'var(--accent-primary)',
                    'Farmasi / Apotek' => 'linear-gradient(90deg, #a78bfa, #8b5cf6)',
                    'Tindakan Lain' => 'linear-gradient(90deg, #60a5fa, #3b82f6)',
                    'Lainnya' => 'linear-gradient(90deg, #cbd5e1, #94a3b8)'
                ];
                foreach ($service_counts as $name => $count): 
                    $pct = round(($count / $total_services) * 100);
                    $color = $svc_colors[$name] ?? 'var(--accent-primary)';
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 500; margin-bottom: 4px;">
                        <span><?php echo htmlspecialchars($name); ?></span>
                        <span><?php echo $pct; ?>%</span>
                    </div>
                    <div class="poly-progress-bar">
                        <div class="poly-progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Antrean Pasien Terbaru</h3>
        <a href="dashboard.php?page=antrean" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Semua Antrean</a>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No. Antrean</th>
                    <th>Nama Pasien</th>
                    <th>Layanan</th>
                    <th>Waktu Daftar</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach (array_slice($queues, 0, 4) as $q):
                    $patient_name = 'Pasien';
                    foreach ($patients as $p) {
                        if ($p['id'] === $q['patient_id']) {
                            $patient_name = $p['name'];
                            break;
                        }
                    }
                    
                    $status_class = strtolower($q['status']);
                    if ($status_class === 'menunggu') $status_class = 'warning';
                    elseif ($status_class === 'diperiksa') $status_class = 'pending';
                    elseif ($status_class === 'selesai') $status_class = 'success';
                ?>
                <tr>
                    <td style="font-weight: 700; color: var(--accent-primary);"><?php echo htmlspecialchars($q['no']); ?></td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($patient_name); ?></td>
                    <td><?php echo htmlspecialchars($q['poly']); ?></td>
                    <td><?php echo htmlspecialchars($q['time']); ?> WIB</td>
                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($q['status']); ?></span></td>
                    <td>
                        <a href="dashboard.php?page=antrean" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Detail</a>
                    </td>
                </tr>
                <?php endforeach; ?>
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
 