<?php
require_once 'includes/db.php';


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    
    header("Location: index.php");
    exit;
}



$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';


$view_file = '';
if ($page === 'edit_profile') {
    $view_file = 'views/edit_profile.php';
} elseif ($role === 'admin') {
    switch ($page) {
        case 'dashboard':
            $view_file = 'views/admin/dashboard.php';
            break;
        case 'pasien':
            $view_file = 'views/admin/pasien.php';
            break;
        case 'pemeriksaan':
            $view_file = 'views/admin/pemeriksaan.php';
            break;
        case 'antrean':
            $view_file = 'views/admin/antrean.php';
            break;
        case 'rujukan':
            $view_file = 'views/admin/rujukan.php';
            break;
        default:
            $view_file = 'views/admin/dashboard.php';
            break;
    }
} elseif ($role === 'dokter') {
    switch ($page) {
        case 'dashboard':
            $view_file = 'views/dokter/dashboard.php';
            break;
        case 'antrean':
            $view_file = 'views/dokter/antrean.php';
            break;
        case 'pemeriksaan_pasien':
            $view_file = 'views/dokter/pemeriksaan.php';
            break;
        case 'resep':
            $view_file = 'views/dokter/resep.php';
            break;
        case 'pembayaran':
            $view_file = 'views/dokter/pembayaran.php';
            break;
        default:
            $view_file = 'views/dokter/dashboard.php';
            break;
    }
} elseif ($role === 'owner') {
    switch ($page) {
        case 'dashboard':
            $view_file = 'views/owner/dashboard.php';
            break;
        case 'keuangan':
            $view_file = 'views/owner/keuangan.php';
            break;
        case 'obat':
            $view_file = 'views/owner/obat.php';
            break;
        case 'log_stok':
            $view_file = 'views/owner/log_stok.php';
            break;
        case 'backup':
            $view_file = 'views/owner/backup.php';
            break;
        case 'users':
            $view_file = 'views/owner/users.php';
            break;
        default:
            $view_file = 'views/owner/dashboard.php';
            break;
    }
}


include 'includes/header.php';
?>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-area">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="content-body">
            <?php
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo "<div class='card'><div class='card-body'><h2>View $view_file tidak ditemukan.</h2></div></div>";
            }
            ?>
        <?php include 'includes/footer.php'; ?>
<?php

?>
