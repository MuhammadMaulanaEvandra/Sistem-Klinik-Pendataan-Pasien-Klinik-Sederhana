<?php


$patients = db_get_patients();
$queues = $_SESSION['queues'];


$waiting_count = 0;
$completed_count = 0;

foreach ($queues as $q) {
    if ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL') {
        $waiting_count++;
    } elseif ($q['status'] === 'SELESAI') {
        $completed_count++;
    }
}
$total_today = $waiting_count + $completed_count;
?>

<div class="content-header">
    <div>
        <h1>Selamat Pagi, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Dr. Raka Aji'); ?></h1>
        <p class="page-title-desc">Berikut adalah ringkasan klinis Anda hari ini.</p>
    </div>
</div>


<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">Total Pasien Hari Ini</div>
        <div class="stat-value"><?php echo $total_today; ?></div>
        <div class="stat-footer">
            <span class="stat-trend-up">+12%</span>
            <span>vs kemarin</span>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Pasien Menunggu</div>
        <div class="stat-value"><?php echo $waiting_count; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Di ruang tunggu</span>
        </div>
    </div>
    <div class="stat-card safe">
        <div class="stat-header">Selesai Diperiksa</div>
        <div class="stat-value"><?php echo $completed_count; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted); font-weight: 500;">Sudah diberi resep</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Rata-rata Waktu</div>
        <div class="stat-value">15 Mnt</div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Per pasien konsultasi</span>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Antrean Terkini</h3>
        <a href="dashboard.php?page=antrean" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Lihat Seluruh Daftar Antrean</a>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No. Antrean</th>
                    <th>Nama Pasien</th>
                    <th>Waktu Jadwal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $shown_queues = 0;
                foreach ($queues as $q):
                    if ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPANGGIL' || $q['status'] === 'DIPERIKSA'):
                        $patient_name = 'Pasien';
                        $patient_id = '';
                        foreach ($patients as $p) {
                            if ($p['id'] === $q['patient_id']) {
                                $patient_name = $p['name'];
                                $patient_id = $p['id'];
                                break;
                            }
                        }
                        $shown_queues++;
                ?>
                <tr>
                    <td style="font-weight: 700; color: var(--accent-primary);"><?php echo htmlspecialchars($q['no']); ?></td>
                    <td style="font-weight: 600; color: #0f172a;">
                        <?php echo htmlspecialchars($patient_name); ?>
                        <span style="font-weight: normal; font-size: 11px; color: var(--text-muted); margin-left: 8px;">(<?php echo htmlspecialchars($patient_id); ?>)</span>
                    </td>
                    <td><?php echo htmlspecialchars($q['time']); ?> WIB</td>
                    <td><span class="badge warning"><?php echo htmlspecialchars($q['status']); ?></span></td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <a href="dashboard.php?page=pemeriksaan_pasien&patient_id=<?php echo htmlspecialchars($patient_id); ?>&no=<?php echo htmlspecialchars($q['no']); ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Periksa Sekarang</a>
                            <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="alert('Detail Rekam Medis: <?php echo htmlspecialchars($patient_name); ?>')">Detail</button>
                        </div>
                    </td>
                </tr>
                <?php 
                    endif;
                    if ($shown_queues >= 3) break;
                endforeach; 
                if ($shown_queues === 0):
                ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                        Semua pasien antrean hari ini telah diperiksa!
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
 