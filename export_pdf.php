<?php
require_once 'includes/db.php';


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$title = '';
$subtitle = '';
$data = [];

$patients = db_get_patients();

if ($type === 'antrean') {
    $title = 'Laporan Antrean Pasien';
    $subtitle = 'Daftar antrean pasien terdaftar hari ini.';
    $data = $_SESSION['queues'] ?? [];
} elseif ($type === 'keuangan') {
    $title = 'Laporan Keuangan';
    $subtitle = 'Riwayat transaksi keuangan dan omset klinik.';
    $data = $_SESSION['payments'] ?? [];
} elseif ($type === 'log_stok') {
    $title = 'Laporan Mutasi Stok Obat';
    $subtitle = 'Riwayat stok masuk dan keluar inventaris apotek.';
    $data = $_SESSION['stock_logs'] ?? [];
} elseif ($type === 'pembayaran') {
    $title = 'Laporan Transaksi Pembayaran';
    $subtitle = 'Daftar invoice tagihan pasien di kasir.';
    $data = $_SESSION['payments'] ?? [];
} elseif ($type === 'obat') {
    $title = 'Laporan Inventaris Obat';
    $subtitle = 'Status ketersediaan stok obat saat ini.';
    $data = $_SESSION['medicines'] ?? [];
} elseif ($type === 'resep') {
    $prescription_id = isset($_GET['id']) ? $_GET['id'] : '';
    $title = 'Resep Obat Pasien';
    $presc_data = $_SESSION['prescriptions'][$prescription_id] ?? null;

    
    if ($presc_data !== null && isset($presc_data['items'])) {
        
        $presc_items    = $presc_data['items'];
        $presc_notes    = $presc_data['notes'] ?? '';
        $presc_patient  = $presc_data['patient_id'] ?? '';
        $presc_doctor   = $presc_data['doctor'] ?? 'Dr. Raka Aji';
        $presc_date     = $presc_data['date'] ?? date('Y-m-d H:i');
    } elseif (is_array($presc_data)) {
        
        $presc_items    = $presc_data;
        $presc_notes    = '';
        $presc_patient  = '';
        $presc_doctor   = $_SESSION['user_name'] ?? 'Dr. Raka Aji';
        $presc_date     = date('Y-m-d H:i');
    } else {
        echo "<p>Data resep tidak ditemukan.</p>";
        exit;
    }

    
    $presc_patient_name = $presc_patient;
    foreach ($patients as $pt) {
        if ($pt['id'] === $presc_patient) {
            $presc_patient_name = $pt['name'];
            break;
        }
    }

    $subtitle = 'Resep Digital untuk ' . htmlspecialchars($presc_patient_name) . ' | ID: ' . htmlspecialchars($prescription_id);
    $data = $presc_items;
} elseif ($type === 'rujukan') {
    $ref_id = isset($_GET['id']) ? $_GET['id'] : '';
    $title = 'Surat Rujukan Pasien';
    $subtitle = 'Dokumen resmi rujukan medis luar klinik.';
    $referral_data = null;
    $referrals = $_SESSION['referrals'] ?? [];
    foreach ($referrals as $ref) {
        if ($ref['id'] === $ref_id) {
            $referral_data = $ref;
            break;
        }
    }
    if (!$referral_data) {
        echo "Surat rujukan tidak ditemukan.";
        exit;
    }
    $data = $referral_data;
} elseif ($type === 'laporan_bulanan') {
    
    $lb_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
    $lb_year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
    if ($lb_month < 1 || $lb_month > 12) $lb_month = (int)date('m');
    if ($lb_year  < 2020 || $lb_year  > 2099) $lb_year  = (int)date('Y');

    $months_name = ['','Januari','Februari','Maret','April','Mei','Juni',
                    'Juli','Agustus','September','Oktober','November','Desember'];
    $lb_label = $months_name[$lb_month] . ' ' . $lb_year;
    $lb_start = sprintf('%04d-%02d-01', $lb_year, $lb_month);
    $lb_end   = date('Y-m-t', strtotime($lb_start));

    
    $lb_doc_no = 'LB-' . $lb_year . sprintf('%02d', $lb_month) . '-001';

    function lb_in_month($d, $s, $e) {
        if (empty($d)) return false;
        $ts = strtotime($d);
        return $ts >= strtotime($s) && $ts <= strtotime($e . ' 23:59:59');
    }

    $lb_payments   = $_SESSION['payments']   ?? [];
    $lb_medicines  = $_SESSION['medicines']  ?? [];
    $lb_queues     = $_SESSION['queues']     ?? [];
    $lb_stock_logs = $_SESSION['stock_logs'] ?? [];
    $lb_referrals  = $_SESSION['referrals']  ?? [];
    $lb_patients   = $patients;
    $lb_users      = $_SESSION['users']      ?? [];
    $lb_prescriptions = $_SESSION['prescriptions'] ?? [];

    
    $lb_doctors = [];
    foreach ($lb_users as $u) {
        if (($u['role'] ?? '') === 'dokter') {
            $lb_doctors[$u['id']] = [
                'id'             => $u['id'],
                'name'           => $u['name'],
                'username'       => $u['username'],
                'sip'            => 'SIP-' . sprintf('%05d', $u['id']) . '-' . $lb_year,
                'specialization' => 'General Practitioner',
                'resep_count'    => 0,
                'revenue'        => 0,
                'patients'       => [],
                'referrals'      => 0,
            ];
        }
    }

    
    foreach ($lb_prescriptions as $pid => $presc) {
        if (!lb_in_month($presc['date'] ?? '', $lb_start, $lb_end)) continue;
        $doc_name = $presc['doctor'] ?? '';
        foreach ($lb_doctors as &$doc) {
            if (stripos($doc['name'], $doc_name) !== false || $doc_name === $doc['name']) {
                $doc['resep_count']++;
                $px_id = $presc['patient_id'] ?? '';
                if ($px_id && !in_array($px_id, $doc['patients'])) {
                    $doc['patients'][] = $px_id;
                }
                break;
            }
        }
        unset($doc);
    }

    
    foreach ($lb_referrals as $ref) {
        if (!lb_in_month($ref['date'] ?? '', $lb_start, $lb_end)) continue;
        
    }

    
    foreach ($lb_prescriptions as $pid => $presc) {
        if (!lb_in_month($presc['date'] ?? '', $lb_start, $lb_end)) continue;
        
        foreach ($lb_payments as $pay) {
            if ($pay['id'] === ($presc['payment_id'] ?? '') && $pay['status'] === 'Success') {
                $doc_name = $presc['doctor'] ?? '';
                foreach ($lb_doctors as &$doc) {
                    if ($doc['name'] === $doc_name || stripos($doc['name'], $doc_name) !== false) {
                        $doc['revenue'] += $pay['amount'];
                        break;
                    }
                }
                unset($doc);
                break;
            }
        }
    }

    
    $lb_revenue = 0; $lb_pending = 0; $lb_trx = 0; $lb_success = 0;
    $lb_by_svc = []; $lb_by_method = [];
    foreach ($lb_payments as $p) {
        if (!lb_in_month($p['date']??'', $lb_start, $lb_end)) continue;
        $lb_trx++;
        if ($p['status'] === 'Success') {
            $lb_revenue += $p['amount'];
            $lb_success++;
            $svc = $p['service'] ?: 'Lainnya';
            $lb_by_svc[$svc] = ($lb_by_svc[$svc] ?? 0) + $p['amount'];
            $m = strtoupper($p['method'] ?: 'CASH');
            $lb_by_method[$m] = ($lb_by_method[$m] ?? 0) + 1;
        } else {
            $lb_pending += $p['amount'];
        }
    }
    $lb_avg = $lb_success > 0 ? round($lb_revenue / $lb_success) : 0;
    arsort($lb_by_svc); arsort($lb_by_method);

    
    $lb_px_new = 0; $lb_px_total = count($lb_patients);
    $lb_px_gm  = 0; $lb_px_gf = 0; $lb_px_ref = 0;
    foreach ($lb_patients as $px) {
        if (lb_in_month($px['reg_date']??'', $lb_start, $lb_end)) $lb_px_new++;
        $g = strtolower($px['gender']??'');
        if ($g === 'laki-laki' || $g === 'l' || $g === 'male') $lb_px_gm++; else $lb_px_gf++;
    }
    foreach ($lb_referrals as $ref) {
        if (lb_in_month($ref['date']??'', $lb_start, $lb_end)) $lb_px_ref++;
    }
    $lb_q_total = count($lb_queues);
    $lb_q_done  = count(array_filter($lb_queues, fn($q) => ($q['status']??'') === 'SELESAI'));

    
    $lb_med_aman = 0; $lb_med_rendah = 0; $lb_med_kritis = 0; $lb_med_val = 0;
    foreach ($lb_medicines as $m) {
        $lb_med_val += ($m['price']??0) * ($m['stock']??0);
        $s = $m['status']??'';
        if ($s === 'Aman') $lb_med_aman++;
        elseif ($s === 'Rendah') $lb_med_rendah++;
        elseif ($s === 'Kritis') $lb_med_kritis++;
    }
    $lb_stock_in = 0; $lb_stock_out = 0;
    foreach ($lb_stock_logs as $log) {
        if (!lb_in_month($log['time']??'', $lb_start, $lb_end)) continue;
        $qty = abs((int)preg_replace('/[^0-9\-]/', '', $log['amount']??'0'));
        if (($log['type']??'') === 'MASUK STOK') $lb_stock_in += $qty;
        elseif (($log['type']??'') === 'KELUAR STOK') $lb_stock_out += $qty;
    }
    $lb_warn_meds = array_filter($lb_medicines, fn($m) => in_array($m['status']??'', ['Kritis','Rendah']));

    
    $lb_new_px     = array_filter($lb_patients, fn($px) => lb_in_month($px['reg_date']??'', $lb_start, $lb_end));
    $lb_month_pays = array_filter($lb_payments, fn($p)  => lb_in_month($p['date']??'', $lb_start, $lb_end));
    $lb_px_map     = array_column($lb_patients, 'name', 'id');
    $lb_printed_by = $_SESSION['user_name'] ?? 'Owner';
    $lb_net_stok   = $lb_stock_in - $lb_stock_out;

    
    ob_end_clean();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Laporan Bulanan <?php echo htmlspecialchars($lb_label); ?> — Klinik Medicare Pro</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Times New Roman', Times, serif;
    color: #000;
    background: #fff;
    font-size: 12pt;
    line-height: 1.6;
  }
  .page {
    padding: 28mm 25mm 20mm 30mm;
    max-width: 210mm;
    margin: 0 auto;
    background: #fff;
  }

  
  .letterhead {
    display: flex;
    align-items: center;
    gap: 20px;
    border-bottom: 3px solid #000;
    padding-bottom: 12px;
    margin-bottom: 6px;
  }
  .letterhead-logo {
    width: 64px; height: 64px;
    background: #000;
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 28px; font-weight: 900; flex-shrink: 0;
  }
  .letterhead-text { flex: 1; }
  .letterhead-text .clinic-name {
    font-size: 18pt; font-weight: 700; color: #000; letter-spacing: 1px; text-transform: uppercase;
  }
  .letterhead-text .clinic-subtitle {
    font-size: 10pt; color: #333; margin-top: 2px;
  }
  .letterhead-text .clinic-contact {
    font-size: 9pt; color: #555; margin-top: 2px;
  }
  .letterhead-bottom { border-bottom: 1px solid #999; margin-bottom: 14px; padding-bottom: 4px; }

  
  .report-title-block {
    text-align: center;
    margin: 16px 0 10px;
    padding: 10px;
    border: 1px solid #000;
  }
  .report-title-block .rt-main {
    font-size: 14pt; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #000;
  }
  .report-title-block .rt-sub {
    font-size: 11pt; font-weight: 600; color: #222; margin-top: 4px;
  }
  .report-title-block .rt-no {
    font-size: 9pt; color: #555; margin-top: 4px;
  }

  
  .meta-table { width: 100%; margin: 14px 0; font-size: 10.5pt; }
  .meta-table td { padding: 2px 6px; vertical-align: top; color: #000; }
  .meta-table td:first-child { width: 160px; font-weight: 600; }
  .meta-table td:nth-child(2) { width: 10px; }

  .sec-heading {
    font-size: 11pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #000;
    background: #e8e8e8;
    border-left: 5px solid #000;
    padding: 6px 12px;
    margin: 18px 0 8px;
  }
  
  .sec-heading.green,
  .sec-heading.blue,
  .sec-heading.amber,
  .sec-heading.purple,
  .sec-heading.rose  { border-color: #000; }

  
  table.formal {
    width: 100%; border-collapse: collapse; margin: 8px 0 14px; font-size: 10pt;
  }
  table.formal th {
    background: #000; color: #fff;
    padding: 6px 10px; text-align: left;
    font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    border: 1px solid #000;
  }
  table.formal td {
    padding: 6px 10px; border: 1px solid #999; vertical-align: top; color: #000;
  }
  table.formal tr:nth-child(even) { background: #f0f0f0; }
  table.formal .num { text-align: right; font-weight: 600; }
  table.formal .center { text-align: center; }

  
  .summary-grid { display: flex; gap: 10px; margin: 8px 0 14px; flex-wrap: wrap; }
  .sum-box { flex: 1; min-width: 110px; border: 1px solid #999; padding: 10px 12px; text-align: center; background: #fff; }
  .sum-box .sb-label { font-size: 8pt; color: #555; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
  .sum-box .sb-val   { font-size: 16pt; font-weight: 700; color: #000; }
  .sum-box .sb-sub   { font-size: 8pt; color: #666; margin-top: 2px; }
  
  .sum-box.hi-green,
  .sum-box.hi-yellow,
  .sum-box.hi-red    { background: #f0f0f0; border-color: #666; }

  
  .bar-item { margin-bottom: 7px; }
  .bar-label-row { display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 2px; color: #000; }
  .bar-bg { height: 8px; background: #ddd; }
  .bar-fill { height: 8px; background: #000 !important; }

  
  .doctor-card {
    border: 1px solid #999;
    padding: 12px 16px;
    margin-bottom: 12px;
    page-break-inside: avoid;
  }
  .doctor-card .dc-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 10px;
  }
  .doctor-card .dc-name { font-size: 12pt; font-weight: 700; color: #000; }
  .doctor-card .dc-sub  { font-size: 9pt; color: #444; margin-top: 2px; }
  .doctor-card .dc-sip  {
    font-size: 8pt; color: #fff; background: #000;
    padding: 3px 8px; font-weight: 600; letter-spacing: 0.5px;
  }
  .doctor-stats { display: flex; gap: 10px; margin-bottom: 8px; }
  .ds-item { flex: 1; border: 1px solid #ccc; padding: 8px 10px; text-align: center; background: #f8f8f8; }
  .ds-item .ds-val   { font-size: 14pt; font-weight: 700; color: #000; }
  .ds-item .ds-label { font-size: 8pt; color: #555; text-transform: uppercase; }

  
  .sig-section {
    margin-top: 30px;
    page-break-inside: avoid;
  }
  .sig-row { display: flex; gap: 20px; justify-content: space-between; flex-wrap: wrap; }
  .sig-col { flex: 1; min-width: 140px; text-align: center; }
  .sig-col .sig-role { font-size: 9pt; color: #333; margin-bottom: 60px; }
  .sig-col .sig-line { border-top: 1px solid #000; padding-top: 4px; }
  .sig-col .sig-name { font-weight: 700; font-size: 10.5pt; color: #000; }
  .sig-col .sig-title { font-size: 8.5pt; color: #555; }

  
  .divider { border: none; border-top: 1px solid #999; margin: 16px 0; }

  
  .footer-note {
    margin-top: 20px;
    border-top: 2px solid #000;
    padding-top: 8px;
    font-size: 8.5pt;
    color: #444;
    display: flex; justify-content: space-between;
  }

  @media print {
    body { padding: 0; }
    .no-print { display: none !important; }
    .page { padding: 15mm 20mm 15mm 25mm; }
  }
</style>

</head>
<body onload="window.print()">
<div class="page">

  
  
  
  <div class="letterhead">
    <div class="letterhead-logo">M</div>
    <div class="letterhead-text">
      <div class="clinic-name">Klinik Medicare Pro</div>
      <div class="clinic-subtitle">Klinik Umum &amp; Spesialis | Pelayanan Kesehatan Terpadu</div>
      <div class="clinic-contact">
        Jl. Kesehatan Raya No. 1, Jakarta Selatan &nbsp;|&nbsp; Telp: (021) 555-0100 &nbsp;|&nbsp; medicare.pro@klinik.id
      </div>
    </div>
  </div>
  <div class="letterhead-bottom"></div>

  
  
  
  <div class="report-title-block">
    <div class="rt-main">Laporan Operasional Bulanan</div>
    <div class="rt-sub">Periode: <?php echo htmlspecialchars($lb_label); ?></div>
    <div class="rt-no">No. Laporan: <?php echo htmlspecialchars($lb_doc_no); ?></div>
  </div>

  
  <table class="meta-table">
    <tr><td>Periode Laporan</td><td>:</td><td><?php echo date('d', strtotime($lb_start)); ?> s.d. <?php echo date('d F Y', strtotime($lb_end)); ?></td></tr>
    <tr><td>Dicetak Oleh</td><td>:</td><td><?php echo htmlspecialchars($lb_printed_by); ?> (Owner / Pimpinan Klinik)</td></tr>
    <tr><td>Tanggal Cetak</td><td>:</td><td><?php echo date('d F Y, H:i'); ?> WIB</td></tr>
    <tr><td>Status Dokumen</td><td>:</td><td>Resmi &amp; Rahasia Internal</td></tr>
  </table>

  <hr class="divider">

  
  
  
  <div class="sec-heading green">I. Ringkasan Keuangan</div>

  <div class="summary-grid">
    <div class="sum-box hi-green">
      <div class="sb-label">Total Pendapatan</div>
      <div class="sb-val" style="font-size:13pt;">Rp <?php echo number_format($lb_revenue,0,',','.'); ?></div>
      <div class="sb-sub"><?php echo $lb_success; ?> transaksi lunas</div>
    </div>
    <div class="sum-box hi-yellow">
      <div class="sb-label">Tagihan Pending</div>
      <div class="sb-val" style="font-size:13pt;">Rp <?php echo number_format($lb_pending,0,',','.'); ?></div>
      <div class="sb-sub"><?php echo $lb_trx - $lb_success; ?> belum lunas</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Total Transaksi</div>
      <div class="sb-val"><?php echo $lb_trx; ?></div>
      <div class="sb-sub">Semua status</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Rata-rata / Transaksi</div>
      <div class="sb-val" style="font-size:12pt;">Rp <?php echo number_format($lb_avg,0,',','.'); ?></div>
      <div class="sb-sub">Transaksi lunas</div>
    </div>
  </div>

  <?php if (!empty($lb_by_svc)): ?>
  <p style="font-size:10pt; font-weight:600; margin-bottom:4px;">Pendapatan per Jenis Layanan:</p>
  <?php $max_svc = max($lb_by_svc) ?: 1; $svc_clrs = ['#16a34a','#2563eb','#d97706','#dc2626','#7c3aed']; $sci=0;
  foreach (array_slice($lb_by_svc, 0, 6, true) as $sn => $sv): $pct = round(($sv/$max_svc)*100); $c = $svc_clrs[$sci++ % count($svc_clrs)]; ?>
  <div class="bar-item">
    <div class="bar-label-row"><span><?php echo htmlspecialchars($sn); ?></span><span>Rp <?php echo number_format($sv,0,',','.'); ?></span></div>
    <div class="bar-bg"><div class="bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $c; ?>;"></div></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($lb_month_pays)): ?>
  <p style="font-size:10pt; font-weight:600; margin: 10px 0 4px;">Rincian Transaksi Keuangan Bulan Ini:</p>
  <table class="formal">
    <thead>
      <tr><th>No.</th><th>ID Transaksi</th><th>Nama Pasien</th><th>Layanan</th><th>Metode</th><th>Total (Rp)</th><th>Status</th><th>Tanggal</th></tr>
    </thead>
    <tbody>
    <?php $no=1; foreach ($lb_month_pays as $mp):
        $sc = strtolower($mp['status']??'');
        $px_n = $lb_px_map[$mp['patient_id']] ?? $mp['patient_id'];
    ?>
      <tr>
        <td class="center"><?php echo $no++; ?></td>
        <td style="font-weight:700; font-family:monospace;"><?php echo htmlspecialchars($mp['id']); ?></td>
        <td><?php echo htmlspecialchars($px_n); ?></td>
        <td><?php echo htmlspecialchars($mp['service']??'-'); ?></td>
        <td><?php echo htmlspecialchars(strtoupper($mp['method']??'-')); ?></td>
        <td class="num"><?php echo number_format($mp['amount']??0,0,',','.'); ?></td>
        <td class="center"><b><?php echo htmlspecialchars($mp['status']); ?></b></td>
        <td><?php echo !empty($mp['date']) ? date('d/m/Y', strtotime($mp['date'])) : '-'; ?></td>
      </tr>
    <?php endforeach; ?>
      <tr style="background:#e8e8e8; font-weight:700;">
        <td colspan="5" style="text-align:right; padding-right:10px;">TOTAL PENDAPATAN TERVERIFIKASI</td>
        <td class="num" style="font-size:11pt;">Rp <?php echo number_format($lb_revenue,0,',','.'); ?></td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>

  <hr class="divider">

  
  
  
  <div class="sec-heading blue">II. Laporan Data Pasien</div>

  <div class="summary-grid">
    <div class="sum-box hi-green">
      <div class="sb-label">Pasien Baru</div>
      <div class="sb-val"><?php echo $lb_px_new; ?></div>
      <div class="sb-sub">Bulan ini</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Total Terdaftar</div>
      <div class="sb-val"><?php echo $lb_px_total; ?></div>
      <div class="sb-sub">Keseluruhan</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Antrean Selesai</div>
      <div class="sb-val"><?php echo $lb_q_done; ?></div>
      <div class="sb-sub">dari <?php echo $lb_q_total; ?> total</div>
    </div>
    <div class="sum-box hi-yellow">
      <div class="sb-label">Rujukan Keluar</div>
      <div class="sb-val"><?php echo $lb_px_ref; ?></div>
      <div class="sb-sub">Bulan ini</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Laki-laki</div>
      <div class="sb-val"><?php echo $lb_px_gm; ?></div>
      <div class="sb-sub"><?php echo round(($lb_px_gm / max($lb_px_gm+$lb_px_gf,1))*100); ?>%</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Perempuan</div>
      <div class="sb-val"><?php echo $lb_px_gf; ?></div>
      <div class="sb-sub"><?php echo round(($lb_px_gf / max($lb_px_gm+$lb_px_gf,1))*100); ?>%</div>
    </div>
  </div>

  <?php if (!empty($lb_new_px)): ?>
  <p style="font-size:10pt; font-weight:600; margin-bottom:4px;">Daftar Pasien Baru — <?php echo htmlspecialchars($lb_label); ?>:</p>
  <table class="formal">
    <thead>
      <tr><th>No.</th><th>No. Rekam Medis</th><th>Nama Pasien</th><th>Gender</th><th>Usia</th><th>No. Telepon</th><th>Tgl. Daftar</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php $no=1; foreach ($lb_new_px as $np): ?>
      <tr>
        <td class="center"><?php echo $no++; ?></td>
        <td style="font-weight:700; font-family:monospace;"><?php echo htmlspecialchars($np['id']); ?></td>
        <td><?php echo htmlspecialchars($np['name']); ?></td>
        <td class="center"><?php echo htmlspecialchars($np['gender']); ?></td>
        <td class="center"><?php echo $np['age']; ?> thn</td>
        <td><?php echo htmlspecialchars($np['phone']); ?></td>
        <td class="center"><?php echo date('d/m/Y', strtotime($np['reg_date'])); ?></td>
        <td class="center"><b><?php echo htmlspecialchars($np['status']??'Active'); ?></b></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p style="font-size:10pt; color:#555; font-style:italic; margin:6px 0 14px;">Tidak ada pasien baru pada periode <?php echo htmlspecialchars($lb_label); ?>.</p>
  <?php endif; ?>

  <hr class="divider">

  
  
  
  <div class="sec-heading amber">III. Laporan Inventaris Obat</div>

  <div class="summary-grid">
    <div class="sum-box hi-green">
      <div class="sb-label">Stok Aman</div>
      <div class="sb-val"><?php echo $lb_med_aman; ?></div>
      <div class="sb-sub">Jenis obat</div>
    </div>
    <div class="sum-box hi-yellow">
      <div class="sb-label">Stok Rendah</div>
      <div class="sb-val"><?php echo $lb_med_rendah; ?></div>
      <div class="sb-sub">Perlu restock</div>
    </div>
    <div class="sum-box hi-red">
      <div class="sb-label">Stok Kritis</div>
      <div class="sb-val"><?php echo $lb_med_kritis; ?></div>
      <div class="sb-sub">Restock darurat</div>
    </div>
    <div class="sum-box">
      <div class="sb-label">Nilai Inventaris</div>
      <div class="sb-val" style="font-size:11pt;">Rp <?php echo number_format($lb_med_val,0,',','.'); ?></div>
      <div class="sb-sub">Stok × harga jual</div>
    </div>
    <div class="sum-box hi-green">
      <div class="sb-label">Unit Masuk</div>
      <div class="sb-val">+<?php echo $lb_stock_in; ?></div>
      <div class="sb-sub">Bulan ini</div>
    </div>
    <div class="sum-box hi-red">
      <div class="sb-label">Unit Keluar</div>
      <div class="sb-val">-<?php echo $lb_stock_out; ?></div>
      <div class="sb-sub">Bulan ini</div>
    </div>
  </div>

  <p style="font-size:10pt; font-weight:600; margin-bottom:4px;">Daftar Seluruh Obat:</p>
  <table class="formal">
    <thead>
      <tr><th>No.</th><th>Nama Obat</th><th>Kategori</th><th>Harga Jual (Rp)</th><th>Stok Saat Ini</th><th>Stok Maks.</th><th>% Sisa</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php $no=1; foreach ($lb_medicines as $m): $pct_stok = $m['max_stock']>0 ? round(($m['stock']/$m['max_stock'])*100) : 0; ?>
      <tr>
        <td class="center"><?php echo $no++; ?></td>
        <td style="font-weight:600;"><?php echo htmlspecialchars($m['name']); ?></td>
        <td><?php echo htmlspecialchars($m['category']); ?></td>
        <td class="num"><?php echo number_format($m['price'],0,',','.'); ?></td>
        <td class="center"><?php echo $m['stock']; ?> unit</td>
        <td class="center"><?php echo $m['max_stock']; ?> unit</td>
        <td class="center"><?php echo $pct_stok; ?>%</td>
        <td class="center"><b><?php echo htmlspecialchars($m['status']); ?></b></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <p style="font-size:9.5pt; color:#555;">
    Net perubahan stok bulan ini:
    <strong>
      <?php echo ($lb_net_stok >= 0 ? '+' : '') . $lb_net_stok; ?> unit
    </strong>
  </p>

  <hr class="divider">

  
  
  
  <div class="sec-heading rose">IV. Kinerja Tenaga Medis (Per Dokter)</div>

  <?php if (!empty($lb_doctors)): ?>
    <?php $dokter_idx = 1; foreach ($lb_doctors as $doc): ?>
    <div class="doctor-card">
      <div class="dc-header">
        <div>
          <div class="dc-name"><?php echo htmlspecialchars($doc['name']); ?></div>
          <div class="dc-sub">
            <?php echo htmlspecialchars($doc['specialization']); ?> &nbsp;|&nbsp;
            Email: <?php echo htmlspecialchars($doc['username']); ?>
          </div>
        </div>
        <div>
          <div class="dc-sip">No. SIP: <?php echo htmlspecialchars($doc['sip']); ?></div>
          <div style="font-size:8pt; color:#444; margin-top:4px; text-align:right;">Status: <b>Aktif</b></div>
        </div>
      </div>

      <div class="doctor-stats">
        <div class="ds-item">
          <div class="ds-val"><?php echo $doc['resep_count']; ?></div>
          <div class="ds-label">Resep Ditulis</div>
        </div>
        <div class="ds-item">
          <div class="ds-val"><?php echo count($doc['patients']); ?></div>
          <div class="ds-label">Pasien Ditangani</div>
        </div>
        <div class="ds-item">
          <div class="ds-val" style="font-size:11pt;">Rp <?php echo number_format($doc['revenue'],0,',','.'); ?></div>
          <div class="ds-label">Pendapatan dari Resep</div>
        </div>
        <div class="ds-item">
          <div class="ds-val"><?php echo $lb_q_done; ?></div>
          <div class="ds-label">Antrean Selesai*</div>
        </div>
      </div>

      <?php if (!empty($doc['patients'])):
        $doc_px_list = array_filter($lb_patients, fn($px) => in_array($px['id'], $doc['patients']));
      ?>
      <p style="font-size:9pt; font-weight:600; margin-bottom:4px; color:#334155;">Daftar Pasien yang Ditangani:</p>
      <table class="formal" style="font-size:9pt;">
        <thead>
          <tr><th>No.</th><th>No. Rekam Medis</th><th>Nama Pasien</th><th>Gender</th><th>Usia</th></tr>
        </thead>
        <tbody>
        <?php $dno=1; foreach ($doc_px_list as $dpx): ?>
          <tr>
            <td class="center"><?php echo $dno++; ?></td>
            <td style="font-family:monospace; font-weight:600;"><?php echo htmlspecialchars($dpx['id']); ?></td>
            <td><?php echo htmlspecialchars($dpx['name']); ?></td>
            <td class="center"><?php echo htmlspecialchars($dpx['gender']); ?></td>
            <td class="center"><?php echo $dpx['age']; ?> thn</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="font-size:9pt; color:#64748b; font-style:italic;">
        Belum ada data pasien/resep tercatat untuk dokter ini pada periode <?php echo htmlspecialchars($lb_label); ?>.
      </p>
      <?php endif; ?>
    </div>
    <?php $dokter_idx++; endforeach; ?>
    <p style="font-size:8.5pt; color:#64748b; margin-top:-6px;">* Antrean selesai adalah data agregat klinik, belum terpetakan per dokter secara individual.</p>
  <?php else: ?>
    <p style="font-size:10pt; color:#64748b; font-style:italic;">Tidak ada data dokter terdaftar.</p>
  <?php endif; ?>

  <hr class="divider">

  
  
  
  <p style="font-size:10.5pt; line-height:1.8; margin: 10px 0 24px;">
    Demikian laporan operasional bulanan Klinik Medicare Pro untuk periode
    <strong><?php echo htmlspecialchars($lb_label); ?></strong> ini disusun secara resmi dan dapat dipertanggungjawabkan.
    Laporan ini bersifat rahasia internal dan hanya diperuntukkan bagi pihak manajemen klinik.
  </p>

  
  <div class="sig-section">
    <div class="sig-row">
      
      <div class="sig-col">
        <div class="sig-role">Mengetahui,<br>Owner / Pimpinan Klinik</div>
        <div class="sig-line">
          <div class="sig-name"><?php echo htmlspecialchars($lb_printed_by); ?></div>
          <div class="sig-title">Owner — Klinik Medicare Pro</div>
        </div>
      </div>
      
      <?php foreach ($lb_doctors as $doc): ?>
      <div class="sig-col">
        <div class="sig-role">Dokter Penanggung Jawab</div>
        <div class="sig-line">
          <div class="sig-name"><?php echo htmlspecialchars($doc['name']); ?></div>
          <div class="sig-title">SIP: <?php echo htmlspecialchars($doc['sip']); ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  
  <div class="footer-note">
    <span>No. Laporan: <?php echo htmlspecialchars($lb_doc_no); ?> | Klinik Medicare Pro &copy; <?php echo $lb_year; ?></span>
    <span>Dicetak: <?php echo date('d/m/Y H:i'); ?> WIB</span>
  </div>

</div>
</body>
</html>
<?php
    exit;

} else {
    echo "Tipe laporan tidak valid.";
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Medicare Pro</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            padding: 30px;
            background-color: white;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-icon {
            background-color: #0f172a;
            color: white;
            font-weight: 800;
            font-size: 20px;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-text {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 15px;
        }
        .subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .info {
            text-align: right;
            font-size: 11px;
            color: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
        }
        th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge.success { background-color: #d1fae5; color: #065f46; }
        .badge.warning { background-color: #fef3c7; color: #92400e; }
        .badge.danger { background-color: #fee2e2; color: #991b1b; }
        .badge.pending { background-color: #e0f2fe; color: #0369a1; }
        
        .total-summary {
            margin-top: 30px;
            border-top: 2px solid #e2e8f0;
            padding-top: 15px;
            display: flex;
            justify-content: flex-end;
        }
        .total-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 20px;
            text-align: right;
        }
        .total-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .total-val {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <div>
            <div class="logo-area">
                <div class="logo-icon">M</div>
                <div class="logo-text">Medicare Pro</div>
            </div>
            <div class="title"><?php echo htmlspecialchars($title); ?></div>
            <div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div>
        </div>
        <div class="info">
            <div>Dicetak Oleh: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Sistem'); ?></strong></div>
            <div style="margin-top: 4px;">Tanggal Cetak: <?php echo date('d M Y, H:i'); ?> WIB</div>
        </div>
    </div>

    <?php if ($type === 'rujukan'): 
        
        $px_details = null;
        foreach ($patients as $p) {
            if ($p['id'] === $data['patient_id'] || $p['name'] === $data['patient_name']) {
                $px_details = $p;
                break;
            }
        }
        $px_gender = $px_details['gender'] ?? 'Laki-laki';
        $px_age = $px_details['age'] ?? '-';
        $px_address = $px_details['address'] ?? '-';
        $px_id = $px_details['id'] ?? $data['patient_id'];
    ?>
        <div style="margin-top: 30px; line-height: 1.8; font-size: 14px; max-width: 800px; margin-left: auto; margin-right: auto;">
            <p><strong>Kepada Yth.</strong><br>
            <strong>Teman Sejawat Dokter / Unit Terkait</strong><br>
            Di <?php echo htmlspecialchars($data['hospital']); ?></p>

            <p style="margin-top: 20px;">Dengan hormat,</p>
            <p>Mohon pemeriksaan dan penanganan lebih lanjut terhadap pasien berikut ini:</p>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 25px;">
                <tr style="background: none;">
                    <td style="width: 25%; border: none; padding: 4px 0; font-weight: bold;">Nama Pasien</td>
                    <td style="border: none; padding: 4px 0;">: <?php echo htmlspecialchars($data['patient_name']); ?></td>
                </tr>
                <tr style="background: none;">
                    <td style="border: none; padding: 4px 0; font-weight: bold;">No. Rekam Medis</td>
                    <td style="border: none; padding: 4px 0;">: <?php echo htmlspecialchars($px_id); ?></td>
                </tr>
                <tr style="background: none;">
                    <td style="border: none; padding: 4px 0; font-weight: bold;">Umur / Gender</td>
                    <td style="border: none; padding: 4px 0;">: <?php echo htmlspecialchars($px_age); ?> Tahun / <?php echo htmlspecialchars($px_gender); ?></td>
                </tr>
                <tr style="background: none;">
                    <td style="border: none; padding: 4px 0; font-weight: bold;">Alamat</td>
                    <td style="border: none; padding: 4px 0;">: <?php echo htmlspecialchars($px_address); ?></td>
                </tr>
            </table>

            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div style="font-weight: bold; color: #0f172a; margin-bottom: 8px;">1. Diagnosis Sementara:</div>
                <div style="color: #334155; font-style: italic;"><?php echo htmlspecialchars($data['diagnosis'] ?? 'Suspek Medis'); ?></div>
            </div>

            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 30px;">
                <div style="font-weight: bold; color: #0f172a; margin-bottom: 8px;">2. Keterangan / Alasan Rujukan:</div>
                <div style="color: #334155; white-space: pre-line;"><?php echo htmlspecialchars($data['reason'] ?? 'Pemeriksaan lebih lanjut'); ?></div>
            </div>

            <p>Demikian surat rujukan ini kami buat untuk dipergunakan sebagaimana mestinya. Atas perhatian dan kerja samanya kami ucapkan terima kasih.</p>

            <?php if ($data['status'] === 'DIBATALKAN'): ?>
                <div style="border: 3px double #ef4444; color: #ef4444; font-weight: 800; font-size: 24px; padding: 10px 20px; width: fit-content; margin: 30px auto; transform: rotate(-5deg); text-transform: uppercase; border-radius: 6px; letter-spacing: 2px; text-align: center; opacity: 0.85;">
                    Rujukan Dibatalkan
                </div>
            <?php endif; ?>

            <div style="margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div></div>
                <div style="text-align: center; width: 200px;">
                    <div>Jakarta, <?php echo date('d M Y', strtotime($data['date'])); ?></div>
                    <div style="margin-top: 80px; font-weight: bold; text-decoration: underline;">Dr. Raka Aji</div>
                    <div style="font-size: 11px; color: #64748b;">SIP. SIP-12345-2026</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php if ($type === 'resep'): ?>
        <div style="margin-bottom:20px; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px;">
            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px;">Informasi Resep</div>
            <table style="width:100%; border-collapse:collapse; border:none;">
                <tr style="background:none;">
                    <td style="border:none; padding:4px 0; width:130px; font-weight:bold; color:#334155;">Pasien</td>
                    <td style="border:none; padding:4px 0;">: <strong><?php echo htmlspecialchars($presc_patient_name); ?></strong></td>
                    <td style="border:none; padding:4px 0; width:130px; font-weight:bold; color:#334155;">No. Rekam Medis</td>
                    <td style="border:none; padding:4px 0;">: <?php echo htmlspecialchars($presc_patient); ?></td>
                </tr>
                <tr style="background:none;">
                    <td style="border:none; padding:4px 0; font-weight:bold; color:#334155;">Dokter</td>
                    <td style="border:none; padding:4px 0;">: <?php echo htmlspecialchars($presc_doctor); ?></td>
                    <td style="border:none; padding:4px 0; font-weight:bold; color:#334155;">Tanggal Resep</td>
                    <td style="border:none; padding:4px 0;">: <?php echo htmlspecialchars($presc_date); ?> WIB</td>
                </tr>
                <tr style="background:none;">
                    <td style="border:none; padding:4px 0; font-weight:bold; color:#334155;">ID Transaksi</td>
                    <td style="border:none; padding:4px 0;" colspan="3">: <?php echo htmlspecialchars($prescription_id); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        <table>
            <?php if ($type === 'antrean'): ?>
                <thead>
                    <tr>
                        <th>No. Antrean</th>
                        <th>Waktu Jadwal</th>
                        <th>Nama Pasien</th>
                        <th>Poli/Layanan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $q): 
                        $patient_name = 'Pasien';
                        foreach ($patients as $p) {
                            if ($p['id'] === $q['patient_id']) {
                                $patient_name = $p['name'];
                                break;
                            }
                        }
                        $status_class = 'warning';
                        if ($q['status'] === 'SELESAI') $status_class = 'success';
                        elseif ($q['status'] === 'DIPERIKSA') $status_class = 'pending';
                    ?>
                    <tr>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($q['no']); ?></td>
                        <td><?php echo htmlspecialchars($q['time']); ?> WIB</td>
                        <td><?php echo htmlspecialchars($patient_name); ?> (<?php echo htmlspecialchars($q['patient_id']); ?>)</td>
                        <td><?php echo htmlspecialchars($q['poly']); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($q['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php elseif ($type === 'keuangan'): ?>
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
                    <?php 
                    $grand_total = 0;
                    foreach ($data as $p): 
                        $patient_name = 'Pasien';
                        foreach ($patients as $pt) {
                            if ($pt['id'] === $p['patient_id']) {
                                $patient_name = $pt['name'];
                                break;
                            }
                        }
                        if ($p['status'] === 'Success') {
                            $grand_total += $p['amount'];
                        }
                        $status_class = ($p['status'] === 'Success') ? 'success' : 'warning';
                    ?>
                    <tr>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($p['id']); ?></td>
                        <td><?php echo htmlspecialchars($patient_name); ?></td>
                        <td><?php echo htmlspecialchars($p['service']); ?></td>
                        <td><?php echo htmlspecialchars($p['method']); ?></td>
                        <td style="font-weight: 600;">Rp <?php echo number_format($p['amount'], 0, ',', '.'); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                        <td><?php echo date('d M Y, H:i', strtotime($p['date'])); ?> WIB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php elseif ($type === 'log_stok'): ?>
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
                    <?php foreach ($data as $log): 
                        $is_in = ($log['type'] === 'MASUK STOK');
                        $status_class = $is_in ? 'success' : 'danger';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['time']); ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($log['name']); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($log['type']); ?></span></td>
                        <td><?php echo htmlspecialchars($log['desc']); ?></td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($log['amount']); ?></td>
                        <td><?php echo htmlspecialchars($log['user']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php elseif ($type === 'pembayaran'): ?>
                <thead>
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Pasien</th>
                        <th>Layanan</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total = 0;
                    foreach ($data as $p): 
                        $patient_name = 'Pasien';
                        foreach ($patients as $pt) {
                            if ($pt['id'] === $p['patient_id']) {
                                $patient_name = $pt['name'];
                                break;
                            }
                        }
                        if ($p['status'] === 'Success') {
                            $grand_total += $p['amount'];
                        }
                        $status_class = ($p['status'] === 'Success') ? 'success' : 'warning';
                    ?>
                    <tr>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($p['id']); ?></td>
                        <td><?php echo htmlspecialchars($patient_name); ?> (<?php echo htmlspecialchars($p['patient_id']); ?>)</td>
                        <td><?php echo htmlspecialchars($p['service']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($p['date'])); ?> WIB</td>
                        <td style="font-weight: 600;">Rp <?php echo number_format($p['amount'], 0, ',', '.'); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php elseif ($type === 'obat'): ?>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Obat</th>
                        <th>Kategori</th>
                        <th>Harga Jual</th>
                        <th>Stok Saat Ini</th>
                        <th>Kapasitas Maksimal</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $m): 
                        $status_class = 'success';
                        if ($m['status'] === 'Kritis') $status_class = 'danger';
                        elseif ($m['status'] === 'Rendah') $status_class = 'warning';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['id']); ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['category']); ?></td>
                        <td>Rp <?php echo number_format($m['price'], 0, ',', '.'); ?></td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($m['stock']); ?> Unit</td>
                        <td><?php echo htmlspecialchars($m['max_stock']); ?> Unit</td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($m['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php elseif ($type === 'resep'): ?>
    <thead>
        <tr><th style="text-align:center; width:40px;">#</th><th>Nama Obat</th><th style="text-align:center;">Jumlah</th><th>Aturan Pakai</th><th style="text-align:right;">Harga Satuan</th></tr>
    </thead>
    <tbody>
        <?php
        
        $medicines_for_pdf = $_SESSION['medicines'] ?? [];
        $grand_total_resep = 0;
        foreach ($data as $idx => $item):
            $item_name  = $item['name']  ?? $item['medicine'] ?? '';
            $item_qty   = $item['qty']   ?? $item['quantity'] ?? 0;
            $item_rules = $item['rules'] ?? $item['usage']   ?? '';
            
            $item_price = 0;
            foreach ($medicines_for_pdf as $med) {
                if (stripos($med['name'], $item_name) !== false || stripos($item_name, $med['name']) !== false) {
                    $item_price = $med['price'];
                    break;
                }
            }
            $grand_total_resep += $item_price * $item_qty;
        ?>
        <tr>
            <td style="text-align:center;"><?php echo $idx + 1; ?></td>
            <td style="font-weight:700;"><?php echo htmlspecialchars($item_name); ?></td>
            <td style="text-align:center;"><?php echo htmlspecialchars($item_qty); ?> Unit</td>
            <td><?php echo htmlspecialchars($item_rules); ?></td>
            <td style="text-align:right;"><?php echo $item_price > 0 ? 'Rp ' . number_format($item_price, 0, ',', '.') : '-'; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
<?php endif; ?>
        </table>

    <?php if ($type === 'resep'): ?>
        <?php if (!empty($presc_notes)): ?>
            <div style="margin-top:24px; background-color:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:14px 16px;">
                <div style="font-weight:700; color:#92400e; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Catatan untuk Apoteker:</div>
                <div style="color:#92400e; font-size:13px; line-height:1.6; white-space:pre-line;"><?php echo htmlspecialchars($presc_notes); ?></div>
            </div>
        <?php endif; ?>

        <div class="total-summary">
            <div class="total-box">
                <div class="total-label">Estimasi Total Tagihan Resep</div>
                <div class="total-val">Rp <?php echo number_format($grand_total_resep, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div style="margin-top:50px; display:flex; justify-content:space-between; align-items:flex-end;">
            <div style="font-size:12px; color:#64748b; line-height:1.8;">
                <div><strong>Pasien:</strong> <?php echo htmlspecialchars($presc_patient_name); ?></div>
                <?php if (!empty($presc_patient)): ?><div><strong>No. RM:</strong> <?php echo htmlspecialchars($presc_patient); ?></div><?php endif; ?>
                <div><strong>Tanggal Resep:</strong> <?php echo htmlspecialchars($presc_date); ?> WIB</div>
            </div>
            <div style="text-align:center; width:200px;">
                <div style="font-size:12px;">Jakarta, <?php echo date('d M Y', strtotime($presc_date)); ?></div>
                <div style="margin-top:70px; font-weight:bold; text-decoration:underline;"><?php echo htmlspecialchars($presc_doctor); ?></div>
                <div style="font-size:11px; color:#64748b;">SIP. SIP-12345-2026</div>
            </div>
        </div>
    <?php endif; ?>

    <?php endif; ?>

    <?php if (in_array($type, ['keuangan', 'pembayaran'])): ?>
        <div class="total-summary">
            <div class="total-box">
                <div class="total-label">Total Pendapatan Terverifikasi</div>
                <div class="total-val">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></div>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
