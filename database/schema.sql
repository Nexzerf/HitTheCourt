-- Database: hit_the_court
-- Complete Relational Schema

CREATE DATABASE IF NOT EXISTS hit_the_court CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hit_the_court;

-- USERS TABLE
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    is_member BOOLEAN DEFAULT 0,
    member_expire DATE NULL,
    points INT DEFAULT 0,
    total_bookings INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SPORTS TABLE
CREATE TABLE sports (
    sport_id INT AUTO_INCREMENT PRIMARY KEY,
    sport_name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    price INT NOT NULL,
    max_courts INT DEFAULT 1,
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- COURTS TABLE
CREATE TABLE courts (
    court_id INT AUTO_INCREMENT PRIMARY KEY,
    sport_id INT NOT NULL,
    court_number INT NOT NULL,
    court_name VARCHAR(50),
    status ENUM('available', 'maintenance', 'reserved') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(sport_id) ON DELETE CASCADE,
    UNIQUE KEY unique_court (sport_id, court_number)
);

-- TIME SLOTS TABLE
CREATE TABLE time_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    sport_id INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (sport_id) REFERENCES sports(sport_id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (sport_id, start_time)
);

-- EQUIPMENT TABLE
CREATE TABLE equipment (
    eq_id INT AUTO_INCREMENT PRIMARY KEY,
    sport_id INT NOT NULL,
    eq_name VARCHAR(100) NOT NULL,
    price INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    fine_amount INT DEFAULT 50,
    max_per_court INT DEFAULT 10,
    status ENUM('available', 'unavailable') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(sport_id) ON DELETE CASCADE
);

-- BOOKINGS TABLE
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    court_id INT NOT NULL,
    slot_id INT NOT NULL,
    booking_date DATE NOT NULL,
    duration_minutes INT NOT NULL,
    court_price DECIMAL(10,2) NOT NULL,
    equipment_total DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    booking_status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (court_id) REFERENCES courts(court_id),
    FOREIGN KEY (slot_id) REFERENCES time_slots(slot_id)
);

-- BOOKING EQUIPMENT TABLE
CREATE TABLE booking_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    eq_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (eq_id) REFERENCES equipment(eq_id)
);

-- PAYMENTS TABLE
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method ENUM('bank_transfer', 'promptpay', 'cash') DEFAULT 'promptpay',
    amount DECIMAL(10,2) NOT NULL,
    slip_image VARCHAR(255),
    reference_code VARCHAR(100),
    payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

-- MEMBERSHIP PLANS TABLE
CREATE TABLE membership_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(50) NOT NULL,
    duration_months INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    discount_day1 INT DEFAULT 30,
    discount_day16 INT DEFAULT 30,
    discount_consecutive INT DEFAULT 20,
    discount_first_booking INT DEFAULT 10,
    free_equipment_limit INT DEFAULT 4,
    advance_booking_days INT DEFAULT 7,
    features TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- USER MEMBERSHIP TABLE
CREATE TABLE user_membership (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(plan_id)
);

-- REPORTS TABLE
CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    report_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    topic VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255),
    status ENUM('new', 'in_progress', 'resolved') DEFAULT 'new',
    admin_notes TEXT,
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ADMINS TABLE
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    role ENUM('super_admin', 'admin', 'staff') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SETTINGS TABLE
CREATE TABLE settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- INSERT DEFAULT SPORTS DATA
INSERT INTO sports (sport_name, slug, duration_minutes, price, max_courts) VALUES
('Badminton', 'badminton', 60, 130, 4),
('Football', 'football', 100, 500, 2),
('Tennis', 'tennis', 120, 200, 4),
('Volleyball', 'volleyball', 120, 150, 4),
('Basketball', 'basketball', 60, 130, 8),
('Table Tennis', 'table-tennis', 60, 40, 6),
('Futsal', 'futsal', 60, 130, 2);

-- INSERT COURTS FOR EACH SPORT
INSERT INTO courts (sport_id, court_number, court_name, status)
SELECT s.sport_id, n.n, CONCAT(s.sport_name, ' Court ', n.n), 'available'
FROM sports s
CROSS JOIN (
    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
) n
WHERE n.n <= s.max_courts;

-- INSERT TIME SLOTS FOR BADMINTON (1 hour slots)
INSERT INTO time_slots (sport_id, start_time, end_time)
SELECT 1, 
    CASE 
        WHEN n = 1 THEN '09:00:00'
        WHEN n = 2 THEN '10:10:00'
        WHEN n = 3 THEN '11:20:00'
        WHEN n = 4 THEN '12:30:00'
        WHEN n = 5 THEN '13:40:00'
        WHEN n = 6 THEN '14:50:00'
        WHEN n = 7 THEN '16:00:00'
        WHEN n = 8 THEN '17:10:00'
        WHEN n = 9 THEN '18:20:00'
        WHEN n = 10 THEN '19:30:00'
        WHEN n = 11 THEN '20:40:00'
    END,
    CASE 
        WHEN n = 1 THEN '10:00:00'
        WHEN n = 2 THEN '11:10:00'
        WHEN n = 3 THEN '12:20:00'
        WHEN n = 4 THEN '13:30:00'
        WHEN n = 5 THEN '14:40:00'
        WHEN n = 6 THEN '15:50:00'
        WHEN n = 7 THEN '17:00:00'
        WHEN n = 8 THEN '18:10:00'
        WHEN n = 9 THEN '19:20:00'
        WHEN n = 10 THEN '20:30:00'
        WHEN n = 11 THEN '21:40:00'
    END
FROM (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) nums;

-- INSERT EQUIPMENT FOR EACH SPORT
INSERT INTO equipment (sport_id, eq_name, price, stock, max_per_court) VALUES
(1, 'Badminton Racket', 10, 50, 25),
(2, 'Football', 50, 40, 20),
(2, 'Training Bib', 30, 10, 5),
(2, 'Training Cone', 20, 10, 5),
(2, 'Training Equipment Set', 300, 6, 3),
(3, 'Tennis Racket', 50, 16, 8),
(3, 'Tennis Ball', 15, 20, 10),
(4, 'Volleyball', 50, 20, 10),
(5, 'Basketball', 50, 30, 15),
(6, 'Ping-Pong Racket', 10, 24, 12),
(6, 'Ping-Pong Ball', 5, 24, 12),
(7, 'Futsal Ball', 40, 40, 20);

-- INSERT MEMBERSHIP PLAN
INSERT INTO membership_plans (plan_name, duration_months, price, discount_day1, discount_day16, discount_consecutive, discount_first_booking, free_equipment_limit, advance_booking_days, features) VALUES
('Premium Plan', 3, 499, 30, 30, 20, 10, 4, 7, '7 Days Advance Booking;30% Discount on 1st & 16th;Free Equipment (4 items/month);Point Rewards System');

-- INSERT DEFAULT ADMIN
INSERT INTO admins (username, password, email, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@hitthecourt.com', 'System Admin', 'super_admin');
-- Password is 'password'

-- INSERT SETTINGS
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'Hit The Court', 'Website name'),
('promptpay_number', 'XXX-XX-XXXXX-X', 'PromptPay phone number'),
('bank_name', 'Krungthai Bank', 'Bank name'),
('bank_account', 'XXX-XX-XXXXX-X', 'Bank account number'),
('company_name', 'HIT THE COURT, LTD', 'Company name for payment');