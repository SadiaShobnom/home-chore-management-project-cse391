-- Home Chore Management System
-- Database Schema

CREATE DATABASE IF NOT EXISTS `chore_system`;
USE `chore_system`;

-- Parents table
CREATE TABLE IF NOT EXISTS `parents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kids table (linked to a parent)
CREATE TABLE IF NOT EXISTS `kids` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `points` INT DEFAULT 0,
    `avatar` VARCHAR(10) DEFAULT '🦸',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chores table (assigned to a kid by a parent)
CREATE TABLE IF NOT EXISTS `chores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT NOT NULL,
    `kid_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `points` INT DEFAULT 10,
    `status` ENUM('pending', 'completed') DEFAULT 'pending',
    `due_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`kid_id`) REFERENCES `kids`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
