<?php


$patients  = db_get_patients();
$payments  = $_SESSION['payments']  ?? [];
$medicines = $_SESSION['medicines'] ?? [];
$queues    = $_SESSION['queues']    ?? [];
$stock_logs = $_SESSION['stock_logs'] ?? [];
$referrals  = $_SESSION['referrals']  ?? [];


$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
if ($selected_month < 1 || $selected_month > 12) $selected_month = (int)date('m');
if ($selected_year  < 2020 || $selected_year  > 2099) $selected_year  = (int)date('Y');

$month_label = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$month_start = sprintf('%04d-%02d-01', $selected_year, $selected_month);
$month_end   = date('Y-m-t', strtotime($month_start));


function is_in_month($date_str, $start, $end) {
    if (empty($date_str)) return false;
    $ts = strtotime($date_str);
    return $ts >= strtotime($start) && $ts <= strtotime($end . ' 23:59:59');
}


$fin_revenue   = 0;
$fin_pending   = 0;
$fin_trx_count = 0;
$fin_success   = 0;
$fin_by_service = [];
$fin_by_method  = [];

foreach ($payments as $p) {
    if (!is_in_month($p['date'], $month_start, $month_end)) continue;
    $fin_trx_count++;
    if ($p['status'] === 'Success') {
        $fin_revenue += $p['amount'];
        $fin_success++;
        $svc = $p['service'] ?: 'Lainnya';
        $fin_by_service[$svc] = ($fin_by_service[$svc] ?? 0) + $p['amount'];
        $m = strtoupper($p['method'] ?: 'CASH');
        $fin_by_method[$m] = ($fin_by_method[$m] ?? 0) + 1;
    } else {
        $fin_pending += $p['amount'];
    }
}
$fin_avg = $fin_success > 0 ? round($fin_revenue / $fin_success) : 0;
arsort($fin_by_service);
arsort($fin_by_method);


$px_new       = 0;
$px_total     = count($patients);
$px_active    = 0;
$px_gender_m  = 0;
$px_gender_f  = 0;
$px_referrals = 0;

foreach ($patients as $px) {
    $reg = $px['reg_date'] ?? '';
    if (is_in_month($reg, $month_start, $month_end)) $px_new++;
    if (($px['status'] ?? '') === 'Active') $px_active++;
    $g = strtolower($px['gender'] ?? '');
    if ($g === 'laki-laki' || $g === 'male' || $g === 'l') $px_gender_m++;
    else $px_gender_f++;
}
foreach ($referrals as $ref) {
    if (is_in_month($ref['date'] ?? '', $month_start, $month_end)) $px_referrals++;
}


$q_total    = 0;
$q_selesai  = 0;
foreach ($queues as $q) {
    
    $q_total++;
    if (($q['status'] ?? '') === 'SELESAI') $q_selesai++;
}


$med_total   = count($medicines);
$med_aman    = 0;
$med_rendah  = 0;
$med_kritis  = 0;
$med_value   = 0;

foreach ($medicines as $m) {
    $med_value += ($m['price'] ?? 0) * ($m['stock'] ?? 0);
    $s = $m['status'] ?? '';
    if ($s === 'Aman')   $med_aman++;
    elseif ($s === 'Rendah')  $med_rendah++;
    elseif ($s === 'Kritis')  $med_kritis++;
}


$stock_in  = 0;
$stock_out = 0;
foreach ($stock_logs as $log) {
    if (!is_in_month($log['time'] ?? '', $month_start, $month_end)) continue;
    $qty = abs((int)preg_replace('/[^0-9\-]/', '', $log['amount'] ?? '0'));
    if (($log['type'] ?? '') === 'MASUK STOK')  $stock_in  += $qty;
    elseif (($log['type'] ?? '') === 'KELUAR STOK') $stock_out += $qty;
}


