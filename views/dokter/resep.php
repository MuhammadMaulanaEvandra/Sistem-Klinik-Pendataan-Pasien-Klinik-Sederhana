<?php


$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : 'PX-2023-0891';


$patient = null;
foreach ($_SESSION['patients'] as $p) {
    if ($p['id'] === $patient_id) {
        $patient = $p;
        break;
    }
}
if (!$patient) {
    $patient = $_SESSION['patients'][0];
    $patient_id = $patient['id'];
}


if (!isset($_SESSION['active_prescription']) || (isset($_SESSION['active_prescription']) && count($_SESSION['active_prescription']) === 3 && isset($_SESSION['active_prescription'][0]['name']) && $_SESSION['active_prescription'][0]['name'] === 'Paracetamol 500mg')) {
    $_SESSION['active_prescription'] = [];
}

$success_msg = '';
$error_msg = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_item') {
    $med_name = isset($_POST['medicine_name']) ? $_POST['medicine_name'] : '';
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;
    $rules = isset($_POST['rules']) ? trim($_POST['rules']) : '';

    if (!empty($med_name) && $qty > 0 && !empty($rules)) {
        $_SESSION['active_prescription'][] = [
            'name' => $med_name,
            'qty' => $qty,
            'rules' => $rules
        ];
        $success_msg = "Obat <strong>$med_name</strong> berhasil ditambahkan ke daftar resep!";
    } else {
        $error_msg = "Mohon lengkapi semua kolom form obat!";
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'delete_item' && isset($_GET['item_index'])) {
    $idx = (int)$_GET['item_index'];
    if (isset($_SESSION['active_prescription'][$idx])) {
        $removed_name = $_SESSION['active_prescription'][$idx]['name'];
        unset($_SESSION['active_prescription'][$idx]);
        $_SESSION['active_prescription'] = array_values($_SESSION['active_prescription']);
        $success_msg = "Obat <strong>$removed_name</strong> dihapus dari resep.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'submit_prescription') {
    if (!empty($_SESSION['active_prescription'])) {
        $notes = isset($_POST['pharmacy_notes']) ? trim($_POST['pharmacy_notes']) : 'Resep standar';
        $prescription_total_cost = 0;
        
        
        foreach ($_SESSION['active_prescription'] as $item) {
            $cost_per_item = 10000; 
            foreach ($_SESSION['medicines'] as &$m) {
                if (stripos($m['name'], $item['name']) !== false || stripos($item['name'], $m['name']) !== false) {
                    $m['stock'] = max(0, $m['stock'] - $item['qty']);
                    if ($m['stock'] <= $m['max_stock'] * 0.1) {
                        $m['status'] = 'Kritis';
                    } elseif ($m['stock'] <= $m['max_stock'] * 0.3) {
                        $m['status'] = 'Rendah';
                    } else {
                        $m['status'] = 'Aman';
                    }
                    $cost_per_item = $m['price'];
                    break;
                }
            }
            unset($m);
            
            $prescription_total_cost += ($cost_per_item * $item['qty']);

            
            array_unshift($_SESSION['stock_logs'], [
                'time' => date('Y-m-d H:i') . ' WIB',
                'name' => $item['name'],
                'type' => 'KELUAR STOK',
                'desc' => "Resep " . ($_SESSION['user_name'] ?? 'Dr. Raka Aji') . " (PX: $patient_id)",
                'amount' => '-' . $item['qty'],
                'user' => $_SESSION['user_name'] ?? 'Dr. Raka Aji'
            ]);
        }

        
        $trx_id = db_generate_payment_id();
        $_SESSION['payments'][] = [
            'id' => $trx_id,
            'patient_id' => $patient_id,
            'date' => date('Y-m-d H:i'),
            'service' => 'Apotek (Resep Obat)',
            'method' => 'CASH',
            'amount' => $prescription_total_cost,
            'status' => 'Pending'
        ];

            
            $prescription_id = $trx_id;
            
            $_SESSION['prescriptions'][$prescription_id] = [
                'items'      => $_SESSION['active_prescription'],
                'notes'      => $notes,
                'patient_id' => $patient_id,
                'doctor'     => $_SESSION['user_name'] ?? 'Dr. Raka Aji',
                'date'       => date('Y-m-d H:i'),
                'payment_id' => $trx_id,
            ];
        
        $_SESSION['active_prescription'] = [];
        db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'DOKTER', 'PRESCRIPTION', "Membuat Resep E-Prescription untuk $patient_id total tagihan Rp $prescription_total_cost");
        $success_msg = "Resep berhasil dikirim ke Apotek & Farmasi! Invoice tagihan sebesar <strong>Rp " . number_format($prescription_total_cost, 0, ',', '.') . "</strong> telah dibuat. Silakan lakukan pembayaran di halaman Pembayaran.";
    } else {
        $error_msg = "Daftar resep kosong! Masukkan minimal satu obat sebelum mengirim.";
    }
}


