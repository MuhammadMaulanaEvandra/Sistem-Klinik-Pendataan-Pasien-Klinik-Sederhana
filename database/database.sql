


CREATE DATABASE IF NOT EXISTS `clinicgg_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clinicgg_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `backups` CASCADE;
DROP TABLE IF EXISTS `audit_logs` CASCADE;
DROP TABLE IF EXISTS `stock_logs` CASCADE;
DROP TABLE IF EXISTS `payments` CASCADE;
DROP TABLE IF EXISTS `referrals` CASCADE;
DROP TABLE IF EXISTS `medicines` CASCADE;
DROP TABLE IF EXISTS `consultations` CASCADE;
DROP TABLE IF EXISTS `patient_vitals` CASCADE;
DROP TABLE IF EXISTS `queues` CASCADE;
DROP TABLE IF EXISTS `patients` CASCADE;
DROP TABLE IF EXISTS `polyclinics` CASCADE;
DROP TABLE IF EXISTS `doctors` CASCADE;
DROP TABLE IF EXISTS `users` CASCADE;

CREATE TABLE `users` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(50) UNIQUE,
  `password` varchar(255),
  `name` varchar(100),
  `role` varchar(20),
  `status` varchar(20),
  `photo` varchar(255) NULL,
  `created_at` datetime
) ENGINE=InnoDB;

CREATE TABLE `doctors` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int,
  `specialization` varchar(100),
  `sip_number` varchar(100),
  `phone` varchar(20),
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `polyclinics` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100),
  `description` text,
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `patients` (
  `id` varchar(50) PRIMARY KEY,
  `name` varchar(100),
  `gender` varchar(20),
  `birth_date` date,
  `age` int,
  `address` text,
  `phone` varchar(20),
  `reg_date` date,
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `queues` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `queue_number` varchar(20),
  `patient_id` varchar(50),
  `polyclinic_id` int,
  `doctor_id` int,
  `queue_date` date,
  `queue_time` time,
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `patient_vitals` (
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
) ENGINE=InnoDB;

CREATE TABLE `consultations` (
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
) ENGINE=InnoDB;

CREATE TABLE `medicines` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100),
  `category` varchar(50),
  `price` int,
  `stock` int,
  `max_stock` int,
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `referrals` (
  `id` varchar(50) PRIMARY KEY,
  `consultation_id` int,
  `patient_id` varchar(50),
  `hospital` varchar(100),
  `diagnosis` text,
  `reason` text,
  `referral_date` date,
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `payments` (
  `id` varchar(50) PRIMARY KEY,
  `consultation_id` int,
  `patient_id` varchar(50),
  `service` varchar(100),
  `payment_method` varchar(50),
  `amount` bigint,
  `payment_date` datetime,
  `status` varchar(20)
) ENGINE=InnoDB;

CREATE TABLE `stock_logs` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `medicine_id` int,
  `user_id` int,
  `type` varchar(20),
  `description` text,
  `quantity` int,
  `log_time` datetime
) ENGINE=InnoDB;

CREATE TABLE `audit_logs` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int,
  `category` varchar(50),
  `action` text,
  `ip_address` varchar(50),
  `created_at` datetime
) ENGINE=InnoDB;

CREATE TABLE `backups` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `backup_time` datetime,
  `file_name` varchar(100),
  `file_size` varchar(20),
  `status` varchar(20)
) ENGINE=InnoDB;

ALTER TABLE `doctors` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `queues` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `queues` ADD FOREIGN KEY (`polyclinic_id`) REFERENCES `polyclinics` (`id`) ON DELETE CASCADE;
ALTER TABLE `queues` ADD FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;
ALTER TABLE `patient_vitals` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `patient_vitals` ADD FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE SET NULL;
ALTER TABLE `consultations` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `consultations` ADD FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;
ALTER TABLE `consultations` ADD FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE SET NULL;
ALTER TABLE `referrals` ADD FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE;
ALTER TABLE `referrals` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `payments` ADD FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL;
ALTER TABLE `payments` ADD FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `stock_logs` ADD FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE;
ALTER TABLE `stock_logs` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `audit_logs` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;




INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`, `status`, `created_at`) VALUES
(1, 'admin@klinik.com', 'password', 'Admin Utama', 'admin', 'Active', NOW()),
(2, 'raka@klinik.com', 'password', 'Dr. Raka Aji', 'dokter', 'Active', NOW()),
(3, 'rani@klinik.com', 'password', 'Dr. Rani dwi hapsari', 'dokter', 'Active', NOW()),
(4, 'owner@klinik.com', 'password', 'Dr. Raka Aji', 'owner', 'Active', NOW());

INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `sip_number`, `phone`, `status`) VALUES
(1, 2, 'General Practitioner', 'SIP-12345-2026', '08123456789', 'Active'),
(2, 3, 'General Practitioner', 'SIP-67890-2026', '08987654321', 'Active');

INSERT INTO `polyclinics` (`id`, `name`, `description`, `status`) VALUES
(1, 'Poli Umum', 'Poliklinik Umum', 'Active');

INSERT INTO `medicines` (`id`, `name`, `category`, `price`, `stock`, `max_stock`, `status`) VALUES
(1, 'Amoxicillin 500mg', 'Antibiotik', 12500, 850, 1000, 'Aman'),
(2, 'Paracetamol Syrup', 'Analgesik', 24000, 175, 500, 'Rendah'),
(3, 'Atorvastatin 20mg', 'Kolesterol', 45800, 12, 100, 'Kritis'),
(4, 'Vitamin C 1000mg', 'Suplemen', 8000, 1900, 2000, 'Aman'),
(5, 'Cetirizine 10mg', 'Antihistamin', 15000, 320, 500, 'Aman'),
(6, 'Ibuprofen 400mg', 'Analgesik', 18000, 512, 1000, 'Aman');

INSERT INTO `audit_logs` (`id`, `user_id`, `category`, `action`, `ip_address`, `created_at`) VALUES
(1, 1, 'SYSTEM', 'Sistem Khas Medicare Pro berhasil dimigrasikan', '127.0.0.1', NOW());

SET FOREIGN_KEY_CHECKS = 1;
