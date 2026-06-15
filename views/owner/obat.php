<?php



$success_msg = '';

            


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'new_drug') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $max_stock = isset($_POST['max_stock']) ? (int)$_POST['max_stock'] : 1000;

    if (!empty($name) && $price > 0 && $stock > 0) {
        $status = 'Aman';
        if ($stock <= $max_stock * 0.1) $status = 'Kritis';
        elseif ($stock <= $max_stock * 0.3) $status = 'Rendah';
        $max_id = 0;
        foreach ($_SESSION['medicines'] as $m) {
            if ($m['id'] > $max_id) {
                $max_id = $m['id'];
            }
        }
        $new_id = $max_id + 1;
        $_SESSION['medicines'][] = [
            'id' => $new_id,
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'stock' => $stock,
            'max_stock' => $max_stock,
            'status' => $status
        ];
        
        
        array_unshift($_SESSION['stock_logs'], [
            'time' => date('Y-m-d H:i') . ' WIB',
            'name' => $name,
            'type' => 'MASUK STOK',
            'desc' => "Registrasi Inventaris Awal",
            'amount' => '+' . $stock,
            'user' => $_SESSION['user_name'] ?? 'Dr. Raka Aji'
        ]);
        
        db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'OWNER', 'STOCK', "Tambah Inventaris Obat Baru #$new_id - $name");
        $success_msg = "Obat baru <strong>" . htmlspecialchars($name) . "</strong> berhasil didaftarkan ke inventaris!";
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'delete_drug' && isset($_GET['id'])) {
    $med_id = (int)$_GET['id'];
    foreach ($_SESSION['medicines'] as $key => $m) {
        if ($m['id'] === $med_id) {
            $deleted_name = $m['name'];
            unset($_SESSION['medicines'][$key]);
            $_SESSION['medicines'] = array_values($_SESSION['medicines']);
            
            db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'OWNER', 'STOCK', "Hapus Obat dari Inventaris: $deleted_name");
            $success_msg = "Obat <strong>" . htmlspecialchars($deleted_name) . "</strong> berhasil dihapus dari inventaris.";
            break;
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'edit_drug' && isset($_GET['id'])) {
    $med_id = (int)$_GET['id'];
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $max_stock = isset($_POST['max_stock']) ? (int)$_POST['max_stock'] : 1000;
    
    if (!empty($name) && $price > 0 && $stock >= 0) {
        foreach ($_SESSION['medicines'] as &$m) {
            if ($m['id'] === $med_id) {
                $m['name'] = $name;
                $m['category'] = $category;
                $m['price'] = $price;
                $m['stock'] = $stock;
                $m['max_stock'] = $max_stock;
                
                $status = 'Aman';
                if ($stock <= $max_stock * 0.1) $status = 'Kritis';
                elseif ($stock <= $max_stock * 0.3) $status = 'Rendah';
                $m['status'] = $status;
                
                db_add_audit($_SESSION['user_name'] ?? 'Dr. Raka Aji', 'OWNER', 'STOCK', "Edit Detail Obat #$med_id - $name");
                $success_msg = "Detail obat <strong>" . htmlspecialchars($name) . "</strong> berhasil diperbarui.";
                break;
            }
        }
        unset($m);
    }
}

$filtered_meds = $_SESSION['medicines'];


$total_items = count($_SESSION['medicines']);
$low_stock_alerts = 0;
$total_price = 0;
$total_usage = 0;

foreach ($_SESSION['medicines'] as $m) {
    if ($m['status'] === 'Kritis' || $m['status'] === 'Rendah') {
        $low_stock_alerts++;
    }
    $total_price += $m['price'];
    $total_usage += max(0, $m['max_stock'] - $m['stock']);
}

$average_price = ($total_items > 0) ? ($total_price / $total_items) : 0;
?>

<div class="content-header">
    <div>
        <h1>Monitoring Obat</h1>
        <p class="page-title-desc">Pantau ketersediaan dan tren penggunaan inventaris medis secara real-time.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="export_pdf.php?type=obat" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export PDF
        </a>
        <button class="btn btn-primary" onclick="openAddDrugModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Stok / Obat
        </button>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div style="background-color: var(--status-safe-bg); border: 1px solid var(--status-safe); color: #065f46; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px;">
        <?php echo $success_msg; ?>
    </div>
<?php endif; ?>


<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">Total Item Stok</div>
        <div class="stat-value"><?php echo $total_items; ?> SKU</div>
        <div class="stat-footer">
            <span>Bulan ini</span>
        </div>
    </div>
    <div class="stat-card critical">
        <div class="stat-header">Alert Stok Rendah</div>
        <div class="stat-value"><?php echo $low_stock_alerts; ?> Item</div>
        <div class="stat-footer">
            <span style="color: var(--status-critical); font-weight: 600;">Segera lakukan re-order vendor</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-header">Rata-rata Harga Obat</div>
        <div class="stat-value">Rp <?php echo number_format($average_price, 0, ',', '.'); ?></div>
        <div class="stat-footer">
            <span>Berdasarkan inventaris</span>
        </div>
    </div>
    <div class="stat-card pending">
        <div class="stat-header">Total Penggunaan</div>
        <div class="stat-value"><?php echo number_format($total_usage, 0, ',', '.'); ?> Unit</div>
        <div class="stat-footer">
            <span>Akumulasi dari sisa stok</span>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Inventaris Obat</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Obat</th>
                    <th>Kategori</th>
                    <th>Harga Satuan</th>
                    <th>Sisa Stok</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filtered_meds) > 0): ?>
                    <?php foreach ($filtered_meds as $m): 
                        $status_class = 'success';
                        if ($m['status'] === 'Kritis') $status_class = 'danger';
                        elseif ($m['status'] === 'Rendah') $status_class = 'warning';
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['category']); ?></td>
                        <td style="font-weight: 600;">Rp <?php echo number_format($m['price'], 0, ',', '.'); ?></td>
                        <td style="font-weight: 600;"><?php echo $m['stock']; ?> <span style="font-weight: normal; color: var(--text-muted); font-size: 12px;">/ <?php echo $m['max_stock']; ?></span></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($m['status']); ?></span></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="openEditDrugModal(<?php echo htmlspecialchars(json_encode($m)); ?>)">Edit</button>
                                <a href="dashboard.php?page=obat&action=delete_drug&id=<?php echo $m['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus obat ini?')">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Obat tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal-backdrop" id="addDrugModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Registrasi Obat Baru</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeAddDrugModal()">&times;</button>
        </div>
        <form method="POST" action="dashboard.php?page=obat&action=new_drug">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="med_new_name">Nama Obat & Sediaan</label>
                    <input type="text" name="name" id="med_new_name" class="form-control" placeholder="Contoh: Metformin 500mg" required>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="med_new_cat">Kategori Obat</label>
                        <select name="category" id="med_new_cat" class="form-control">
                            <option value="Antibiotik">Antibiotik</option>
                            <option value="Analgesik">Analgesik</option>
                            <option value="Suplemen">Suplemen</option>
                            <option value="Kolesterol">Kolesterol</option>
                            <option value="Antihistamin">Antihistamin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="med_new_price">Harga Jual Satuan (Rp)</label>
                        <input type="number" name="price" id="med_new_price" class="form-control" placeholder="Contoh: 15000" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="med_new_stock">Stok Awal</label>
                        <input type="number" name="stock" id="med_new_stock" class="form-control" placeholder="Contoh: 100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="med_new_max">Kapasitas Maksimal</label>
                        <input type="number" name="max_stock" id="med_new_max" class="form-control" placeholder="Contoh: 1000" value="1000">
                    </div>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeAddDrugModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Obat</button>
            </div>
        </form>
    </div>
