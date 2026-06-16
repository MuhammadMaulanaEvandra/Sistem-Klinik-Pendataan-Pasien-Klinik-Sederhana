<?php



$admin_name = $_SESSION['user_name'] ?? 'Admin Utama';
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add') {
    $patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
    $hospital = isset($_POST['hospital']) ? $_POST['hospital'] : '';
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if (!empty($patient_id) && !empty($hospital) && !empty($date) && !empty($diagnosis)) {
        
        $patient_name = 'Pasien';
        foreach ($_SESSION['patients'] as $p) {
            if ($p['id'] === $patient_id) {
                $patient_name = $p['name'];
                break;
            }
        }
        
        $ref_id = db_generate_referral_id();
        
        $_SESSION['referrals'][] = [
            'id' => $ref_id,
            'patient_name' => $patient_name,
            'patient_id' => $patient_id,
            'hospital' => $hospital,
            'date' => $date,
            'status' => 'DIKIRIM',
            'diagnosis' => $diagnosis,
            'reason' => $reason
        ];
        
        db_add_audit($admin_name, 'ADMIN', 'REFERRAL', "Pembuatan Rujukan #$ref_id ke $hospital untuk pasien $patient_name");
        $success_msg = "Surat Rujukan <strong>$ref_id</strong> untuk <strong>" . htmlspecialchars($patient_name) . "</strong> berhasil disimpan! <a href='export_pdf.php?type=rujukan&id=$ref_id' target='_blank' style='color: #1e3a8a; text-decoration: underline; font-weight: bold;'>Cetak PDF Surat Rujukan</a>";
    } else {
        $error_msg = "Mohon lengkapi seluruh field yang wajib diisi!";
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $ref_id = $_GET['id'];
    foreach ($_SESSION['referrals'] as &$r) {
        if ($r['id'] === $ref_id) {
            $r['status'] = 'DIBATALKAN';
            db_add_audit($admin_name, 'ADMIN', 'REFERRAL', "Pembatalan Rujukan #$ref_id");
            $success_msg = "Rujukan <strong>$ref_id</strong> ditandai batal.";
            break;
        }
    }
    unset($r);
}


$patients = db_get_patients();
$referrals = $_SESSION['referrals'];
?>

<div class="content-header">
    <div>
        <h1>Rujukan Pasien</h1>
        <p class="page-title-desc">Kelola dan buat surat rujukan pasien ke rumah sakit mitra secara cepat dan akurat.</p>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div style="background-color: var(--status-critical-bg); border: 1px solid var(--status-critical); color: #991b1b; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<div class="grid-2col">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Buat Surat Rujukan Baru</h3>
        </div>
        <form method="POST" action="dashboard.php?page=rujukan&action=add">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="ref_patient">Cari / Pilih Pasien</label>
                    <select name="patient_id" id="ref_patient" class="form-control" required>
                        <option value="">-- Pilih Nama Pasien --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="ref_hosp">Rumah Sakit Tujuan</label>
                        <select name="hospital" id="ref_hosp" class="form-control" required>
                            <option value="">-- Pilih RS Mitra --</option>
                            <option value="RS Dr. Cipto">RS Dr. Cipto Mangunkusumo</option>
                            <option value="RS Medistra">RS Medistra</option>
                            <option value="RS Siloam">RS Siloam</option>
                            <option value="RS Pusat Angkatan Darat">RS Pusat Angkatan Darat (RSPAD)</option>
                            <option value="RS Jantung Harapan Kita">RS Jantung Harapan Kita</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ref_date">Tanggal Rujukan</label>
                        <input type="date" name="date" id="ref_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ref_diag">Diagnosis Sementara</label>
                    <input type="text" name="diagnosis" id="ref_diag" class="form-control" placeholder="Contoh: Suspek Appendicitis / Hipertensi Grade II" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ref_reason">Alasan Rujukan</label>
                    <textarea name="reason" id="ref_reason" class="form-control" placeholder="Berikan deskripsi alasan medis rujukan..." required></textarea>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 20px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="reset" class="btn btn-secondary">Reset Form</button>
                <button type="submit" class="btn btn-primary">Cetak & Simpan Rujukan</button>
            </div>
        </form>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Riwayat Rujukan</h3>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>No. Rujukan</th>
                        <th>Pasien</th>
                        <th>RS Tujuan</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    
                    foreach (array_reverse($referrals) as $r): 
                        $status_class = strtolower($r['status']);
                        if ($status_class === 'dikirim') $status_class = 'pending';
                        elseif ($status_class === 'selesai') $status_class = 'success';
                        elseif ($status_class === 'dibatalkan') $status_class = 'danger';
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--accent-primary);"><?php echo htmlspecialchars($r['id']); ?></td>
                        <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($r['patient_name']); ?></td>
                        <td style="font-size: 13px; font-weight: 500; color: #475569;"><?php echo htmlspecialchars($r['hospital']); ?></td>
                        <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <a href="export_pdf.php?type=rujukan&id=<?php echo urlencode($r['id']); ?>" target="_blank" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; text-decoration: none; display: inline-flex; align-items: center;">Cetak PDF</a>
                                <?php if ($r['status'] === 'DIKIRIM'): ?>
                                    <a href="dashboard.php?page=rujukan&action=cancel&id=<?php echo $r['id']; ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;" onclick="return confirm('Apakah Anda yakin ingin membatalkan rujukan ini?')">Batal</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
 