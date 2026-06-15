<?php


$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';
$queue_no = isset($_GET['no']) ? $_GET['no'] : '';


$patient = null;
if (!empty($patient_id)) {
    foreach ($_SESSION['patients'] as $p) {
        if ($p['id'] === $patient_id) {
            $patient = $p;
            break;
        }
    }
}




if (empty($queue_no) && isset($_SESSION['queues'])) {
    foreach ($_SESSION['queues'] as $q) {
        if ($q['patient_id'] === $patient_id && $q['status'] !== 'SELESAI') {
            $queue_no = $q['no'];
            break;
        }
    }
}


if (!empty($queue_no) && isset($_SESSION['queues'])) {
    foreach ($_SESSION['queues'] as &$q) {
        if ($q['no'] === $queue_no && ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL')) {
            $q['status'] = 'DIPERIKSA';
            db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'QUEUE', "Memulai pemeriksaan untuk No Antrean $queue_no");
            break;
        }
    }
    unset($q);
}


$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subj = isset($_POST['subjective']) ? trim($_POST['subjective']) : '';
    $obj = isset($_POST['objective']) ? trim($_POST['objective']) : '';
    $assess = isset($_POST['assessment']) ? trim($_POST['assessment']) : '';
    $plan = isset($_POST['plan']) ? trim($_POST['plan']) : '';
    
    
    if (!empty($assess)) {
        
        $diag_code = 'J06.9';
        $diag_name = $assess;
        if (preg_match('/^([A-Z][0-9][0-9\.]+)\s*[\(-]?\s*([^)]*)\)?/', $assess, $matches)) {
            $diag_code = trim($matches[1]);
            $diag_name = trim($matches[2]) ?: $assess;
        }

        foreach ($_SESSION['patients'] as &$p) {
            if ($p['id'] === $patient_id) {
                $p['consultations'][] = [
                    'date' => date('Y-m-d H:i:s'),
                    'type' => 'Konsultasi Medis',
                    'notes' => "$assess: $plan (Subyektif: $subj)",
                    'subjective' => $subj,
                    'objective' => $obj,
                    'assessment' => $assess,
                    'plan' => $plan,
                    'diagnosis_code' => $diag_code,
                    'diagnosis_name' => $diag_name,
                    'status' => 'Selesai'
                ];
                break;
            }
        }
        unset($p);
        
        
        foreach ($_SESSION['queues'] as &$q) {
            if ($q['patient_id'] === $patient_id && $q['status'] !== 'SELESAI') {
                $q['status'] = 'SELESAI';
                $queue_no = $q['no'];
                break;
            }
        }
        unset($q);
        
        
        foreach ($_SESSION['queues'] as &$q) {
            if ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL') {
                $q['status'] = 'DIPERIKSA';
                db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'QUEUE', "Otomatis memulai pemeriksaan untuk No Antrean " . $q['no']);
                break;
            }
        }
        unset($q);
        
        
        db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'CONSULTATION', "Menyelesaikan Konsultasi Pasien $patient_id - " . $patient['name']);
        
        $success_msg = "Pemeriksaan medis berhasil disimpan! Antrean <strong>$queue_no</strong> ditandai SELESAI.";
        
        
        echo "<script>window.location.href = 'dashboard.php?page=resep&patient_id=" . urlencode($patient_id) . "';</script>";
        exit;
    }
}


$vitals = ($patient !== null) ? ($patient['vitals'] ?? [
    'td' => '-',
    'hr' => '-',
    'temp' => '-',
    'weight' => '-',
    'keluhan' => ''
]) : null;
?>

<div class="content-header">
    <div>
        <h1>Pemeriksaan Konsultasi Pasien</h1>
        <p class="page-title-desc">Isi diagnosa dan tindakan medis pasien (SOAP format).</p>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<?php if ($patient === null): ?>
    <div class="card" style="text-align: center; padding: 48px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 24px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--text-muted); margin-bottom: 16px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <h3 style="color: #0f172a; font-size: 18px; font-weight: 600;">Tidak ada pasien yang sedang diperiksa</h3>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 8px;">Silakan pilih pasien terlebih dahulu dari menu <a href="dashboard.php?page=antrean" style="color: var(--accent-primary); font-weight: 600; text-decoration: none;">Antrean Pasien</a>.</p>
    </div>
<?php else: ?>

