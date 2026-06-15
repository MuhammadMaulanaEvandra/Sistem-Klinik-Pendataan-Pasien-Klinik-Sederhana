<?php



$admin_name = $_SESSION['user_name'] ?? 'Admin Utama';
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {
    $patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
    $td = isset($_POST['td']) ? trim($_POST['td']) : '';
    $gula = isset($_POST['gula']) ? trim($_POST['gula']) : '';
    $asam = isset($_POST['asam']) ? trim($_POST['asam']) : '';
    $temp = isset($_POST['temp']) ? trim($_POST['temp']) : '';
    $weight = isset($_POST['weight']) ? trim($_POST['weight']) : '';
    $keluhan = isset($_POST['keluhan']) ? trim($_POST['keluhan']) : '';

    if (!empty($patient_id) && !empty($td) && !empty($temp) && !empty($weight) && !empty($keluhan)) {
        if (db_add_vitals($patient_id, $td, '80', $temp, $weight, $gula, $asam, $keluhan)) {
            
            $patient_name = 'Pasien';
            foreach ($_SESSION['patients'] as $p) {
                if ($p['id'] === $patient_id) {
                    $patient_name = $p['name'];
                    break;
                }
            }
            
            
            $is_already_queued = false;
            foreach ($_SESSION['queues'] as $q) {
                if ($q['patient_id'] === $patient_id && ($q['status'] === 'MENUNGGU' || $q['status'] === 'DIPERIKSA')) {
                    $is_already_queued = true;
                    break;
                }
            }
            
            if (!$is_already_queued) {
                $queue_no = db_generate_queue_no('Poli Umum');
                $_SESSION['queues'][] = [
                    'no' => $queue_no,
                    'patient_id' => $patient_id,
                    'time' => date('H:i'),
                    'poly' => 'Poli Umum',
                    'status' => 'MENUNGGU'
                ];
                db_add_audit($admin_name, 'ADMIN', 'QUEUE', "Antrean Baru $queue_no untuk pasien $patient_id");
            }
            
            $success_msg = "Data pemeriksaan vital untuk <strong>" . htmlspecialchars($patient_name) . "</strong> berhasil disimpan dan pasien dimasukkan ke antrean!";
        } else {
            $error_msg = "Gagal memperbarui data vital pasien. ID tidak valid.";
        }
    } else {
        $error_msg = "Mohon lengkapi seluruh field vital yang wajib diisi (Tekanan Darah, Suhu, Berat, dan Keluhan)!";
    }
}


$patients = db_get_patients();


$selected_patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';
?>

<div class="content-header">
    <div>
        <h1>Pemeriksaan Awal</h1>
        <p class="page-title-desc">Lakukan pengecekan tanda-tanda vital pasien sebelum konsultasi dokter.</p>
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
            <h3 class="card-title">Form Pemeriksaan Baru</h3>
        </div>
        <form method="POST" action="dashboard.php?page=pemeriksaan&action=save">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="patient_id">Pilih Pasien</label>
                    <select name="patient_id" id="patient_id" class="form-control" required>
                        <option value="">-- Pilih Nama Pasien --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] === $selected_patient_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Pemeriksaan</label>
                    <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" disabled style="background-color: #f1f5f9; color: var(--text-muted);">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="td">Tekanan Darah (mmHg)</label>
                        <input type="text" name="td" id="td" class="form-control" placeholder="Contoh: 120/80" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gula">Gula Darah (mg/dL)</label>
                        <input type="number" name="gula" id="gula" class="form-control" placeholder="Contoh: 100">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="asam">Asam Urat (mg/dL)</label>
                        <input type="text" name="asam" id="asam" class="form-control" placeholder="Contoh: 5.4">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="temp">Suhu Tubuh (°C)</label>
                        <input type="text" name="temp" id="temp" class="form-control" placeholder="Contoh: 36.5" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="weight">Berat Badan (kg)</label>
                    <input type="number" name="weight" id="weight" class="form-control" placeholder="Contoh: 65" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="keluhan">Keluhan Pasien</label>
                    <textarea name="keluhan" id="keluhan" class="form-control" placeholder="Tuliskan keluhan utama pasien di sini..." required></textarea>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 20px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="reset" class="btn btn-secondary">Reset</button>
                <button type="submit" class="btn btn-primary">Simpan Data Pemeriksaan</button>
            </div>
        </form>
    </div>

    
    <div>
        
        <div class="card" style="background-color: var(--accent-primary); color: white; border: none; margin-bottom: 24px;">
            <div class="card-body" style="padding: 28px;">
                <h4 style="color: white; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; margin-bottom: 12px;">Total Pemeriksaan Hari Ini</h4>
                <?php 
                $real_exam_count = 0;
                foreach ($patients as $p) {
                    if ($p['vitals'] !== null) {
                        $real_exam_count++;
                    }
                }
                ?>
                <div style="font-family: 'Outfit', sans-serif; font-size: 36px; font-weight: 800; line-height: 1;"><?php echo $real_exam_count; ?> Pasien</div>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Pemeriksaan Hari Ini</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="display: flex; flex-direction: column;">
                    <?php 
                    $exam_count = 0;
                    foreach ($patients as $p): 
                        if ($p['vitals'] !== null):
                            $exam_count++;
                    ?>
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600; font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                    Tensi: <?php echo htmlspecialchars($p['vitals']['td']); ?> mmHg &bull; Gula: <?php echo htmlspecialchars($p['vitals']['gula']); ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge success" style="font-size: 9px; padding: 2px 6px;">Selesai</span>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if ($exam_count === 0):
                    ?>
                        <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">
                            Belum ada pemeriksaan awal hari ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