$year_min = (int)date('Y') - 3;
$year_max = (int)date('Y') + 1;
$months_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<div class="content-header">
    <div>
        <h1>Laporan Bulanan</h1>
        <p class="page-title-desc">Ringkasan performa klinik: pasien, keuangan, inventaris obat, dan operasional.</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        
        <form method="GET" action="dashboard.php" style="display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="page" value="backup">
            <select name="month" class="form-control" style="width:130px; padding:8px 12px; font-size:13px;">
                <?php for ($mi = 1; $mi <= 12; $mi++): ?>
                    <option value="<?php echo $mi; ?>" <?php echo $mi === $selected_month ? 'selected' : ''; ?>>
                        <?php echo $months_id[$mi]; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-control" style="width:90px; padding:8px 12px; font-size:13px;">
                <?php for ($yi = $year_min; $yi <= $year_max; $yi++): ?>
                    <option value="<?php echo $yi; ?>" <?php echo $yi === $selected_year ? 'selected' : ''; ?>>
                        <?php echo $yi; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-secondary" style="padding:8px 16px; font-size:13px;">Tampilkan</button>
        </form>

        
        <a href="export_pdf.php?type=laporan_bulanan&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>"
           target="_blank"
           class="btn btn-primary"
           style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Export PDF
        </a>
    </div>
</div>


<div style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); border-radius:14px; padding:20px 28px; margin-bottom:28px; display:flex; justify-content:space-between; align-items:center; color:white;">
    <div>
        <div style="font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Periode Laporan</div>
        <div style="font-size:22px; font-weight:800;"><?php echo $month_label; ?></div>
        <div style="font-size:12px; color:#60a5fa; margin-top:4px;"><?php echo date('d M Y', strtotime($month_start)); ?> &mdash; <?php echo date('d M Y', strtotime($month_end)); ?></div>
    </div>
    <div style="text-align:right;">
        <div style="font-size:12px; color:#94a3b8; margin-bottom:4px;">Dicetak oleh</div>
        <div style="font-size:15px; font-weight:700;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Owner'); ?></div>
        <div style="font-size:11px; color:#60a5fa;"><?php echo date('d M Y, H:i'); ?> WIB</div>
    </div>
</div>




<div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
    <div style="width:4px; height:22px; background:linear-gradient(180deg, #10b981, #059669); border-radius:2px;"></div>
    <div style="font-size:16px; font-weight:800; color:#0f172a;">Keuangan</div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card safe">
        <div class="stat-header">Total Pendapatan</div>
        <div class="stat-value" style="font-size:20px;">Rp <?php echo number_format($fin_revenue, 0, ',', '.'); ?></div>
        <div class="stat-footer"><span><?php echo $fin_success; ?> transaksi selesai</span></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Tagihan Pending</div>
        <div class="stat-value" style="font-size:20px;">Rp <?php echo number_format($fin_pending, 0, ',', '.'); ?></div>
        <div class="stat-footer"><span><?php echo $fin_trx_count - $fin_success; ?> transaksi belum lunas</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Total Transaksi</div>
        <div class="stat-value"><?php echo $fin_trx_count; ?></div>
        <div class="stat-footer"><span>Semua status</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Rata-rata per Transaksi</div>
        <div class="stat-value" style="font-size:18px;">Rp <?php echo number_format($fin_avg, 0, ',', '.'); ?></div>
        <div class="stat-footer"><span>Dari transaksi sukses</span></div>
    </div>
</div>

