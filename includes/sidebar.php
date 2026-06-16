<?php
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';


$profile_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin Utama';
$profile_role = 'Administrator';

if ($role === 'dokter') {
    $profile_role = 'General Practitioner';
} elseif ($role === 'owner') {
    $profile_role = 'Clinic Owner';
}


$words = explode(' ', $profile_name);
$avatar_initials = '';
foreach ($words as $w) {
    if (!empty($w)) {
        $avatar_initials .= $w[0];
    }
}
$avatar_initials = strtoupper(substr($avatar_initials, 0, 2));
if (empty($avatar_initials)) {
    $avatar_initials = 'US';
}
?>
<div class="sidebar" id="appSidebar">
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.getElementById('appSidebar').classList.add('collapsed');
        }
    </script>
    <div class="sidebar-header">
        <div class="sidebar-logo-icon">M</div>
        <div class="sidebar-logo-text">Medicare Pro</div>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-menu-title">Main Menu</div>
        
        <?php if ($role === 'admin'): ?>
            <a href="dashboard.php?page=dashboard" class="sidebar-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                Dashboard
            </a>
            <a href="dashboard.php?page=pasien" class="sidebar-item <?php echo $page === 'pasien' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Data Pasien
            </a>
            <a href="dashboard.php?page=pemeriksaan" class="sidebar-item <?php echo $page === 'pemeriksaan' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Pemeriksaan Awal
            </a>
            <a href="dashboard.php?page=antrean" class="sidebar-item <?php echo $page === 'antrean' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Manajemen Antrean
            </a>
            <a href="dashboard.php?page=rujukan" class="sidebar-item <?php echo $page === 'rujukan' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                Rujukan
            </a>
            
        <?php elseif ($role === 'dokter'): ?>
            <a href="dashboard.php?page=dashboard" class="sidebar-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                Dashboard
            </a>
            <a href="dashboard.php?page=antrean" class="sidebar-item <?php echo $page === 'antrean' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Antrean Pasien
            </a>
            <a href="dashboard.php?page=pemeriksaan_pasien" class="sidebar-item <?php echo $page === 'pemeriksaan_pasien' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Pemeriksaan Pasien
            </a>
            <a href="dashboard.php?page=resep" class="sidebar-item <?php echo $page === 'resep' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Resep Obat
            </a>
            <a href="dashboard.php?page=pembayaran" class="sidebar-item <?php echo $page === 'pembayaran' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Pembayaran
            </a>
            
        <?php elseif ($role === 'owner'): ?>
            <a href="dashboard.php?page=dashboard" class="sidebar-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                Dashboard
            </a>
            <a href="dashboard.php?page=keuangan" class="sidebar-item <?php echo $page === 'keuangan' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="12" x2="12" y2="12"/><path d="M20.9 15A9 9 0 1 0 9 20.9M20.9 15a8.9 8.9 0 0 0-5.9-5.9M20.9 15h-5.9v-5.9"/></svg>
                Laporan Keuangan
            </a>
            <a href="dashboard.php?page=obat" class="sidebar-item <?php echo $page === 'obat' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Monitoring Obat
            </a>
            <a href="dashboard.php?page=log_stok" class="sidebar-item <?php echo $page === 'log_stok' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Log Stok Obat
            </a>
            <a href="dashboard.php?page=backup" class="sidebar-item <?php echo $page === 'backup' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="8" y2="18"/><line x1="12" y1="18" x2="16" y2="18"/></svg>
                Laporan Bulanan
            </a>
            <a href="dashboard.php?page=users" class="sidebar-item <?php echo $page === 'users' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Kelola User
            </a>
        <?php endif; ?>
    </div>
    
    <div class="sidebar-footer">
        <a href="dashboard.php?page=edit_profile" class="user-profile">
            <div class="user-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                <?php 
                $profile_photo = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;
                if ($profile_photo && file_exists($profile_photo)): 
                ?>
                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo $avatar_initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($profile_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($profile_role); ?></div>
            </div>
        </a>
        <a href="index.php?logout=true" class="logout-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar Akun
        </a>
    </div>
</div>
