<?php


$success_msg = '';
if (isset($_GET['action']) && $_GET['action'] === 'pay' && isset($_GET['id'])) {
    $trx_id = $_GET['id'];
    foreach ($_SESSION['payments'] as &$p) {
        if ($p['id'] === $trx_id) {
            $p['status'] = 'Success';
            db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'PAYMENT', "Penyelesaian Pembayaran Invoice #$trx_id");
            $success_msg = "Pembayaran Invoice <strong>$trx_id</strong> berhasil diselesaikan!";
            break;
        }
    }
    unset($p);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_bill') {
    $patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
    $service = isset($_POST['service']) ? $_POST['service'] : '';
    $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
    $method = isset($_POST['method']) ? $_POST['method'] : '';

    if (!empty($patient_id) && !empty($service) && $amount > 0) {
        $trx_id = db_generate_payment_id();
        $_SESSION['payments'][] = [
            'id' => $trx_id,
            'patient_id' => $patient_id,
            'date' => date('Y-m-d H:i'),
            'service' => $service,
            'method' => $method,
            'amount' => $amount,
            'status' => 'Pending'
        ];
        db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'PAYMENT', "Pembuatan Tagihan Manual #$trx_id untuk pasien $patient_id");
        $success_msg = "Invoice tagihan baru <strong>$trx_id</strong> berhasil dibuat dengan status Pending!";
    }
}


$payments = $_SESSION['payments'];
$patients = db_get_patients();

$total_revenue = 0;
$pending_count = 0;
$success_count = 0;

foreach ($payments as $p) {
    if ($p['status'] === 'Success') {
        $total_revenue += $p['amount'];
        $success_count++;
    } else {
        $pending_count++;
    }
}

$average_bill = ($success_count + $pending_count) > 0
    ? ($total_revenue / max($success_count, 1))
    : 0;
?>

<div class="content-header">
    <div>
        <h1>Manajemen Pembayaran</h1>
        <p class="page-title-desc">Kelola tagihan dan transaksi pasien Anda.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="export_pdf.php?type=pembayaran" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export PDF
        </a>
        <button class="btn btn-primary" onclick="openBillingModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Input Tagihan Manual
        </button>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>