<div class="grid-2col" style="margin-bottom:28px;">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pendapatan per Layanan</h3>
            <span style="font-size:12px; color:var(--text-muted);">Bulan ini</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (!empty($fin_by_service)): ?>
                <?php $max_svc = max($fin_by_service) ?: 1; $svc_idx = 0; $svc_colors = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6']; ?>
                <?php foreach (array_slice($fin_by_service, 0, 5, true) as $svc_name => $svc_val): ?>
                    <div style="padding:14px 20px; border-bottom:1px solid var(--border-color);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-size:13px; font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($svc_name); ?></span>
                            <span style="font-size:13px; font-weight:700; color:#0f172a;">Rp <?php echo number_format($svc_val, 0, ',', '.'); ?></span>
                        </div>
                        <div style="height:6px; background:#f1f5f9; border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?php echo round(($svc_val/$max_svc)*100); ?>%; background:<?php echo $svc_colors[$svc_idx % count($svc_colors)]; ?>; border-radius:4px; transition:width 0.6s;"></div>
                        </div>
                    </div>
                    <?php $svc_idx++; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:32px; text-align:center; color:var(--text-muted); font-size:13px;">Belum ada data transaksi bulan ini.</div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Metode Pembayaran</h3>
            <span style="font-size:12px; color:var(--text-muted);">Distribusi transaksi sukses</span>
        </div>
        <div class="card-body">
            <?php if (!empty($fin_by_method) && array_sum($fin_by_method) > 0):
                $total_m = array_sum($fin_by_method);
                $m_pie_colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'];
                $m_accum = 0; $mc_idx = 0;
                $pie_circles = [];
                foreach ($fin_by_method as $mn => $mv) {
                    $pct = round(($mv / $total_m) * 100);
                    if ($pct <= 0) continue;
                    $c = $m_pie_colors[$mc_idx % count($m_pie_colors)];
                    $pie_circles[] = ['method'=>$mn,'pct'=>$pct,'color'=>$c,'offset'=>$m_accum,'val'=>$mv];
                    $m_accum += $pct; $mc_idx++;
                }
            ?>
            <div style="display:flex; gap:20px; align-items:center; justify-content:center;">
                <div style="width:110px; height:110px; flex-shrink:0;">
                    <svg viewBox="0 0 36 36" style="width:100%; height:100%; transform:rotate(-90deg);">
                        <circle cx="18" cy="18" r="15.91" fill="none" stroke="#e2e8f0" stroke-width="4"/>
                        <?php foreach ($pie_circles as $pc): ?>
                            <circle cx="18" cy="18" r="15.91" fill="none"
                                stroke="<?php echo $pc['color']; ?>"
                                stroke-width="4"
                                stroke-dasharray="<?php echo $pc['pct']; ?> 100"
                                stroke-dashoffset="-<?php echo $pc['offset']; ?>"/>
                        <?php endforeach; ?>
                    </svg>
                </div>
                <div style="display:flex; flex-direction:column; gap:7px; font-size:12px;">
                    <?php foreach ($pie_circles as $pc): ?>
                        <div style="display:flex; align-items:center; gap:7px;">
                            <div style="width:10px; height:10px; border-radius:3px; background:<?php echo $pc['color']; ?>; flex-shrink:0;"></div>
                            <span><?php echo htmlspecialchars($pc['method']); ?> <strong>(<?php echo $pc['pct']; ?>%)</strong></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
                <div style="text-align:center; color:var(--text-muted); font-size:13px;">Belum ada data.</div>
            <?php endif; ?>
        </div>
    </div>
</div>




<div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
    <div style="width:4px; height:22px; background:linear-gradient(180deg, #3b82f6, #2563eb); border-radius:2px;"></div>
    <div style="font-size:16px; font-weight:800; color:#0f172a;">Pasien</div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card safe">
        <div class="stat-header">Pasien Baru Bulan Ini</div>
        <div class="stat-value"><?php echo $px_new; ?></div>
        <div class="stat-footer"><span>Terdaftar <?php echo $months_id[$selected_month]; ?></span></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Total Pasien Terdaftar</div>
        <div class="stat-value"><?php echo $px_total; ?></div>
        <div class="stat-footer"><span><?php echo $px_active; ?> aktif</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Antrean Terlayani</div>
        <div class="stat-value"><?php echo $q_selesai; ?></div>
        <div class="stat-footer"><span>dari <?php echo $q_total; ?> total antrean</span></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Rujukan Keluar</div>
        <div class="stat-value"><?php echo $px_referrals; ?></div>
        <div class="stat-footer"><span>Bulan <?php echo $months_id[$selected_month]; ?></span></div>
    </div>
</div>

