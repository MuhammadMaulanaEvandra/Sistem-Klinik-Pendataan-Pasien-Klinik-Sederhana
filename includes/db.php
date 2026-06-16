<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    date_default_timezone_set('Asia/Jakarta'); 

}


define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'clinicgg_db');

$conn = null;
$db_error = false;


try {
    
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    
    $conn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE `" . DB_NAME . "`");
    
    
    $tables_exist = true;
    $required_tables = [
        'users', 'doctors', 'polyclinics', 'patients', 'queues', 
        'patient_vitals', 'consultations', 'medicines', 
        'referrals', 'payments', 'stock_logs', 
        'audit_logs', 'backups', 'prescriptions'
    ];
    
    foreach ($required_tables as $table) {
        try {
            $check = $conn->query("SELECT 1 FROM `$table` LIMIT 1");
            if ($check === false) {
                $tables_exist = false;
                break;
            }
        } catch (PDOException $e) {
            $tables_exist = false;
            break;
        }
    }
    
    if (!$tables_exist) {
        db_initialize_tables($conn);
    } else {
        
        try {
            $conn->query("SELECT `photo` FROM `users` LIMIT 1");
        } catch (PDOException $e) {
            try {
                $conn->exec("ALTER TABLE `users` ADD COLUMN `photo` VARCHAR(255) NULL DEFAULT NULL AFTER `status`");
            } catch (PDOException $ex) {
                
            }
        }
    }
    
    
    db_load_from_mysql($conn);
    
    
    register_shutdown_function('db_sync_to_mysql_shutdown');
    
} catch (PDOException $e) {
    
    $db_error = $e->getMessage();
    
    
    if (!isset($_SESSION['seeded'])) {
        db_seed_session_fallback();
    }
}