</div>


<div class="modal-backdrop" id="editDrugModal" style="display: none;">
    <div class="modal-content">
        <div class="card-header">
            <h3 class="card-title">Edit Detail Obat</h3>
            <button class="btn" style="background: transparent; font-size: 20px; padding: 0;" onclick="closeEditDrugModal()">&times;</button>
        </div>
        <form method="POST" id="editDrugForm" action="dashboard.php?page=obat&action=edit_drug">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="med_edit_name">Nama Obat & Sediaan</label>
                    <input type="text" name="name" id="med_edit_name" class="form-control" required>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="med_edit_cat">Kategori Obat</label>
                        <select name="category" id="med_edit_cat" class="form-control">
                            <option value="Antibiotik">Antibiotik</option>
                            <option value="Analgesik">Analgesik</option>
                            <option value="Suplemen">Suplemen</option>
                            <option value="Kolesterol">Kolesterol</option>
                            <option value="Antihistamin">Antihistamin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="med_edit_price">Harga Jual Satuan (Rp)</label>
                        <input type="number" name="price" id="med_edit_price" class="form-control" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="med_edit_stock">Stok Saat Ini</label>
                        <input type="number" name="stock" id="med_edit_stock" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="med_edit_max">Kapasitas Maksimal</label>
                        <input type="number" name="max_stock" id="med_edit_max" class="form-control" required>
                    </div>
                </div>
            </div>
            
            <div class="card-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background-color: #f8fafc;">
                <button type="button" class="btn btn-secondary" onclick="closeEditDrugModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddDrugModal() {
        document.getElementById('addDrugModal').style.display = 'flex';
    }
    function closeAddDrugModal() {
        document.getElementById('addDrugModal').style.display = 'none';
    }
    function openEditDrugModal(drug) {
        document.getElementById('editDrugForm').action = 'dashboard.php?page=obat&action=edit_drug&id=' + drug.id;
        document.getElementById('med_edit_name').value = drug.name;
        document.getElementById('med_edit_cat').value = drug.category;
        document.getElementById('med_edit_price').value = drug.price;
        document.getElementById('med_edit_stock').value = drug.stock;
        document.getElementById('med_edit_max').value = drug.max_stock;
        document.getElementById('editDrugModal').style.display = 'flex';
    }
    function closeEditDrugModal() {
        document.getElementById('editDrugModal').style.display = 'none';
    }
</script>