<div class="grid-2col" style="margin-bottom:28px;">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Distribusi Gender Pasien</h3>
        </div>
        <div class="card-body">
            <?php
            $total_px_g = $px_gender_m + $px_gender_f;
            if ($total_px_g === 0) $total_px_g = 1;
            $pct_m = round(($px_gender_m / $total_px_g) * 100);
            $pct_f = 100 - $pct_m;
            ?>
            <div style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px; font-weight:600;">
                    <span>👨 Laki-laki</span><span><?php echo $px_gender_m; ?> (<?php echo $pct_m; ?>%)</span>
                </div>
                <div style="height:10px; background:#f1f5f9; border-radius:6px; overflow:hidden;">
                    <div style="height:100%; width:<?php echo $pct_m; ?>%; background:#3b82f6; border-radius:6px;"></div>
                </div>
            </div>
            <div>
                <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px; font-weight:600;">
                    <span>👩 Perempuan</span><span><?php echo $px_gender_f; ?> (<?php echo $pct_f; ?>%)</span>
                </div>
                <div style="height:10px; background:#f1f5f9; border-radius:6px; overflow:hidden;">
                    <div style="height:100%; width:<?php echo $pct_f; ?>%; background:#ec4899; border-radius:6px;"></div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Status Antrean</h3>
        </div>
        <div class="card-body">
            <?php
            $q_stats = [];
            foreach ($queues as $q) {
                $s = $q['status'] ?? 'MENUNGGU';
                $q_stats[$s] = ($q_stats[$s] ?? 0) + 1;
            }
            $q_colors = ['SELESAI'=>'#10b981','DIPERIKSA'=>'#3b82f6','MENUNGGU'=>'#f59e0b','BATAL'=>'#ef4444'];
            $q_total_c = max(array_sum($q_stats), 1);
            ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($q_stats as $qs_name => $qs_val): ?>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:4px;">
                            <span><?php echo htmlspecialchars($qs_name); ?></span>
                            <span><?php echo $qs_val; ?> (<?php echo round(($qs_val/$q_total_c)*100); ?>%)</span>
                        </div>
                        <div style="height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?php echo round(($qs_val/$q_total_c)*100); ?>%; background:<?php echo $q_colors[$qs_name] ?? '#64748b'; ?>; border-radius:4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($q_stats)): ?>
                    <div style="text-align:center; color:var(--text-muted); font-size:13px;">Belum ada data antrean.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>




<div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
    <div style="width:4px; height:22px; background:linear-gradient(180deg, #f59e0b, #d97706); border-radius:2px;"></div>
    <div style="font-size:16px; font-weight:800; color:#0f172a;">Inventaris Obat</div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card safe">
        <div class="stat-header">Stok Aman</div>
        <div class="stat-value"><?php echo $med_aman; ?></div>
        <div class="stat-footer"><span>Jenis obat</span></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Stok Rendah</div>
        <div class="stat-value"><?php echo $med_rendah; ?></div>
        <div class="stat-footer"><span>Perlu segera restock</span></div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--status-critical);">
        <div class="stat-header">Stok Kritis</div>
        <div class="stat-value" style="color:var(--status-critical);"><?php echo $med_kritis; ?></div>
        <div class="stat-footer"><span>Restock darurat</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Nilai Inventaris</div>
        <div class="stat-value" style="font-size:16px;">Rp <?php echo number_format($med_value, 0, ',', '.'); ?></div>
        <div class="stat-footer"><span>Total stok × harga jual</span></div>
    </div>
</div>

<div class="grid-2col" style="margin-bottom:28px;">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Aktivitas Stok Bulan Ini</h3>
        </div>
        <div class="card-body">
            <div style="display:flex; gap:16px;">
                <div style="flex:1; background:#f0fdf4; border:1px solid #86efac; border-radius:12px; padding:20px; text-align:center;">
                    <div style="font-size:28px; font-weight:800; color:#16a34a;">+<?php echo $stock_in; ?></div>
                    <div style="font-size:12px; color:#15803d; font-weight:600; margin-top:4px;">Unit Masuk</div>
                </div>
                <div style="flex:1; background:#fef2f2; border:1px solid #fca5a5; border-radius:12px; padding:20px; text-align:center;">
                    <div style="font-size:28px; font-weight:800; color:#dc2626;">-<?php echo $stock_out; ?></div>
                    <div style="font-size:12px; color:#b91c1c; font-weight:600; margin-top:4px;">Unit Keluar</div>
                </div>
            </div>
            <div style="margin-top:16px; padding:14px; background:#f8fafc; border-radius:10px; font-size:13px; text-align:center; color:#64748b;">
                Net Perubahan: <strong style="color:<?php echo ($stock_in - $stock_out) >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                    <?php echo ($stock_in - $stock_out) >= 0 ? '+' : ''; ?><?php echo $stock_in - $stock_out; ?> Unit
                </strong>
            </div>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Obat Perlu Perhatian</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php
            $warn_meds = array_filter($medicines, fn($m) => in_array($m['status']??'', ['Kritis','Rendah']));
            ?>
            <?php if (!empty($warn_meds)): ?>
                <?php foreach (array_slice($warn_meds, 0, 6) as $wm): ?>
                    <div style="padding:12px 20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:600; font-size:13px;"><?php echo htmlspecialchars($wm['name']); ?></div>
                            <div style="font-size:11px; color:var(--text-muted);">Sisa <?php echo $wm['stock']; ?> / <?php echo $wm['max_stock']; ?> unit</div>
                        </div>
                        <span class="badge <?php echo ($wm['status']==='Kritis') ? 'danger' : 'warning'; ?>"><?php echo $wm['status']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:28px; text-align:center; color:var(--text-muted); font-size:13px;">🎉 Semua stok dalam kondisi aman!</div>
            <?php endif; ?>
        </div>
    </div>
