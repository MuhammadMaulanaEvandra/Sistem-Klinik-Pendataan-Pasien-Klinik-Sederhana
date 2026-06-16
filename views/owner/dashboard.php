<?php


$medicines = $_SESSION['medicines'];


$low_stock_count = 0;
foreach ($medicines as $m) {
    if ($m['status'] === 'Kritis' || $m['status'] === 'Rendah') {
        $low_stock_count++;
    }
}


$payments = $_SESSION['payments'] ?? [];
$total_revenue = 0;
$success_trx = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $total_revenue += $p['amount'];
        $success_trx++;
    }
}

$patients = db_get_patients();
$total_patients = count($patients);


$current_year = date('Y');
$monthly_revenue = [];
for ($m = 1; $m <= 12; $m++) {
    $monthly_revenue[$m] = 0;
}
foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $pay_year = date('Y', strtotime($p['date']));
        if ($pay_year === $current_year) {
            $pay_month = (int)date('m', strtotime($p['date']));
            $monthly_revenue[$pay_month] += $p['amount'];
        }
    }
}
$max_revenue = max(array_values($monthly_revenue));
if ($max_revenue <= 0) {
    $max_revenue = 1000000; 
}


$category_totals = [];
$total_volume = 0;
if (isset($_SESSION['stock_logs'])) {
    foreach ($_SESSION['stock_logs'] as $log) {
        if ($log['type'] === 'KELUAR STOK') {
            $med_name = $log['name'];
            $qty = abs((int)$log['amount']);
            
            
            $category = 'Lainnya';
            foreach ($medicines as $m) {
                if ($m['name'] === $med_name) {
                    $category = $m['category'];
                    break;
                }
            }
            if (!isset($category_totals[$category])) {
                $category_totals[$category] = 0;
            }
            $category_totals[$category] += $qty;
            $total_volume += $qty;
        }
    }
}
if ($total_volume === 0) {
    
    $category_totals = ['Antibiotik' => 45, 'Analgesik' => 30, 'Lainnya' => 25];
    $total_volume = 100;
}
arsort($category_totals);
$pie_data = [];
$accum = 0;
$colors = ['var(--accent-primary)', '#a78bfa', '#60a5fa', '#34d399', '#fbbf24', '#f87171'];
$color_index = 0;
foreach ($category_totals as $cat => $val) {
    $pct = round(($val / $total_volume) * 100);
    if ($pct <= 0) continue;
    $color = $colors[$color_index % count($colors)];
    $color_index++;
    $pie_data[] = [
        'category' => $cat,
        'value' => $val,
        'percentage' => $pct,
        'dasharray' => "$pct 100",
        'dashoffset' => "-" . $accum,
        'color' => $color
    ];
    $accum += $pct;
}
?>

<div class="content-header">
    <div>
        <h1>Dashboard Analytics</h1>
        <p class="page-title-desc">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Dr. Raka Aji'); ?>. Here's your clinic's performance overview.</p>
    </div>
</div>


<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">Total Revenue</div>
        <div class="stat-value" style="font-size: 24px;">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span>Dynamic DB value</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Total Transactions</div>
        <div class="stat-value"><?php echo $success_trx; ?></div>
        <div class="stat-footer">
            <span>Successful payments</span>
        </div>
    </div>
    <div class="stat-card critical">
        <div class="stat-header">Low Stock Medicines</div>
        <div class="stat-value"><?php echo $low_stock_count; ?> Items</div>
        <div class="stat-footer">
            <span class="badge danger" style="font-size: 10px; padding: 2px 6px;">Action Required</span>
        </div>
    </div>
    <div class="stat-card pending">
        <div class="stat-header">Total Patients</div>
        <div class="stat-value"><?php echo $total_patients; ?></div>
        <div class="stat-footer">
            <span>Registered in DB</span>
        </div>
    </div>
</div>


