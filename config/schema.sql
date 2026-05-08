-- phpMyAdmin SQL Dump
-- Database: `luminacare_db`
-- Structure for Maternal Health Remote Monitoring System

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+01:00"; -- Africa/Lagos

-- --------------------------------------------------------
-- Create Database (if not exists)
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `luminacare_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `luminacare_db`;

-- --------------------------------------------------------
-- 1. ADMINS TABLE
-- Isolated table strictly for system administrators
-- --------------------------------------------------------
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert a default admin account (Password: Admin@123)
-- IMPORTANT: Change this password in production!
INSERT INTO `admins` (`username`, `email`, `password_hash`) VALUES
('superadmin', 'admin@luminacare.local', '$2y$10$e.wXqH4.FhM9F7mR0P0bZeJtU6qW4U7n9Qk4c2tG3e1V4y5b6n7M.');

-- --------------------------------------------------------
-- 2. DOCTORS TABLE
-- Isolated table strictly for approved healthcare providers
-- --------------------------------------------------------
CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `specialization` varchar(100) DEFAULT 'Obstetrician/Gynecologist',
  `is_active` tinyint(1) DEFAULT 0, -- Admins must verify doctors before they can log in
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. PATIENTS (MOTHERS) TABLE
-- Isolated table strictly for expectant mothers
-- --------------------------------------------------------
CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) DEFAULT NULL, -- The assigned doctor
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `expected_due_date` date DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`patient_id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. VITALS LOGS TABLE
-- Stores daily/weekly health metrics submitted by patients
-- --------------------------------------------------------
CREATE TABLE `vitals` (
  `vital_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `systolic_bp` int(3) DEFAULT NULL,    -- e.g., 120
  `diastolic_bp` int(3) DEFAULT NULL,   -- e.g., 80
  `heart_rate` int(3) DEFAULT NULL,     -- Beats per minute
  `weight_kg` decimal(5,2) DEFAULT NULL,-- e.g., 75.50
  `blood_sugar_mgdl` int(4) DEFAULT NULL, 
  `symptoms_notes` text DEFAULT NULL,   -- Any symptoms the patient typed
  `recorded_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`vital_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. MESSAGES TABLE (Chat System)
-- Handles communication between patients and their assigned doctors
-- --------------------------------------------------------
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('patient','doctor') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('patient','doctor') NOT NULL,
  `message_body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. ALERTS TABLE
-- Stores system-generated alerts when vitals are abnormal
-- --------------------------------------------------------
CREATE TABLE `alerts` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `vital_id` int(11) DEFAULT NULL, -- Link to the specific vital entry that triggered the alert
  `alert_type` varchar(50) NOT NULL, -- e.g., 'High Blood Pressure', 'Critical Blood Sugar'
  `alert_message` text NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0, -- Doctor marks this as 1 when handled
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`alert_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE,
  FOREIGN KEY (`vital_id`) REFERENCES `vitals`(`vital_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. APPOINTMENTS TABLE
-- For scheduling checkups or remote consultations
-- --------------------------------------------------------
CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;