</div>




<div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
    <div style="width:4px; height:22px; background:linear-gradient(180deg, #8b5cf6, #7c3aed); border-radius:2px;"></div>
    <div style="font-size:16px; font-weight:800; color:#0f172a;">Pasien Baru — <?php echo $months_id[$selected_month] . ' ' . $selected_year; ?></div>
</div>

<div class="card" style="margin-bottom:28px;">
    <div class="table-responsive">
        <table class="custom-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th>No. RM</th>
                    <th>Nama Pasien</th>
                    <th>Gender</th>
                    <th>Usia</th>
                    <th>No. Telepon</th>
                    <th>Tanggal Daftar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $new_px_list = array_filter($patients, fn($px) => is_in_month($px['reg_date']??'', $month_start, $month_end));
                ?>
                <?php if (!empty($new_px_list)): ?>
                    <?php foreach ($new_px_list as $np): ?>
                        <tr>
                            <td style="font-weight:700; color:#334155;"><?php echo htmlspecialchars($np['id']); ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($np['name']); ?></td>
                            <td><?php echo htmlspecialchars($np['gender']); ?></td>
                            <td><?php echo $np['age']; ?> thn</td>
                            <td><?php echo htmlspecialchars($np['phone']); ?></td>
                            <td><?php echo date('d M Y', strtotime($np['reg_date'])); ?></td>
                            <td><span class="badge <?php echo ($np['status']??'') === 'Active' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($np['status']??'Active'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px;">
                            Tidak ada pasien baru pada bulan <?php echo $months_id[$selected_month] . ' ' . $selected_year; ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>




<div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
    <div style="width:4px; height:22px; background:linear-gradient(180deg, #10b981, #059669); border-radius:2px;"></div>
    <div style="font-size:16px; font-weight:800; color:#0f172a;">Transaksi Keuangan — <?php echo $months_id[$selected_month] . ' ' . $selected_year; ?></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="custom-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Pasien</th>
                    <th>Layanan</th>
                    <th>Metode</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $month_pays = array_filter($payments, fn($p) => is_in_month($p['date']??'', $month_start, $month_end));
                $patients_map = array_column($patients, 'name', 'id');
                ?>
                <?php if (!empty($month_pays)): ?>
                    <?php foreach ($month_pays as $mp):
                        $sc = strtolower($mp['status']??'');
                        if ($sc === 'success') $sc = 'success';
                        elseif ($sc === 'pending') $sc = 'warning';
                        else $sc = 'danger';
                    ?>
                        <tr>
                            <td style="font-weight:700; color:#334155;"><?php echo htmlspecialchars($mp['id']); ?></td>
                            <td><?php echo htmlspecialchars($patients_map[$mp['patient_id']] ?? $mp['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($mp['service']??'-'); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper($mp['method']??'-')); ?></td>
                            <td style="font-weight:700;">Rp <?php echo number_format($mp['amount']??0, 0, ',', '.'); ?></td>
                            <td><span class="badge <?php echo $sc; ?>"><?php echo htmlspecialchars($mp['status']); ?></span></td>
                            <td><?php echo !empty($mp['date']) ? date('d M Y, H:i', strtotime($mp['date'])) . ' WIB' : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px;">
                            Tidak ada transaksi pada bulan <?php echo $months_id[$selected_month] . ' ' . $selected_year; ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