<div class="card" style="background-color: #0f172a; color: white; border: none; margin-bottom: 24px;">
    <div class="card-body" style="padding: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div style="display: flex; gap: 16px; align-items: center;">
            <div class="avatar-circle" style="background-color: #3b82f6; color: white; width: 50px; height: 50px; font-size: 18px; border: 2px solid rgba(255,255,255,0.2);">
                <?php 
                $init = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $patient['name'])));
                echo htmlspecialchars(substr($init, 0, 2));
                ?>
            </div>
            <div>
                <h2 style="color: white; font-size: 20px; font-weight: 700;"><?php echo htmlspecialchars($patient['name']); ?></h2>
                <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">
                    <?php echo htmlspecialchars($patient['age']); ?> Tahun &bull; <?php echo htmlspecialchars($patient['gender']); ?> &bull; RM: <strong><?php echo htmlspecialchars($patient['id']); ?></strong>
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 24px; border-left: 1px solid rgba(255,255,255,0.1); padding-left: 24px; flex-wrap: wrap;">
            <div style="text-align: center;">
                <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Tensi</div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 700; margin-top: 4px;"><?php echo htmlspecialchars($vitals['td']); ?> <span style="font-size: 11px; font-weight: normal;">mmHg</span></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Nadi</div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 700; margin-top: 4px;"><?php echo htmlspecialchars($vitals['hr'] ?? '80'); ?> <span style="font-size: 11px; font-weight: normal;">bpm</span></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Suhu</div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 700; margin-top: 4px;"><?php echo htmlspecialchars($vitals['temp']); ?> <span style="font-size: 11px; font-weight: normal;">°C</span></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Berat</div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 700; margin-top: 4px;"><?php echo htmlspecialchars($vitals['weight']); ?> <span style="font-size: 11px; font-weight: normal;">kg</span></div>
            </div>
        </div>

        <div>
            <span class="badge danger" style="padding: 6px 12px; font-size: 10px;">Rawat Jalan Urgent</span>
        </div>
    </div>
</div>

<div class="grid-2col">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Form Pemeriksaan Medis</h3>
        </div>
        <form method="POST" action="dashboard.php?page=pemeriksaan_pasien&patient_id=<?php echo urlencode($patient_id); ?>&no=<?php echo urlencode($queue_no); ?>">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="subj">Subjective: Keluhan Utama</label>
                    <textarea name="subjective" id="subj" class="form-control" placeholder="Tuliskan keluhan utama pasien..." required><?php echo htmlspecialchars($vitals['keluhan'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="obj">Objective: Hasil Pemeriksaan Fisik</label>
                    <textarea name="objective" id="obj" class="form-control" placeholder="Deskripsi hasil observasi dan pemeriksaan fisik..." required></textarea>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="assess">Assessment: Diagnosis (ICD-10)</label>
                        <input type="text" name="assessment" id="assess" class="form-control" placeholder="Cari Kode ICD-10... Contoh: J06.9 (ISPA)" value="" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="plan">Plan: Tindakan Medis</label>
                        <input type="text" name="plan" id="plan" class="form-control" placeholder="Misal: Pemberian Nebulizer / Istirahat Cukup" value="" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Catatan Klinis Internal (Opsional)</label>
                    <textarea name="notes" id="notes" class="form-control" placeholder="Catatan tambahan untuk tim medis..."></textarea>
                </div>
                
                
            </div>
            
            <div class="card-footer" style="padding: 20px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <a href="dashboard.php?page=antrean" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Buat Resep Obat</button>
            </div>
        </form>
    </div>

    
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Terakhir Rekam Medis</h3>
                <button class="btn btn-secondary" style="font-size: 12px; padding: 4px 8px;" onclick="alert('Membuka rekam medis lengkap...')">Lihat Semua</button>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="display: flex; flex-direction: column;">
                    <?php if (!empty($patient['consultations'])): ?>
                        <?php foreach (array_reverse($patient['consultations']) as $c): ?>
                            <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span style="font-weight: 700; font-size: 13px; color: #0f172a;"><?php echo htmlspecialchars($c['type']); ?></span>
                                    <span style="font-size: 11px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($c['date'])); ?></span>
                                </div>
                                <p style="font-size: 13px; color: var(--text-main); line-height: 1.4;">
                                    <?php echo htmlspecialchars($c['notes']); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 13px;">
                            Belum ada riwayat rekam medis.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
