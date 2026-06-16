<?php


$patients = db_get_patients();
$payments = $_SESSION['payments'];

$filtered_payments = [];
foreach ($payments as $p) {
    $patient_name = 'Pasien';
    foreach ($patients as $pt) {
        if ($pt['id'] === $p['patient_id']) {
            $patient_name = $pt['name'];
            break;
        }
    }
    
    $p['patient_name'] = $patient_name;
    $filtered_payments[] = $p;
}


$total_revenue = 0;
$total_transactions = count($payments);
$success_count = 0;
$outstanding_payments = 0;

foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $total_revenue += $p['amount'];
        $success_count++;
    } elseif ($p['status'] === 'Pending') {
        $outstanding_payments += $p['amount'];
    }
}

$average_bill = ($success_count > 0) ? ($total_revenue / $success_count) : 0;


function format_indo_date($date_str, $include_time = true) {
    if (empty($date_str)) return '-';
    $timestamp = strtotime($date_str);
    if (!$timestamp || $timestamp < 0) return '-';
    
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $day = date('d', $timestamp);
    $month_num = (int)date('m', $timestamp);
    $year = date('Y', $timestamp);
    
    $month_name = $months[$month_num] ?? date('F', $timestamp);
    
    if ($include_time) {
        $time = date('H:i', $timestamp);
        return "$day $month_name $year &bull; $time WIB";
    }
    
    return "$day $month_name $year";
}


$first_date = 'N/A';
$last_date = 'N/A';
if (!empty($payments)) {
    $dates = array_filter(array_column($payments, 'date'));
    if (!empty($dates)) {
        $first_date = format_indo_date(min($dates), false);
        $last_date = format_indo_date(max($dates), false);
    }
} else {
    $first_date = format_indo_date(date('Y-m-01'), false);
    $last_date = format_indo_date(date('Y-m-t'), false);
}


$weekly_revenue = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $day = (int)date('d', strtotime($p['date']));
        if ($day <= 6) {
            $weekly_revenue[1] += $p['amount'];
        } elseif ($day <= 12) {
            $weekly_revenue[2] += $p['amount'];
        } elseif ($day <= 18) {
            $weekly_revenue[3] += $p['amount'];
        } elseif ($day <= 24) {
            $weekly_revenue[4] += $p['amount'];
        } else {
            $weekly_revenue[5] += $p['amount'];
        }
    }
}
$max_weekly = max(array_values($weekly_revenue));
if ($max_weekly <= 0) {
    $max_weekly = 1000000;
}


$method_counts = [];
$total_success_payments = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $method = strtoupper($p['method']);
        if (!isset($method_counts[$method])) {
            $method_counts[$method] = 0;
        }
        $method_counts[$method]++;
        $total_success_payments++;
    }
}
if ($total_success_payments === 0) {
    $method_counts = ['CASH' => 1];
    $total_success_payments = 1;
}
arsort($method_counts);
$method_pie = [];
$accum_m = 0;
$m_colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
$m_color_idx = 0;
foreach ($method_counts as $m_name => $m_val) {
    $pct = round(($m_val / $total_success_payments) * 100);
    if ($pct <= 0) continue;
    $color = $m_colors[$m_color_idx % count($m_colors)];
    $m_color_idx++;
    $method_pie[] = [
        'method' => $m_name,
        'value' => $m_val,
        'percentage' => $pct,
        'dasharray' => "$pct 100",
        'dashoffset' => "-" . $accum_m,
        'color' => $color
    ];
    $accum_m += $pct;
}
?>

<div class="content-header">
    <div>
        <h1>Laporan Keuangan</h1>
        <p class="page-title-desc">Overview performa finansial klinik dalam periode tertentu.</p>
    </div>
    <div style="display: flex; gap: 12px; align-items: center;">
        <span style="font-size: 13px; font-weight: 600; color: var(--text-muted); background-color: white; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px;">
            <?php echo htmlspecialchars($first_date . " - " . $last_date); ?>
        </span>
        <a href="export_pdf.php?type=keuangan" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export PDF
        </a>
    </div>
</div>


<div class="stats-grid">
    <div class="stat-card safe">
        <div class="stat-header">Total Pendapatan</div>
        <div class="stat-value" style="font-size: 24px;">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span>Dynamic DB value</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Total Transaksi</div>
        <div class="stat-value"><?php echo $total_transactions; ?></div>
        <div class="stat-footer">
            <span>Transaksi terproses</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Rata-rata Transaksi</div>
        <div class="stat-value">Rp <?php echo number_format($average_bill, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span>Per kunjungan medis</span>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Outstanding Payments</div>
        <div class="stat-value">Rp <?php echo number_format($outstanding_payments, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span>Tagihan Pending</span>
        </div>
    </div>
</div>



<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detail Transaksi Finansial</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Pasien</th>
                    <th>Layanan</th>
                    <th>Metode</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Tanggal & Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filtered_payments) > 0): ?>
                    <?php foreach ($filtered_payments as $fp): 
                        $status_class = strtolower($fp['status']);
                        if ($status_class === 'success') $status_class = 'success';
                        elseif ($status_class === 'pending') $status_class = 'warning';
                        elseif ($status_class === 'gagal') $status_class = 'danger';
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: #334155;"><?php echo htmlspecialchars($fp['id']); ?></td>
                        <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($fp['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($fp['service']); ?></td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($fp['method']); ?></td>
                        <td style="font-weight: 700; color: #0f172a;">Rp <?php echo number_format($fp['amount'], 0, ',', '.'); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($fp['status']); ?></span></td>
                        <td><?php echo format_indo_date($fp['date']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Tidak ditemukan transaksi finansial.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