$medicines = $_SESSION['medicines'];
?>

<div class="content-header">
    <div>
        <h1>Resep Obat (E-Prescription)</h1>
        <p class="page-title-desc">Kelola dan input resep obat pasien secara digital untuk farmasi.</p>
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

<div style="background-color: #f1f5f9; padding: 12px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div style="font-size: 14px; font-weight: 500;">
        Resep untuk: <strong style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars($patient['name']); ?></strong> (RM: <?php echo htmlspecialchars($patient['id']); ?>)
    </div>
    <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
        <?php echo date('d M Y'); ?>
    </div>
</div>

<div class="grid-2col">
    
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">Tambah Obat Baru</h3>
            </div>
            <form method="POST" action="dashboard.php?page=resep&patient_id=<?php echo urlencode($patient_id); ?>&action=add_item">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="med_name">Cari / Pilih Nama Obat</label>
                        <select name="medicine_name" id="med_name" class="form-control" required>
                            <option value="">-- Pilih Obat --</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['name']); ?>">
                                    <?php echo htmlspecialchars($m['name']); ?> (Sisa: <?php echo $m['stock']; ?> - Rp <?php echo number_format($m['price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="med_qty">Jumlah</label>
                            <input type="number" name="qty" id="med_qty" class="form-control" placeholder="Contoh: 10" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="med_rules">Aturan Pakai</label>
                            <input type="text" name="rules" id="med_rules" class="form-control" placeholder="Contoh: 3 x 1 Sesudah Makan" required>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background-color: #f8fafc;">
                    <button type="submit" class="btn btn-primary">Add to List</button>
                </div>
            </form>
        </div>

        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Stok Apotek Terbatas</h3>
                <button class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;" onclick="alert('Membuka modul monitoring obat...')">Lihat Semua</button>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="display: flex; flex-direction: column;">
                    <?php 
                    $warn_count = 0;
                    foreach ($medicines as $m): 
                        if ($m['status'] === 'Kritis' || $m['status'] === 'Rendah'):
                            $warn_count++;
                            $badge_class = ($m['status'] === 'Kritis') ? 'danger' : 'warning';
                    ?>
                        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600; font-size: 13px; color: #0f172a;"><?php echo htmlspecialchars($m['name']); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Tersisa <?php echo $m['stock']; ?> unit</div>
                            </div>
                            <div>
                                <span class="badge <?php echo $badge_class; ?>" style="font-size: 9px; padding: 2px 6px;"><?php echo $m['status']; ?></span>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if ($warn_count === 0):
                    ?>
                        <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">
                            Semua stok obat dalam kondisi aman.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    
    <div>
        <div class="card" style="border: 1px solid var(--accent-primary);">
            <div class="card-header" style="background-color: var(--accent-primary); color: white;">
                <h3 class="card-title" style="color: white;">Daftar Resep Digital</h3>
                <span style="font-size: 12px; opacity: 0.8; font-weight: 500;"><?php echo count($_SESSION['active_prescription']); ?> Items Added</span>
            </div>
            <form method="POST" action="dashboard.php?page=resep&patient_id=<?php echo urlencode($patient_id); ?>&action=submit_prescription">
                <div class="card-body" style="padding: 0;">
                    <div style="display: flex; flex-direction: column;">
                        <?php if (!empty($_SESSION['active_prescription'])): ?>
                            <?php foreach ($_SESSION['active_prescription'] as $idx => $item): ?>
                                <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                            Aturan Pakai: <strong><?php echo htmlspecialchars($item['rules']); ?></strong>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span style="font-weight: 600; font-size: 13px; color: #475569; background-color: #f1f5f9; padding: 4px 10px; border-radius: 6px;"><?php echo $item['qty']; ?> Unit</span>
                                        <a href="dashboard.php?page=resep&patient_id=<?php echo urlencode($patient_id); ?>&action=delete_item&item_index=<?php echo $idx; ?>" style="color: var(--status-critical); font-size: 18px; font-weight: bold; text-decoration: none;" title="Hapus obat">&times;</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                Belum ada obat yang ditambahkan ke resep.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="padding: 20px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="notes">Catatan Tambahan (Opsional)</label>
                            <textarea name="pharmacy_notes" id="notes" class="form-control" placeholder="Tambahkan catatan instruksi khusus untuk apoteker..." style="min-height: 80px;"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc;">
                    <button type="submit" class="btn btn-primary" style="background-color: var(--status-safe); border-color: var(--status-safe);" <?php echo empty($_SESSION['active_prescription']) ? 'disabled' : ''; ?>>Lanjutkan ke Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
