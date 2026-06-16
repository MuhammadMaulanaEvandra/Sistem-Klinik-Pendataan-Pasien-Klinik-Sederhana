<?php



if (isset($_GET['action']) && $_GET['action'] === 'mulai_periksa' && isset($_GET['no'])) {
    $q_no = $_GET['no'];
    foreach ($_SESSION['queues'] as &$q) {
        if ($q['no'] === $q_no) {
            $q['status'] = 'DIPERIKSA';
            db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'QUEUE', "Memulai pemeriksaan untuk No Antrean $q_no");
            break;
        }
    }
    unset($q);
    echo "<script>window.location.href = 'dashboard.php?page=antrean';</script>";
    exit;
}

$patients = db_get_patients();
$queues = $_SESSION['queues'];


$waiting_patients = [];
$active_patient = null;

foreach ($queues as $q) {
    
    $p_profile = null;
    foreach ($patients as $p) {
        if ($p['id'] === $q['patient_id']) {
            $p_profile = $p;
            break;
        }
    }
    
    if ($q['status'] === 'DIPERIKSA') {
        $active_patient = [
            'queue' => $q,
            'patient' => $p_profile
        ];
    } elseif ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL') {
        $waiting_patients[] = [
            'queue' => $q,
            'patient' => $p_profile
        ];
    }
}
?>

<div class="content-header">
    <div>
        <h1>Antrean Pasien Dokter</h1>
        <p class="page-title-desc">Kelola pemeriksaan pasien Anda yang sedang mengantre.</p>
    </div>
</div>


<?php

$total_antrean = count($queues);
$selesai_count = 0;
foreach ($queues as $q) {
    if ($q['status'] === 'SELESAI') {
        $selesai_count++;
    }
}
$menunggu_count = count($waiting_patients);
?>
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header">Total Antrean</div>
        <div class="stat-value"><?php echo str_pad($total_antrean, 2, '0', STR_PAD_LEFT); ?></div>
        <div class="stat-footer"><span>Hari ini</span></div>
    </div>
    <div class="stat-card safe">
        <div class="stat-header">Selesai diperiksa</div>
        <div class="stat-value"><?php echo str_pad($selesai_count, 2, '0', STR_PAD_LEFT); ?></div>
        <div class="stat-footer"><span>Telah diberikan obat</span></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Menunggu</div>
        <div class="stat-value"><?php echo str_pad($menunggu_count, 2, '0', STR_PAD_LEFT); ?></div>
        <div class="stat-footer"><span>Pasien dalam antrean</span></div>
    </div>
</div>

<div class="grid-2col">
    
    <div>
        <?php if ($active_patient && isset($active_patient['patient'])): ?>
            <div class="card" style="border: 2px solid var(--accent-primary); position: relative;">
                <div style="position: absolute; top: 16px; right: 16px;">
                    <span class="badge pending" style="animation: pulse 2s infinite;">Pemeriksaan Aktif</span>
                </div>
                
                <div class="card-header">
                    <h3 class="card-title">Pemeriksaan Sedang Berlangsung</h3>
                </div>
                
                <div class="card-body">
                    <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 20px;">
                        <div class="avatar-circle" style="width: 48px; height: 48px; font-size: 16px;">
                            <?php 
                            $init = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $active_patient['patient']['name'])));
                            echo htmlspecialchars(substr($init, 0, 2));
                            ?>
                        </div>
                        <div>
                            <div style="font-size: 18px; font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($active_patient['patient']['name']); ?></div>
                            <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                                No. Antrean: <strong><?php echo htmlspecialchars($active_patient['queue']['no']); ?></strong> &bull; ID: <?php echo htmlspecialchars($active_patient['patient']['id']); ?>
                            </div>
                        </div>
                    </div>

                    <div style="background-color: #f8fafc; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 6px;">Keluhan Utama</div>
                        <p style="font-size: 14px; font-weight: 500; color: #1e293b;">
                            "<?php echo htmlspecialchars($active_patient['patient']['vitals']['keluhan'] ?? 'Keluhan belum dicatat.'); ?>"
                        </p>
                    </div>
                    
                    <a href="dashboard.php?page=pemeriksaan_pasien&patient_id=<?php echo htmlspecialchars($active_patient['patient']['id']); ?>&no=<?php echo htmlspecialchars($active_patient['queue']['no']); ?>" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 600;">Lanjut Pemeriksaan</a>
                </div>
            </div>
        <?php else: ?>
            
        <?php endif; ?>
    </div>

    
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Antrean Pasien Menunggu (<?php echo count($waiting_patients); ?>)</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="display: flex; flex-direction: column;">
                    <?php if (!empty($waiting_patients)): ?>
                        <?php foreach ($waiting_patients as $wp): ?>
                            <div style="padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                                <div style="flex-grow: 1;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-weight: 800; color: var(--accent-primary); font-size: 14px;"><?php echo htmlspecialchars($wp['queue']['no']); ?></span>
                                        <span style="font-weight: 600; color: #0f172a; font-size: 14px;"><?php echo htmlspecialchars($wp['patient']['name'] ?? 'Pasien'); ?></span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                        Keluhan: "<?php echo htmlspecialchars($wp['patient']['vitals']['keluhan'] ?? 'Keluhan belum dicatat.'); ?>"
                                    </div>
                                </div>
                                <div style="flex-shrink: 0;">
                                    <a href="dashboard.php?page=antrean&action=mulai_periksa&no=<?php echo htmlspecialchars($wp['queue']['no']); ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; font-weight: 600; border-color: var(--accent-primary); color: var(--accent-primary);">Mulai Pemeriksaan</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 40px 24px; text-align: center; color: var(--text-muted);">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; opacity: 0.5; color: var(--text-muted);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <div style="font-size: 14px; font-weight: 500;">Tidak ada antrean pasien saat ini.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer" style="padding: 16px; text-align: center; border-top: 1px solid var(--border-color); background-color: #f8fafc;">
                <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="alert('Memuat antrean berikutnya...')">Tampilkan Lebih Banyak</button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
 