<div class="grid-2col">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pendapatan Bulanan (Tahun <?php echo $current_year; ?>)</h3>
            <span style="font-size: 12px; color: var(--text-muted);">Financial growth overview for current fiscal year</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <svg viewBox="0 0 500 200" class="svg-chart">
                    <line x1="40" y1="20" x2="480" y2="20" stroke="#f1f5f9" stroke-width="1" />
                    <line x1="40" y1="70" x2="480" y2="70" stroke="#f1f5f9" stroke-width="1" />
                    <line x1="40" y1="120" x2="480" y2="120" stroke="#f1f5f9" stroke-width="1" />
                    <line x1="40" y1="170" x2="480" y2="170" stroke="#cbd5e1" stroke-width="1" />
                    
                    
                    <?php
                    $month_labels = [
                        1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MEI', 6 => 'JUN',
                        7 => 'JUL', 8 => 'AGU', 9 => 'SEP', 10 => 'OKT', 11 => 'NOV', 12 => 'DES'
                    ];
                    for ($m = 1; $m <= 12; $m++):
                        $revenue = $monthly_revenue[$m];
                        $bar_height = ($revenue / $max_revenue) * 130;
                        $y = 170 - $bar_height;
                        $x = 45 + (($m - 1) * 35);
                        $x_label = $x + 8;
                    ?>
                        <rect x="<?php echo $x; ?>" y="<?php echo $y; ?>" width="16" height="<?php echo $bar_height; ?>" rx="2" fill="var(--accent-primary)" />
                        <text x="<?php echo $x_label; ?>" y="190" font-size="8" fill="#94a3b8" text-anchor="middle"><?php echo $month_labels[$m]; ?></text>
                    <?php endfor; ?>
                </svg>
            </div>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Statistik Penggunaan Obat</h3>
            <span style="font-size: 12px; color: var(--text-muted);">Top categories by volume (<?php echo number_format($total_volume); ?> unit)</span>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 16px; align-items: center; justify-content: center; height: 100%;">
                <div style="width: 120px; height: 120px;">
                    
                    <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                        
                        <circle cx="18" cy="18" r="15.91" fill="none" stroke="#e2e8f0" stroke-width="4"/>
                        <?php foreach ($pie_data as $seg): ?>
                            <circle cx="18" cy="18" r="15.91" fill="none" stroke="<?php echo $seg['color']; ?>" stroke-width="4" stroke-dasharray="<?php echo $seg['dasharray']; ?>" stroke-dashoffset="<?php echo $seg['dashoffset']; ?>"/>
                        <?php endforeach; ?>
                    </svg>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                    <?php foreach ($pie_data as $seg): ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: <?php echo $seg['color']; ?>;"></div>
                            <span><?php echo htmlspecialchars($seg['category']); ?> (<?php echo $seg['percentage']; ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="grid-2col">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pasien Per Bulan</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php 
                $monthly_patients = [];
                $current_year = date('Y');
                for ($m = 1; $m <= 12; $m++) {
                    $month_name = date('F', mktime(0, 0, 0, $m, 1));
                    $monthly_patients[$month_name] = 0;
                }

                foreach ($patients as $p) {
                    if (isset($p['reg_date'])) {
                        $reg_year = date('Y', strtotime($p['reg_date']));
                        if ($reg_year === $current_year) {
                            $reg_month = date('F', strtotime($p['reg_date']));
                            if (isset($monthly_patients[$reg_month])) {
                                $monthly_patients[$reg_month]++;
                            }
                        }
                    }
                }

                $indonesian_months = [
                    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
                    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
                    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
                    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
                ];
                
                $months_to_show = array_slice(array_keys($monthly_patients), -5);
                foreach ($months_to_show as $month_name):
                    $count = $monthly_patients[$month_name];
                    $max_count = max(array_values($monthly_patients));
                    $pct = ($max_count > 0) ? ($count / $max_count * 100) : 0;
                    $display_name = $indonesian_months[$month_name] ?? $month_name;
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                        <span><?php echo strtoupper($display_name); ?></span>
                        <span><?php echo $count; ?> Pasien</span>
                    </div>
                    <div class="poly-progress-bar">
                        <div class="poly-progress-fill" style="width: <?php echo $pct; ?>%; background: var(--accent-primary);"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Stok Obat Menipis</h3>
            <a href="dashboard.php?page=log_stok" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;">VIEW LOGS</a>
        </div>
        <div class="table-responsive">
            <table class="custom-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th>Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $low_stock_medicines = [];
                    foreach ($medicines as $m) {
                        if ($m['status'] === 'Kritis' || $m['status'] === 'Rendah') {
                            $low_stock_medicines[] = $m;
                        }
                    }
                    if (!empty($low_stock_medicines)):
                        foreach ($low_stock_medicines as $m):
                            $badge_class = ($m['status'] === 'Kritis') ? 'danger' : 'warning';
                            $status_text = ($m['status'] === 'Kritis') ? 'CRITICAL' : 'REORDER';
                    ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($m['name']); ?></td>
                            <td style="font-weight: 700; color: var(--status-critical);"><?php echo htmlspecialchars($m['stock']); ?> Unit</td>
                            <td><span class="badge <?php echo $badge_class; ?>" style="font-size: 9px; padding: 2px 6px;"><?php echo $status_text; ?></span></td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 24px;">Semua stok obat dalam kondisi aman.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
 