function db_initialize_tables($pdo) {
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $pdo->exec("DROP TABLE IF EXISTS `prescriptions` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `backups` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `audit_logs` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `stock_logs` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `payments` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `referrals` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `medicines` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `consultations` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `patient_vitals` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `queues` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `patients` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `polyclinics` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `doctors` CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS `users` CASCADE");

    
    $pdo->exec("CREATE TABLE `users` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `username` varchar(50) UNIQUE,
      `password` varchar(255),
      `name` varchar(100),
      `role` varchar(20),
      `status` varchar(20),
      `photo` varchar(255) NULL,
      `created_at` datetime
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `doctors` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `user_id` int,
      `specialization` varchar(100),
      `sip_number` varchar(100),
      `phone` varchar(20),
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `polyclinics` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `name` varchar(100),
      `description` text,
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `patients` (
      `id` varchar(50) PRIMARY KEY,
      `name` varchar(100),
      `gender` varchar(20),
      `birth_date` date,
      `age` int,
      `address` text,
      `phone` varchar(20),
      `reg_date` date,
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `queues` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `queue_number` varchar(20),
      `patient_id` varchar(50),
      `polyclinic_id` int,
      `doctor_id` int,
      `queue_date` date,
      `queue_time` time,
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `patient_vitals` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `patient_id` varchar(50),
      `queue_id` int,
      `blood_pressure` varchar(20),
      `heart_rate` int,
      `temperature` decimal(4,1),
      `weight` decimal(5,2),
      `blood_sugar` int,
      `uric_acid` decimal(4,1),
      `complaint` text,
      `created_at` datetime
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `consultations` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `patient_id` varchar(50),
      `doctor_id` int,
      `queue_id` int,
      `subjective` text,
      `objective` text,
      `assessment` text,
      `plan` text,
      `diagnosis_code` varchar(20),
      `diagnosis_name` varchar(255),
      `consultation_date` datetime,
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `medicines` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `name` varchar(100),
      `category` varchar(50),
      `price` int,
      `stock` int,
      `max_stock` int,
      `status` varchar(20)
    ) ENGINE=InnoDB");


    
    $pdo->exec("CREATE TABLE `referrals` (
      `id` varchar(50) PRIMARY KEY,
      `consultation_id` int,
      `patient_id` varchar(50),
      `hospital` varchar(100),
      `diagnosis` text,
      `reason` text,
      `referral_date` date,
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `payments` (
      `id` varchar(50) PRIMARY KEY,
      `consultation_id` int,
      `patient_id` varchar(50),
      `service` varchar(100),
      `payment_method` varchar(50),
      `amount` bigint,
      `payment_date` datetime,
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `stock_logs` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `medicine_id` int,
      `user_id` int,
      `type` varchar(20),
      `description` text,
      `quantity` int,
      `log_time` datetime
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `audit_logs` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `user_id` int,
      `category` varchar(50),
      `action` text,
      `ip_address` varchar(50),
      `created_at` datetime
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `backups` (
      `id` int PRIMARY KEY AUTO_INCREMENT,
      `backup_time` datetime,
      `file_name` varchar(100),
      `file_size` varchar(20),
      `status` varchar(20)
    ) ENGINE=InnoDB");

    
    $pdo->exec("CREATE TABLE `prescriptions` (
      `id` varchar(50) PRIMARY KEY,
      `payment_id` varchar(50),
      `patient_id` varchar(50),
      `doctor_name` varchar(100),
      `items` longtext,
      `notes` text,
      `created_at` datetime
    ) ENGINE=InnoDB");


    
    $pdo->exec("ALTER TABLE `doctors` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `queues` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `queues` ADD FOREIGN KEY (`polyclinic_id`) REFERENCES `polyclinics` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `queues` ADD FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `patient_vitals` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `patient_vitals` ADD FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE `consultations` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `consultations` ADD FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `consultations` ADD FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE `referrals` ADD FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `referrals` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `payments` ADD FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE `payments` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `stock_logs` ADD FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `stock_logs` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE `audit_logs` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");

    
    $pdo->exec("INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`, `status`, `created_at`) VALUES
    (1, 'admin@klinik.com', '\$2y\$10\$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'Admin Utama', 'admin', 'Active', NOW()),
    (2, 'raka@klinik.com', '\$2y\$10\$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'Dr. Raka Aji', 'dokter', 'Active', NOW()),
    (3, 'rani@klinik.com', '\$2y\$10\$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'Dr. Rani dwi hapsari', 'dokter', 'Active', NOW()),
    (4, 'owner@klinik.com', '\$2y\$10\$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'Dr. Raka Aji', 'owner', 'Active', NOW())");

    
    $pdo->exec("INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `sip_number`, `phone`, `status`) VALUES
    (1, 2, 'General Practitioner', 'SIP-12345-2026', '08123456789', 'Active'),
    (2, 3, 'General Practitioner', 'SIP-67890-2026', '08987654321', 'Active')");

    
    $pdo->exec("INSERT INTO `polyclinics` (`id`, `name`, `description`, `status`) VALUES
    (1, 'Poli Umum', 'Poliklinik Umum', 'Active')");

    
    $pdo->exec("INSERT INTO `medicines` (`id`, `name`, `category`, `price`, `stock`, `max_stock`, `status`) VALUES
    (1, 'Amoxicillin 500mg', 'Antibiotik', 12500, 850, 1000, 'Aman'),
    (2, 'Paracetamol Syrup', 'Analgesik', 24000, 175, 500, 'Rendah'),
    (3, 'Atorvastatin 20mg', 'Kolesterol', 45800, 12, 100, 'Kritis'),
    (4, 'Vitamin C 1000mg', 'Suplemen', 8000, 1900, 2000, 'Aman'),
    (5, 'Cetirizine 10mg', 'Antihistamin', 15000, 320, 500, 'Aman'),
    (6, 'Ibuprofen 400mg', 'Analgesik', 18000, 512, 1000, 'Aman')");

    
    $pdo->exec("INSERT INTO `audit_logs` (`id`, `user_id`, `category`, `action`, `ip_address`, `created_at`) VALUES
    (1, 1, 'SYSTEM', 'Sistem Khas Medicare Pro berhasil dimigrasikan', '127.0.0.1', NOW())");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    db_load_from_mysql($pdo);
}


function db_load_from_mysql($pdo) {
    
    $patients_raw = $pdo->query("SELECT * FROM `patients`")->fetchAll();
    $patients = [];
    foreach ($patients_raw as $p) {
        
        $stmt_vit = $pdo->prepare("SELECT * FROM `patient_vitals` WHERE `patient_id` = ? ORDER BY `id` DESC LIMIT 1");
        $stmt_vit->execute([$p['id']]);
        $vit = $stmt_vit->fetch();
        
        $vitals_data = null;
        if ($vit) {
            $vitals_data = [
                'td' => $vit['blood_pressure'],
                'hr' => (int)$vit['heart_rate'],
                'temp' => (float)$vit['temperature'],
                'weight' => (float)$vit['weight'],
                'gula' => $vit['blood_sugar'] !== null ? (int)$vit['blood_sugar'] : null,
                'asam' => $vit['uric_acid'] !== null ? (float)$vit['uric_acid'] : null,
                'keluhan' => $vit['complaint']
            ];
        }
        
        
        $stmt_cons = $pdo->prepare("SELECT * FROM `consultations` WHERE `patient_id` = ? ORDER BY `id` ASC");
        $stmt_cons->execute([$p['id']]);
        $consults = $stmt_cons->fetchAll();
        
        $consultations = [];
        foreach ($consults as $c) {
            $consultations[] = [
                'date' => $c['consultation_date'],
                'type' => 'Konsultasi Medis',
                'notes' => $c['assessment'] . ': ' . $c['plan'] . ' (Subyektif: ' . $c['subjective'] . ')',
                'subjective' => $c['subjective'],
                'objective' => $c['objective'],
                'assessment' => $c['assessment'],
                'plan' => $c['plan'],
                'diagnosis_code' => $c['diagnosis_code'],
                'diagnosis_name' => $c['diagnosis_name'],
                'status' => $c['status']
            ];
        }
        
        $patients[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'gender' => $p['gender'],
            'birth_date' => $p['birth_date'],
            'age' => (int)$p['age'],
            'address' => $p['address'],
            'phone' => $p['phone'],
            'reg_date' => $p['reg_date'],
            'status' => $p['status'],
            'vitals' => $vitals_data,
            'consultations' => $consultations
        ];
    }
    $_SESSION['patients'] = $patients;
    
    
    $queues_raw = $pdo->query("SELECT q.*, p.name as poly_name FROM `queues` q LEFT JOIN `polyclinics` p ON q.polyclinic_id = p.id ORDER BY q.id ASC")->fetchAll();
    $queues = [];
    foreach ($queues_raw as $q) {
        $queues[] = [
            'no' => $q['queue_number'],
            'patient_id' => $q['patient_id'],
            'time' => substr($q['queue_time'], 0, 5),
            'poly' => $q['poly_name'] ?: 'Poli Umum',
            'status' => $q['status'],
            'polyclinic_id' => $q['polyclinic_id'],
            'doctor_id' => $q['doctor_id']
        ];
    }
    $_SESSION['queues'] = $queues;
    
    
    $payments_raw = $pdo->query("SELECT * FROM `payments` ORDER BY `payment_date` ASC")->fetchAll();
    $payments = [];
    foreach ($payments_raw as $pay) {
        $payments[] = [
            'id' => $pay['id'],
            'patient_id' => $pay['patient_id'],
            'date' => $pay['payment_date'],
            'service' => $pay['service'],
            'method' => $pay['payment_method'],
            'amount' => (int)$pay['amount'],
            'status' => $pay['status']
        ];
    }
    $_SESSION['payments'] = $payments;
    
    
    $referrals_raw = $pdo->query("SELECT r.*, p.name as patient_name FROM `referrals` r LEFT JOIN `patients` p ON r.patient_id = p.id ORDER BY r.referral_date ASC")->fetchAll();
    $referrals = [];
    foreach ($referrals_raw as $ref) {
        $referrals[] = [
            'id' => $ref['id'],
            'patient_name' => $ref['patient_name'] ?: 'Pasien',
            'patient_id' => $ref['patient_id'],
            'hospital' => $ref['hospital'],
            'date' => $ref['referral_date'],
            'status' => $ref['status'],
            'diagnosis' => $ref['diagnosis'],
            'reason' => $ref['reason']
        ];
    }
    $_SESSION['referrals'] = $referrals;
    
    
    $meds = $pdo->query("SELECT * FROM `medicines` ORDER BY `id` ASC")->fetchAll();
    foreach ($meds as &$m) {
        $m['id'] = (int)$m['id'];
        $m['price'] = (int)$m['price'];
        $m['stock'] = (int)$m['stock'];
        $m['max_stock'] = (int)$m['max_stock'];
    }
    unset($m);
    $_SESSION['medicines'] = $meds;
    
    
    $logs = $pdo->query("SELECT sl.*, m.name as medicine_name, u.name as user_name FROM `stock_logs` sl LEFT JOIN `medicines` m ON sl.medicine_id = m.id LEFT JOIN `users` u ON sl.user_id = u.id ORDER BY sl.id DESC")->fetchAll();
    $stock_logs = [];
    foreach ($logs as $l) {
        $stock_logs[] = [
            'time' => $l['log_time'],
            'name' => $l['medicine_name'] ?: 'Obat',
            'type' => $l['type'],
            'desc' => $l['description'],
            'amount' => ($l['quantity'] >= 0 ? '+' : '') . $l['quantity'],
            'user' => $l['user_name'] ?: 'Dr. Raka Aji'
        ];
    }
    $_SESSION['stock_logs'] = $stock_logs;
    
    
    $backups_raw = $pdo->query("SELECT * FROM `backups` ORDER BY `id` DESC")->fetchAll();
    $backups = [];
    foreach ($backups_raw as $b) {
        $backups[] = [
            'time' => $b['backup_time'],
            'name' => $b['file_name'],
            'size' => $b['file_size'],
            'status' => $b['status']
        ];
    }
    $_SESSION['backups'] = $backups;
    
    
    $audits = $pdo->query("SELECT al.*, u.name as user_name, u.role as user_role FROM `audit_logs` al LEFT JOIN `users` u ON al.user_id = u.id ORDER BY al.id DESC")->fetchAll();
    $audit_logs = [];
    foreach ($audits as $aud) {
        $audit_logs[] = [
            'time' => $aud['created_at'],
            'user' => $aud['user_name'] ?: 'System',
            'role' => $aud['user_role'] ?: 'SYSTEM',
            'category' => $aud['category'],
            'action' => $aud['action'],
            'ip' => $aud['ip_address']
        ];
    }
    $_SESSION['audit_logs'] = $audit_logs;
    
    
    $users_raw = $pdo->query("SELECT * FROM `users` ORDER BY `id` ASC")->fetchAll();
    foreach ($users_raw as &$usr) {
        $usr['id'] = (int)$usr['id'];
        
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $usr['id']) {
            $_SESSION['user_name'] = $usr['name'];
            $_SESSION['user_photo'] = $usr['photo'];
        }
    }
    unset($usr);
    $_SESSION['users'] = $users_raw;

    
    $prescriptions_raw = $pdo->query("SELECT * FROM `prescriptions` ORDER BY `created_at` ASC")->fetchAll();
    $prescriptions = [];
    foreach ($prescriptions_raw as $presc) {
        $prescriptions[$presc['id']] = [
            'items'      => json_decode($presc['items'], true) ?: [],
            'notes'      => $presc['notes'],
            'patient_id' => $presc['patient_id'],
            'doctor'     => $presc['doctor_name'],
            'date'       => $presc['created_at'],
            'payment_id' => $presc['payment_id'],
        ];
    }
    $_SESSION['prescriptions'] = $prescriptions;
    
    $_SESSION['seeded'] = true;
}


function db_sync_to_mysql_shutdown() {
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        
        $pdo->exec("DELETE FROM `patient_vitals`");
        $pdo->exec("DELETE FROM `consultations`");
        $pdo->exec("DELETE FROM `patients`");
        
        $ins_pat = $pdo->prepare("INSERT INTO `patients` (`id`, `name`, `gender`, `birth_date`, `age`, `address`, `phone`, `reg_date`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_vit = $pdo->prepare("INSERT INTO `patient_vitals` (`patient_id`, `queue_id`, `blood_pressure`, `heart_rate`, `temperature`, `weight`, `blood_sugar`, `uric_acid`, `complaint`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_cons = $pdo->prepare("INSERT INTO `consultations` (`patient_id`, `doctor_id`, `queue_id`, `subjective`, `objective`, `assessment`, `plan`, `diagnosis_code`, `diagnosis_name`, `consultation_date`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (isset($_SESSION['patients'])) {
            foreach ($_SESSION['patients'] as $p) {
                $birth_date = isset($p['birth_date']) ? $p['birth_date'] : date('Y-m-d', strtotime("-{$p['age']} years"));
                $p_status = isset($p['status']) ? $p['status'] : 'Active';
                
                $ins_pat->execute([
                    $p['id'],
                    $p['name'],
                    $p['gender'],
                    $birth_date,
                    $p['age'],
                    $p['address'],
                    $p['phone'],
                    $p['reg_date'],
                    $p_status
                ]);
                
                if ($p['vitals'] !== null) {
                    $v = $p['vitals'];
                    $ins_vit->execute([
                        $p['id'],
                        null,
                        $v['td'],
                        (int)$v['hr'],
                        (float)$v['temp'],
                        (float)$v['weight'],
                        $v['gula'] !== null ? (int)$v['gula'] : null,
                        $v['asam'] !== null ? (float)$v['asam'] : null,
                        $v['keluhan'],
                        date('Y-m-d H:i:s')
                    ]);
                }
                
                if (!empty($p['consultations'])) {
                    foreach ($p['consultations'] as $c) {
                        $subj = isset($c['subjective']) ? $c['subjective'] : '';
                        $obj = isset($c['objective']) ? $c['objective'] : 'Suhu tubuh normal.';
                        $assess = isset($c['assessment']) ? $c['assessment'] : $c['notes'];
                        $plan = isset($c['plan']) ? $c['plan'] : '';
                        $diag_code = isset($c['diagnosis_code']) ? $c['diagnosis_code'] : 'J06.9';
                        $diag_name = isset($c['diagnosis_name']) ? $c['diagnosis_name'] : $assess;
                        $c_status = isset($c['status']) ? $c['status'] : 'Selesai';
                        
                        
                        $doctor_id = 1;
                        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'dokter') {
                            
                            $stmt_doc = $pdo->prepare("SELECT id FROM `doctors` WHERE user_id = ? LIMIT 1");
                            $stmt_doc->execute([$_SESSION['user_id']]);
                            $doctor_id = $stmt_doc->fetchColumn() ?: 1;
                        }
                        
                        $ins_cons->execute([
                            $p['id'],
                            $doctor_id,
                            null,
                            $subj,
                            $obj,
                            $assess,
                            $plan,
                            $diag_code,
                            $diag_name,
                            $c['date'] . (strlen($c['date']) <= 10 ? ' 12:00:00' : ''),
                            $c_status
                        ]);
                    }
                }
            }
        }
        
        
        $pdo->exec("DELETE FROM `queues`");
        $ins_q = $pdo->prepare("INSERT INTO `queues` (`queue_number`, `patient_id`, `polyclinic_id`, `doctor_id`, `queue_date`, `queue_time`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['queues'])) {
            foreach ($_SESSION['queues'] as $q) {
                
                $poly_id = 1;
                
                
                $doctor_id = isset($q['doctor_id']) ? $q['doctor_id'] : 1;
                
                $ins_q->execute([
                    $q['no'],
                    $q['patient_id'],
                    $poly_id,
                    $doctor_id,
                    date('Y-m-d'),
                    $q['time'] . ':00',
                    $q['status']
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `payments`");
        $ins_pay = $pdo->prepare("INSERT INTO `payments` (`id`, `consultation_id`, `patient_id`, `service`, `payment_method`, `amount`, `payment_date`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['payments'])) {
            foreach ($_SESSION['payments'] as $pay) {
                
                $stmt_cid = $pdo->prepare("SELECT id FROM `consultations` WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
                $stmt_cid->execute([$pay['patient_id']]);
                $cid = $stmt_cid->fetchColumn();
                $consultation_id = $cid ? $cid : null;
                
                $ins_pay->execute([
                    $pay['id'],
                    $consultation_id,
                    $pay['patient_id'],
                    $pay['service'],
                    $pay['method'],
                    $pay['amount'],
                    $pay['date'] . (strlen($pay['date']) <= 16 ? ':00' : ''),
                    $pay['status']
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `referrals`");
        $ins_ref = $pdo->prepare("INSERT INTO `referrals` (`id`, `consultation_id`, `patient_id`, `hospital`, `diagnosis`, `reason`, `referral_date`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['referrals'])) {
            foreach ($_SESSION['referrals'] as $ref) {
                $pat_id = isset($ref['patient_id']) ? $ref['patient_id'] : '';
                if (empty($pat_id)) {
                    
                    $stmt_pid = $pdo->prepare("SELECT id FROM `patients` WHERE name = ? LIMIT 1");
                    $stmt_pid->execute([$ref['patient_name']]);
                    $pat_id = $stmt_pid->fetchColumn();
                }
                if (empty($pat_id) && isset($_SESSION['patients'][0])) {
                    $pat_id = $_SESSION['patients'][0]['id'];
                }
                
                
                $stmt_cid = $pdo->prepare("SELECT id FROM `consultations` WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
                $stmt_cid->execute([$pat_id]);
                $consultation_id = $stmt_cid->fetchColumn() ?: null;
                
                $ins_ref->execute([
                    $ref['id'],
                    $consultation_id,
                    $pat_id,
                    $ref['hospital'],
                    isset($ref['diagnosis']) ? $ref['diagnosis'] : 'Suspek Medis',
                    isset($ref['reason']) ? $ref['reason'] : 'Pemeriksaan lebih lanjut',
                    $ref['date'],
                    $ref['status']
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `medicines`");
        $ins_med = $pdo->prepare("INSERT INTO `medicines` (`id`, `name`, `category`, `price`, `stock`, `max_stock`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['medicines'])) {
            foreach ($_SESSION['medicines'] as $med) {
                $ins_med->execute([
                    $med['id'],
                    $med['name'],
                    $med['category'],
                    $med['price'],
                    $med['stock'],
                    $med['max_stock'],
                    $med['status']
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `stock_logs`");
        $ins_log = $pdo->prepare("INSERT INTO `stock_logs` (`medicine_id`, `user_id`, `type`, `description`, `quantity`, `log_time`) VALUES (?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['stock_logs'])) {
            foreach (array_reverse($_SESSION['stock_logs']) as $log) { 
                
                $stmt_mid = $pdo->prepare("SELECT id FROM `medicines` WHERE name = ? LIMIT 1");
                $stmt_mid->execute([$log['name']]);
                $med_id = $stmt_mid->fetchColumn();
                
                if (!$med_id) continue;
                
                
                $user_id = 1;
                if (stripos($log['user'], 'Raka') !== false) {
                    $user_id = 2;
                } elseif (stripos($log['user'], 'Rani') !== false) {
                    $user_id = 3;
                }
                
                $qty = (int)$log['amount'];
                
                $ins_log->execute([
                    $med_id,
                    $user_id,
                    $log['type'],
                    isset($log['desc']) ? $log['desc'] : '',
                    $qty,
                    date('Y-m-d H:i:s')
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `backups`");
        $ins_bk = $pdo->prepare("INSERT INTO `backups` (`backup_time`, `file_name`, `file_size`, `status`) VALUES (?, ?, ?, ?)");
        if (isset($_SESSION['backups'])) {
            foreach (array_reverse($_SESSION['backups']) as $b) {
                $ins_bk->execute([
                    $b['time'] . (strlen($b['time']) <= 16 ? ':00' : ''),
                    $b['name'],
                    $b['size'],
                    $b['status']
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `audit_logs`");
        $ins_aud = $pdo->prepare("INSERT INTO `audit_logs` (`user_id`, `category`, `action`, `ip_address`, `created_at`) VALUES (?, ?, ?, ?, ?)");
        if (isset($_SESSION['audit_logs'])) {
            foreach (array_reverse($_SESSION['audit_logs']) as $aud) {
                
                $user_id = 1;
                if (stripos($aud['user'], 'Raka') !== false) {
                    $user_id = 2;
                } elseif (stripos($aud['user'], 'Rani') !== false) {
                    $user_id = 3;
                }
                
                $ins_aud->execute([
                    $user_id,
                    $aud['category'],
                    $aud['action'],
                    $aud['ip'],
                    date('Y-m-d H:i:s')
                ]);
            }
        }
        
        
        $pdo->exec("DELETE FROM `users`");
        $ins_user = $pdo->prepare("INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`, `status`, `photo`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['users'])) {
            foreach ($_SESSION['users'] as $u) {
                $ins_user->execute([
                    $u['id'],
                    $u['username'],
                    $u['password'],
                    $u['name'],
                    $u['role'],
                    $u['status'],
                    $u['photo'] ?? null,
                    $u['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }

        
        $pdo->exec("DELETE FROM `prescriptions`");
        $ins_presc = $pdo->prepare("INSERT INTO `prescriptions` (`id`, `payment_id`, `patient_id`, `doctor_name`, `items`, `notes`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (isset($_SESSION['prescriptions'])) {
            foreach ($_SESSION['prescriptions'] as $pid => $presc) {
                $items_json = json_encode($presc['items'] ?? []);
                $created_at = isset($presc['date']) ? $presc['date'] : date('Y-m-d H:i:s');
                
                if (strlen($created_at) <= 16) {
                    $created_at .= ':00';
                }
                $ins_presc->execute([
                    $pid,
                    $presc['payment_id'] ?? $pid,
                    $presc['patient_id'] ?? '',
                    $presc['doctor'] ?? 'Dr. Raka Aji',
                    $items_json,
                    $presc['notes'] ?? '',
                    $created_at
                ]);
            }
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
    } catch (Throwable $e) {
        file_put_contents(dirname(__DIR__) . "/mysql_sync_error.log", date('Y-m-d H:i:s') . " - Shutdown Sync Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}


function db_seed_session_fallback() {
    $_SESSION['patients'] = [];
    $_SESSION['queues'] = [];
    $_SESSION['payments'] = [];
    $_SESSION['referrals'] = [];

    $_SESSION['medicines'] = [
        ['id' => 1, 'name' => 'Amoxicillin 500mg', 'category' => 'Antibiotik', 'price' => 12500, 'stock' => 850, 'max_stock' => 1000, 'status' => 'Aman'],
        ['id' => 2, 'name' => 'Paracetamol Syrup', 'category' => 'Analgesik', 'price' => 24000, 'stock' => 175, 'max_stock' => 500, 'status' => 'Rendah'],
        ['id' => 3, 'name' => 'Atorvastatin 20mg', 'category' => 'Kolesterol', 'price' => 45800, 'stock' => 12, 'max_stock' => 100, 'status' => 'Kritis'],
        ['id' => 4, 'name' => 'Vitamin C 1000mg', 'category' => 'Suplemen', 'price' => 8000, 'stock' => 1900, 'max_stock' => 2000, 'status' => 'Aman'],
        ['id' => 5, 'name' => 'Cetirizine 10mg', 'category' => 'Antihistamin', 'price' => 15000, 'stock' => 320, 'max_stock' => 500, 'status' => 'Aman'],
        ['id' => 6, 'name' => 'Ibuprofen 400mg', 'category' => 'Analgesik', 'price' => 18000, 'stock' => 512, 'max_stock' => 1000, 'status' => 'Aman']
    ];

    $_SESSION['stock_logs'] = [];
    $_SESSION['backups'] = [];
    $_SESSION['audit_logs'] = [];
    
    $_SESSION['users'] = [
        ['id' => 1, 'username' => 'admin@klinik.com', 'password' => '$2y$10$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'name' => 'Admin Utama', 'role' => 'admin', 'status' => 'Active', 'photo' => null, 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'username' => 'raka@klinik.com', 'password' => '$2y$10$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'name' => 'Dr. Raka Aji', 'role' => 'dokter', 'status' => 'Active', 'photo' => null, 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 3, 'username' => 'rani@klinik.com', 'password' => '$2y$10$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'name' => 'Dr. Rani dwi hapsari', 'role' => 'dokter', 'status' => 'Active', 'photo' => null, 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 4, 'username' => 'owner@klinik.com', 'password' => '$2y$10$6mrU9TJ12xhVMdk0sUN02uoKcxkF/NjV6AdxACb7Xcai9wp8lIXca', 'name' => 'Dr. Raka Aji', 'role' => 'owner', 'status' => 'Active', 'photo' => null, 'created_at' => date('Y-m-d H:i:s')]
    ];

    $_SESSION['seeded'] = true;
}


function db_get_patients() {
    return $_SESSION['patients'] ?? [];
}


function db_add_patient($name, $gender, $age, $address, $phone) {
    $year = date('Y');
    $prefix = "PX-$year-";
    
    
    $patients = $_SESSION['patients'] ?? [];
    
    
    $max_num = 0;
    foreach ($patients as $p) {
        if (strpos($p['id'], $prefix) === 0) {
            $num_part = substr($p['id'], strlen($prefix));
            $num = (int)$num_part;
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }
    
    $next_num = $max_num + 1;
    do {
        $id = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        $exists = false;
        foreach ($patients as $p) {
            if ($p['id'] === $id) {
                $exists = true;
                break;
            }
        }
        if ($exists) {
            $next_num++;
        }
    } while ($exists);

    $birth_date = date('Y-m-d', strtotime("-$age years"));

    $_SESSION['patients'][] = [
        'id' => $id,
        'name' => $name,
        'gender' => $gender,
        'birth_date' => $birth_date,
        'age' => (int)$age,
        'address' => $address,
        'phone' => $phone,
        'reg_date' => date('Y-m-d'),
        'status' => 'Active',
        'vitals' => null,
        'consultations' => []
    ];
    
    $aud_user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin Utama';
    $aud_role = isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'ADMIN';
    db_add_audit($aud_user, $aud_role, 'PATIENT', "Tambah Pasien Baru #$id - $name");
    return $id;
}


function db_generate_queue_no($poly) {
    $queue_letter = 'A';
    $queues = $_SESSION['queues'] ?? [];
    
    $max_num = 0;
    foreach ($queues as $q) {
        if (strpos($q['no'], "$queue_letter-") === 0) {
            $num_part = substr($q['no'], 2);
            $num = (int)$num_part;
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }
    
    if ($max_num === 0) {
        $queue_num = 41;
    } else {
        $queue_num = $max_num + 1;
    }
    
    do {
        $queue_no = "$queue_letter-" . str_pad($queue_num, 3, '0', STR_PAD_LEFT);
        $exists = false;
        foreach ($queues as $q) {
            if ($q['no'] === $queue_no) {
                $exists = true;
                break;
            }
        }
        if ($exists) {
            $queue_num++;
        }
    } while ($exists);
    
    return $queue_no;
}


function db_generate_payment_id() {
    $date_prefix = 'TRX-' . date('Ymd') . '-';
    $payments = $_SESSION['payments'] ?? [];
    
    $max_num = 0;
    foreach ($payments as $p) {
        if (strpos($p['id'], $date_prefix) === 0) {
            $num_part = substr($p['id'], strlen($date_prefix));
            $num = (int)$num_part;
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }
    
    $next_num = $max_num + 1;
    do {
        $trx_id = $date_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        $exists = false;
        foreach ($payments as $p) {
            if ($p['id'] === $trx_id) {
                $exists = true;
                break;
            }
        }
        if ($exists) {
            $next_num++;
        }
    } while ($exists);
    
    return $trx_id;
}


function db_generate_referral_id() {
    $year_prefix = 'REF-' . date('Y') . '-';
    $referrals = $_SESSION['referrals'] ?? [];
    
    $max_num = 0;
    foreach ($referrals as $r) {
        if (strpos($r['id'], $year_prefix) === 0) {
            $num_part = substr($r['id'], strlen($year_prefix));
            $num = (int)$num_part;
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }
    
    if ($max_num === 0) {
        $next_num = 773; 
    } else {
        $next_num = $max_num + 1;
    }
    
    do {
        $ref_id = $year_prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        $exists = false;
        foreach ($referrals as $r) {
            if ($r['id'] === $ref_id) {
                $exists = true;
                break;
            }
        }
        if ($exists) {
            $next_num++;
        }
    } while ($exists);
    
    return $ref_id;
}



function db_add_vitals($patient_id, $td, $hr, $temp, $weight, $gula, $asam, $keluhan) {
    if (isset($_SESSION['patients'])) {
        foreach ($_SESSION['patients'] as &$p) {
            if ($p['id'] === $patient_id) {
                $p['vitals'] = [
                    'td' => $td,
                    'hr' => (int)$hr,
                    'temp' => (float)$temp,
                    'weight' => (float)$weight,
                    'gula' => !empty($gula) ? (int)$gula : null,
                    'asam' => !empty($asam) ? (float)$asam : null,
                    'keluhan' => $keluhan
                ];
                
                $aud_user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin Utama';
                $aud_role = isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'ADMIN';
                db_add_audit($aud_user, $aud_role, 'PATIENT', "Pemeriksaan Awal Pasien $patient_id");
                return true;
            }
        }
        unset($p);
    }
    return false;
}


function db_add_audit($user, $role, $category, $action) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? ($_SERVER['REMOTE_ADDR'] === '::1' ? '127.0.0.1' : $_SERVER['REMOTE_ADDR']) : '127.0.0.1';
    array_unshift($_SESSION['audit_logs'], [
        'time' => date('Y-m-d H:i') . ' WIB',
        'user' => $user,
        'role' => $role,
        'category' => $category,
        'action' => $action,
        'ip' => $ip
    ]);
}
?>
