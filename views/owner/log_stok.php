<?php


$stock_logs = $_SESSION['stock_logs'];


$type_filter = isset($_GET['type_filter']) ? $_GET['type_filter'] : '';
$filtered_logs = [];
foreach ($stock_logs as $log) {
    $match = true;
    if ($type_filter !== '') {
        $match = ($log['type'] === $type_filter);
    }
    
    if ($match) {
        $filtered_logs[] = $log;
    }
}


$total_activities_today = count($stock_logs);
$stok_masuk_total = 0;
$stok_keluar_total = 0;
$low_stock_medicines = 0;

foreach ($stock_logs as $log) {
    $amt = (int)filter_var($log['amount'], FILTER_SANITIZE_NUMBER_INT);
    if ($log['type'] === 'MASUK STOK') {
        $stok_masuk_total += abs($amt);
    } elseif ($log['type'] === 'KELUAR STOK') {
        $stok_keluar_total += abs($amt);
    }
}

foreach ($_SESSION['medicines'] as $m) {
    if ($m['status'] === 'Kritis' || $m['status'] === 'Rendah') {
        $low_stock_medicines++;
    }
}
?>

<div class="content-header">
    <div>
        <h1>Log Stok Obat</h1>
        <p class="page-title-desc">Pantau riwayat masuk dan keluar stok obat secara mendetail.</p>
    </div>
    <div>
        <a href="export_pdf.php?type=log_stok" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export PDF
        </a>
    </div>
</div>


<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">Total Aktivitas Hari Ini</div>
        <div class="stat-value"><?php echo $total_activities_today; ?></div>
        <div class="stat-footer">
            <span>Log mutasi tercatat</span>
        </div>
    </div>
    <div class="stat-card safe">
        <div class="stat-header">Stok Masuk (Restock)</div>
        <div class="stat-value"><?php echo $stok_masuk_total; ?> Unit</div>
        <div class="stat-footer">
            <span>Total penambahan stok</span>
        </div>
    </div>
    <div class="stat-card pending">
        <div class="stat-header">Stok Keluar (Resep)</div>
        <div class="stat-value"><?php echo $stok_keluar_total; ?> Unit</div>
        <div class="stat-footer">
            <span>Total pengeluaran resep</span>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Obat Mendekati Habis</div>
        <div class="stat-value"><?php echo $low_stock_medicines; ?> Item</div>
        <div class="stat-footer">
            <span class="badge warning" style="font-size: 10px; padding: 2px 6px;">Perlu Atensi</span>
        </div>
    </div>
</div>


<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="GET" action="dashboard.php" style="display: flex; gap: 16px; align-items: center;">
            <input type="hidden" name="page" value="log_stok">
            
            <div style="width: 200px;">
                <select name="type_filter" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Tipe Log</option>
                    <option value="MASUK STOK" <?php echo $type_filter === 'MASUK STOK' ? 'selected' : ''; ?>>Stok Masuk (Restock)</option>
                    <option value="KELUAR STOK" <?php echo $type_filter === 'KELUAR STOK' ? 'selected' : ''; ?>>Stok Keluar (Resep)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($type_filter !== ''): ?>
                <a href="dashboard.php?page=log_stok" class="btn btn-secondary" style="background-color: transparent; border: none; color: var(--text-muted);">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Riwayat Log Mutasi Stok</h3>
        <span style="font-size: 13px; color: var(--text-muted);">Menampilkan <?php echo count($filtered_logs); ?> entri log</span>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Tanggal & Waktu</th>
                    <th>Nama Obat</th>
                    <th>Tipe Log</th>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
                    <th>Pengguna</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filtered_logs) > 0): ?>
                    <?php foreach ($filtered_logs as $log): 
                        $is_in = ($log['type'] === 'MASUK STOK');
                        $badge_class = $is_in ? 'success' : 'danger';
                        $amount_style = $is_in ? 'color: var(--status-safe); font-weight: 700;' : 'color: var(--status-critical); font-weight: 700;';
                        
                        $user_initials = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $log['user'])));
                        $user_initials = substr($user_initials, 0, 2);
                    ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 13px;"><?php echo htmlspecialchars($log['time']); ?></td>
                        <td style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($log['name']); ?></td>
                        <td><span class="badge <?php echo $badge_class; ?>" style="font-size: 9px; padding: 2px 6px;"><?php echo $log['type']; ?></span></td>
                        <td style="font-size: 13px;"><?php echo htmlspecialchars($log['desc']); ?></td>
                        <td style="<?php echo $amount_style; ?>"><?php echo htmlspecialchars($log['amount']); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="avatar-circle" style="width: 24px; height: 24px; font-size: 9px; background-color: #cbd5e1; color: #475569;">
                                    <?php echo htmlspecialchars($user_initials); ?>
                                </div>
                                <span style="font-weight: 500; font-size: 13px;"><?php echo htmlspecialchars($log['user']); ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Tidak ditemukan log mutasi stok obat.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
