-- ============================================================
-- PetPal Rental Management and Decision Support System
-- Database Setup Script
-- Run this file once to create all tables and sample data
-- ============================================================

CREATE DATABASE IF NOT EXISTS petpal_db;
USE petpal_db;

-- ============================================================
-- TABLE: users
-- Stores admin and staff login accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    status      ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: pets
-- Stores all pet records available for rental
-- ============================================================
CREATE TABLE IF NOT EXISTS pets (
    pet_id        INT AUTO_INCREMENT PRIMARY KEY,
    pet_name      VARCHAR(100) NOT NULL,
    species       VARCHAR(50) NOT NULL,
    breed         VARCHAR(100),
    age           INT,
    gender        ENUM('Male', 'Female') NOT NULL,
    rental_price  DECIMAL(10,2) NOT NULL,
    status        ENUM('Available', 'Rented', 'Archived') NOT NULL DEFAULT 'Available',
    photo         VARCHAR(255) DEFAULT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: customers
-- Stores customer records
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
    customer_id     INT AUTO_INCREMENT PRIMARY KEY,
    customer_name   VARCHAR(100) NOT NULL,
    contact_number  VARCHAR(20) NOT NULL,
    address         TEXT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: rentals
-- Records each pet rental transaction
-- ============================================================
CREATE TABLE IF NOT EXISTS rentals (
    rental_id         INT AUTO_INCREMENT PRIMARY KEY,
    customer_id       INT NOT NULL,
    pet_id            INT NOT NULL,
    rental_date       DATE NOT NULL,
    expected_return   DATE NOT NULL,
    actual_return     DATE DEFAULT NULL,
    rental_days       INT NOT NULL,
    rental_fee        DECIMAL(10,2) NOT NULL,
    status            ENUM('Active', 'Returned', 'Cancelled') NOT NULL DEFAULT 'Active',
    created_by        INT,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (pet_id)      REFERENCES pets(pet_id),
    FOREIGN KEY (created_by)  REFERENCES users(user_id)
);

-- ============================================================
-- TABLE: payments
-- Records payment for each rental
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    rental_id       INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  ENUM('Cash') NOT NULL DEFAULT 'Cash',
    payment_date    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_id) REFERENCES rentals(rental_id)
);

-- ============================================================
-- TABLE: penalties
-- Records late return penalties
-- ============================================================
CREATE TABLE IF NOT EXISTS penalties (
    penalty_id  INT AUTO_INCREMENT PRIMARY KEY,
    rental_id   INT NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    reason      VARCHAR(255) DEFAULT 'Late Return',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_id) REFERENCES rentals(rental_id)
);

-- ============================================================
-- SAMPLE DATA
-- Default password for BOTH accounts is: password
-- Change after setup using: password_hash('newpass', PASSWORD_DEFAULT)
-- ============================================================
INSERT INTO users (username, password, full_name, role) VALUES
('admin',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin'),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Boris Alano', 'staff');

INSERT INTO pets (pet_name, species, breed, age, gender, rental_price, status) VALUES
('Buddy',   'Dog',    'Labrador Retriever', 3, 'Male',   500.00, 'Available'),
('Luna',    'Cat',    'Persian',            2, 'Female', 300.00, 'Available'),
('Max',     'Dog',    'German Shepherd',    4, 'Male',   600.00, 'Available'),
('Mochi',   'Rabbit', 'Dutch',              1, 'Female', 200.00, 'Available'),
('Coco',    'Dog',    'Shih Tzu',           2, 'Female', 450.00, 'Available'),
('Tiger',   'Cat',    'Siamese',            3, 'Male',   350.00, 'Available'),
('Peanut',  'Rabbit', 'Angora',             1, 'Male',   250.00, 'Available'),
('Charlie', 'Dog',    'Poodle',             5, 'Male',   400.00, 'Available');

INSERT INTO customers (customer_name, contact_number, address) VALUES
('Maria Santos',     '09171234567', 'Rosario, Cavite'),
('Jose Reyes',       '09281234567', 'General Trias, Cavite'),
('Ana Garcia',       '09391234567', 'Bacoor, Cavite'),
('Pedro Lim',        '09451234567', 'Imus, Cavite'),
('Carmen Villanueva','09561234567', 'Dasmariñas, Cavite');