<div class="stats-grid">
    <div class="stat-card safe">
        <div class="stat-header">Total Pendapatan</div>
        <div class="stat-value">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Dari transaksi selesai</span>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-header">Transaksi Pending</div>
        <div class="stat-value"><?php echo $pending_count; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Menunggu pembayaran pasien</span>
        </div>
    </div>
    <div class="stat-card pending">
        <div class="stat-header">Transaksi Selesai</div>
        <div class="stat-value"><?php echo $success_count; ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted); font-weight: 500;">Sudah terverifikasi</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Rata-rata Tagihan</div>
        <div class="stat-value">Rp <?php echo number_format($average_bill, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span style="color: var(--text-muted);">Per transaksi medis</span>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Riwayat Transaksi Terkini</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Pasien</th>
                    <th>Layanan</th>
                    <th>Tanggal</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                        Belum ada transaksi pembayaran.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($payments as $p): 
                    $patient_name = 'Pasien';
                    $patient_id = '';
                    foreach ($patients as $pt) {
                        if ($pt['id'] === $p['patient_id']) {
                            $patient_name = $pt['name'];
                            $patient_id = $pt['id'];
                            break;
                        }
                    }
                    
                    $status_class = strtolower($p['status']);
                    if ($status_class === 'pending') $status_class = 'warning';
                    elseif ($status_class === 'success') $status_class = 'success';
                    elseif ($status_class === 'gagal') $status_class = 'danger';
                ?>
                <tr>
                    <td style="font-weight: 700; color: #334155;"><?php echo htmlspecialchars($p['id']); ?></td>
                    <td>
                        <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($patient_name); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($patient_id); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($p['service']); ?></td>
                    <td><?php echo date('d M Y, H:i', strtotime($p['date'])); ?> WIB</td>
                    <td style="font-weight: 700; color: #0f172a;">Rp <?php echo number_format($p['amount'], 0, ',', '.'); ?></td>
                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <?php if ($p['status'] === 'Pending'): ?>
                                <a href="dashboard.php?page=pembayaran&action=pay&id=<?php echo $p['id']; ?>" class="btn btn-success" style="padding: 6px 12px; font-size: 12px;">Bayar</a>
                            <?php endif; ?>
                            <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="openPrescriptionModal('<?php echo $p['id']; ?>')">Detail</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal-backdrop" id="addBillModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Input Tagihan Manual</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeBillingModal()">&times;</button>
        </div>
        <form method="POST" action="dashboard.php?page=pembayaran&action=add_bill">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="bill_patient">Pilih Pasien</label>
                    <select name="patient_id" id="bill_patient" class="form-control" required>
                        <option value="">-- Pilih Pasien --</option>
                        <?php foreach ($patients as $pt): ?>
                            <option value="<?php echo $pt['id']; ?>">
                                <?php echo htmlspecialchars($pt['name']); ?> (<?php echo htmlspecialchars($pt['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="bill_service">Layanan Medis</label>
                    <input type="text" name="service" id="bill_service" class="form-control" placeholder="Contoh: Apotek / Konsultasi Dokter" required>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="bill_amount">Nominal Tagihan (Rp)</label>
                        <input type="number" name="amount" id="bill_amount" class="form-control" placeholder="Contoh: 150000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="bill_method">Metode Pembayaran</label>
                        <select name="method" id="bill_method" class="form-control">
                            <option value="CASH">Cash / Tunai</option>
                            <option value="DEBIT CARD">Kartu Debit</option>
                            <option value="QRIS">QRIS / LinkAja</option>
                            <option value="ASURANSI">Asuransi / BPJS</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeBillingModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Tagihan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openBillingModal() {
        document.getElementById('addBillModal').style.display = 'flex';
    }
    function closeBillingModal() {
        document.getElementById('addBillModal').style.display = 'none';
    }
</script>
<script>
  
  const prescriptions = <?= json_encode($_SESSION['prescriptions'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

  
  const patientsMap = <?= json_encode(array_column(db_get_patients(), 'name', 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

  function openPrescriptionModal(id) {
    const overlay = document.getElementById('prescriptionOverlay');
    const data = prescriptions[id];

    
    document.getElementById('prescTrxId').textContent = id;

    if (!data || !data.items || data.items.length === 0) {
      
      document.getElementById('prescInfoArea').innerHTML =
        '<div style="padding:20px; text-align:center; color:#64748b; font-size:14px;">Tagihan ini tidak memiliki data resep obat (tagihan manual).</div>';
      document.getElementById('prescTableWrapper').style.display = 'none';
      document.getElementById('prescNotesArea').style.display = 'none';
      document.getElementById('prescriptionPdfBtn').style.display = 'none';
    } else {
      const patientName = patientsMap[data.patient_id] || data.patient_id || '-';
      document.getElementById('prescInfoArea').innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 20px; font-size:13px; margin-bottom:16px;">
          <div><span style="color:#64748b;">Pasien:</span> <strong>${patientName}</strong></div>
          <div><span style="color:#64748b;">Dokter:</span> <strong>${data.doctor || '-'}</strong></div>
          <div><span style="color:#64748b;">Tanggal:</span> <strong>${data.date || '-'}</strong></div>
          <div><span style="color:#64748b;">ID Transaksi:</span> <strong>${id}</strong></div>
        </div>`;

      const listContainer = document.getElementById('prescriptionList');
      listContainer.innerHTML = '';
      data.items.forEach((item, idx) => {
        const row = document.createElement('tr');
        
        const nama = item.name || item.medicine || '-';
        const qty  = item.qty  || item.quantity  || '-';
        const rule = item.rules|| item.usage     || '-';
        row.innerHTML = `<td style="text-align:center;">${idx + 1}</td><td><strong>${nama}</strong></td><td style="text-align:center;">${qty} Unit</td><td>${rule}</td>`;
        listContainer.appendChild(row);
      });

      document.getElementById('prescTableWrapper').style.display = 'block';

      
      const notesArea = document.getElementById('prescNotesArea');
      if (data.notes && data.notes.trim() !== '') {
        document.getElementById('prescNotesText').textContent = data.notes;
        notesArea.style.display = 'block';
      } else {
        notesArea.style.display = 'none';
      }

      
      const pdfBtn = document.getElementById('prescriptionPdfBtn');
      pdfBtn.href = `export_pdf.php?type=resep&id=${id}`;
      pdfBtn.style.display = 'inline-flex';
    }

    overlay.style.display = 'flex';
  }

  function closePrescriptionModal() {
    document.getElementById('prescriptionOverlay').style.display = 'none';
  }
</script>


<div class="modal-backdrop" id="prescriptionOverlay" style="display:none; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; border-radius:16px; max-width:640px; width:100%; margin:20px; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <div style="color:white; font-size:16px; font-weight:700;">Detail Resep Obat</div>
        <div style="color:#94a3b8; font-size:12px; margin-top:2px;">ID: <span id="prescTrxId" style="font-weight:600; color:#60a5fa;"></span></div>
      </div>
      <button onclick="closePrescriptionModal()" style="background:rgba(255,255,255,0.15); border:none; color:white; width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center;">&times;</button>
    </div>

    
    <div style="padding:20px 24px;">
      
      <div id="prescInfoArea"></div>

      
      <div id="prescTableWrapper">
        <div style="font-size:12px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">Daftar Obat</div>
        <div style="border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;">
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:10px 12px; text-align:center; font-size:11px; color:#64748b; font-weight:700; width:40px;">#</th>
                <th style="padding:10px 12px; font-size:11px; color:#64748b; font-weight:700;">Nama Obat</th>
                <th style="padding:10px 12px; text-align:center; font-size:11px; color:#64748b; font-weight:700; width:80px;">Jumlah</th>
                <th style="padding:10px 12px; font-size:11px; color:#64748b; font-weight:700;">Aturan Pakai</th>
              </tr>
            </thead>
            <tbody id="prescriptionList"></tbody>
          </table>
        </div>
      </div>

      
      <div id="prescNotesArea" style="margin-top:16px; display:none;">
        <div style="font-size:12px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">Catatan untuk Apoteker</div>
        <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; padding:14px 16px; font-size:13px; color:#92400e; line-height:1.6;" id="prescNotesText"></div>
      </div>
    </div>

    
    <div style="padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:flex-end; gap:10px;">
      <button onclick="closePrescriptionModal()" class="btn btn-secondary">Tutup</button>
      <a id="prescriptionPdfBtn" href="#" target="_blank" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Cetak PDF
      </a>
    </div>
  </div>
</div>
