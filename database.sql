-- MedXpert Database Schema
-- Modern Medical Platform with Complete Features

-- Create an empty database first (for example in phpMyAdmin) and select it,
-- then import this file. This file does not create, drop, or select a database,
-- so importing it never deletes existing data. Tables that already exist are
-- skipped with an error, so run it once on an empty database.

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    email_verified_at DATETIME NULL,
    consented_at DATETIME NULL,
    consent_version VARCHAR(20) NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Login sessions (one row per signed-in browser)
CREATE TABLE auth_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    remember TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uq_token (token_hash),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Doctors table
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    experience INT NOT NULL,
    qualification VARCHAR(255) NOT NULL,
    hospital VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL,
    fee DECIMAL(10,2) NOT NULL,
    rating DECIMAL(3,2) DEFAULT 4.50,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    available_days VARCHAR(100) DEFAULT 'Mon-Fri',
    available_time VARCHAR(50) DEFAULT '9:00 AM - 5:00 PM',
    bio TEXT,
    image VARCHAR(255) DEFAULT 'default-doctor.png',
    status ENUM('active', 'inactive') DEFAULT 'active',
    verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_specialization (specialization),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Blood Donors table
CREATE TABLE blood_donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    age INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    last_donation DATE,
    available BOOLEAN DEFAULT TRUE,
    emergency_contact VARCHAR(20),
    medical_conditions TEXT,
    image VARCHAR(255) DEFAULT 'default-donor.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_type (blood_type),
    INDEX idx_location (location),
    INDEX idx_available (available)
) ENGINE=InnoDB;

-- Medicines table
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    description TEXT,
    manufacturer VARCHAR(150),
    dosage VARCHAR(100),
    image VARCHAR(255) DEFAULT 'default-medicine.png',
    prescription_required BOOLEAN DEFAULT FALSE,
    status ENUM('available', 'out_of_stock') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Appointments table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    symptoms TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    consultation_type ENUM('in-person', 'video', 'phone') DEFAULT 'in-person',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_date (appointment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Medicine Orders table
CREATE TABLE medicine_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    payment_method ENUM('cash', 'card', 'mobile_banking') DEFAULT 'cash',
    prescription_image VARCHAR(255) NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Order Items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES medicine_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ambulance Requests table
CREATE TABLE ambulance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    pickup_location TEXT NOT NULL,
    destination TEXT NOT NULL,
    emergency_type VARCHAR(100),
    patient_condition TEXT,
    request_type ENUM('emergency', 'scheduled') DEFAULT 'emergency',
    status ENUM('pending', 'assigned', 'en_route', 'completed', 'cancelled') DEFAULT 'pending',
    ambulance_number VARCHAR(50),
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_request_type (request_type)
) ENGINE=InnoDB;

-- Login and registration attempts (used for rate limiting)
CREATE TABLE login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(10) NOT NULL DEFAULT 'login',
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kind_ip_time (kind, ip, created_at),
    INDEX idx_kind_ip_email_time (kind, ip, email, created_at)
) ENGINE=InnoDB;

-- Insert Sample Data

-- Admin User
-- The admin password is set when you install. Sample users cannot log in
-- until you reset their password. Register a new account to test as a user.
INSERT INTO users (full_name, email, password, phone, role) VALUES
('Admin User', 'admin@medxpert.com', '$2y$12$OvfK4umC9bA/dEDhlMGgf.mz6szVsMrNG1B0gxUI7TdezBvaEZ5dW', '+8801712345678', 'admin'),
('John Doe', 'john@example.com', '!', '+8801898765432', 'user'),
('Sarah Smith', 'sarah@example.com', '!', '+8801787654321', 'user');

-- Sample Doctors
INSERT INTO doctors (name, specialization, experience, qualification, hospital, location, fee, rating, phone, email, bio, available_days, available_time) VALUES
('Dr. Ashraf Rahman', 'Cardiology', 15, 'MBBS, MD (Cardiology)', 'Square Hospital', 'Dhaka', 1500.00, 4.8, '+8801711234567', 'ashraf@medxpert.com', 'Experienced cardiologist specializing in heart disease treatment and prevention.', 'Mon-Sat', '10:00 AM - 6:00 PM'),
('Dr. Fatima Khan', 'Dermatology', 10, 'MBBS, FCPS (Dermatology)', 'United Hospital', 'Dhaka', 1200.00, 4.7, '+8801712345678', 'fatima@medxpert.com', 'Skin specialist with focus on cosmetic and clinical dermatology.', 'Sun-Thu', '2:00 PM - 8:00 PM'),
('Dr. Kamal Hossain', 'Orthopedics', 12, 'MBBS, MS (Orthopedics)', 'Apollo Hospital', 'Dhaka', 1300.00, 4.9, '+8801723456789', 'kamal@medxpert.com', 'Expert in bone, joint and muscle disorders with surgical expertise.', 'Mon-Sat', '9:00 AM - 3:00 PM'),
('Dr. Nusrat Jahan', 'Pediatrics', 8, 'MBBS, DCH', 'Ibn Sina Hospital', 'Dhaka', 1000.00, 4.6, '+8801734567890', 'nusrat@medxpert.com', 'Dedicated pediatrician caring for children from newborn to adolescence.', 'Mon-Fri', '11:00 AM - 7:00 PM'),
('Dr. Rafiq Ahmed', 'Neurology', 18, 'MBBS, FCPS (Neurology)', 'Bangabandhu Medical', 'Dhaka', 2000.00, 4.9, '+8801745678901', 'rafiq@medxpert.com', 'Neurologist specializing in brain and nervous system disorders.', 'Sun-Thu', '8:00 AM - 2:00 PM'),
('Dr. Ayesha Siddiqua', 'Gynecology', 14, 'MBBS, FCPS (Gynecology)', 'Labaid Hospital', 'Dhaka', 1400.00, 4.8, '+8801756789012', 'ayesha@medxpert.com', 'Women\'s health specialist providing comprehensive gynecological care.', 'Mon-Sat', '3:00 PM - 9:00 PM'),
('Dr. Mahmud Hassan', 'General Medicine', 20, 'MBBS, FCPS (Medicine)', 'Popular Diagnostic', 'Dhaka', 800.00, 4.5, '+8801767890123', 'mahmud@medxpert.com', 'General physician with two decades of experience in primary care.', 'Mon-Sat', '9:00 AM - 5:00 PM'),
('Dr. Shahana Begum', 'Ophthalmology', 11, 'MBBS, DO (Ophthalmology)', 'National Eye Care', 'Dhaka', 1100.00, 4.7, '+8801778901234', 'shahana@medxpert.com', 'Eye care specialist offering medical and surgical treatment.', 'Sun-Thu', '10:00 AM - 4:00 PM');

-- Sample Blood Donors
INSERT INTO blood_donors (name, blood_type, age, phone, email, location, last_donation, available) VALUES
('Rahim Uddin', 'O+', 28, '+8801812345678', 'rahim@example.com', 'Mirpur, Dhaka', '2024-01-15', TRUE),
('Salma Khatun', 'A+', 32, '+8801823456789', 'salma@example.com', 'Dhanmondi, Dhaka', '2024-02-20', TRUE),
('Tareq Islam', 'B+', 25, '+8801834567890', 'tareq@example.com', 'Uttara, Dhaka', '2024-03-10', TRUE),
('Nasrin Akter', 'AB+', 30, '+8801845678901', 'nasrin@example.com', 'Gulshan, Dhaka', '2023-12-05', FALSE),
('Jahangir Alam', 'O-', 35, '+8801856789012', 'jahangir@example.com', 'Banani, Dhaka', '2024-01-25', TRUE),
('Rupa Begum', 'A-', 27, '+8801867890123', 'rupa@example.com', 'Mohammadpur, Dhaka', '2024-02-14', TRUE),
('Karim Ahmed', 'B-', 29, '+8801878901234', 'karim@example.com', 'Rampura, Dhaka', '2024-03-05', TRUE),
('Shireen Rahman', 'AB-', 31, '+8801889012345', 'shireen@example.com', 'Bashundhara, Dhaka', '2023-11-20', FALSE),
('Monir Hossain', 'O+', 26, '+8801890123456', 'monir@example.com', 'Lalmatia, Dhaka', '2024-02-28', TRUE),
('Parveen Sultana', 'A+', 33, '+8801801234567', 'parveen@example.com', 'Shantinagar, Dhaka', '2024-01-18', TRUE);

-- Sample Medicines
INSERT INTO medicines (name, category, price, stock, description, manufacturer, dosage, prescription_required) VALUES
('Napa Extra', 'Pain Relief', 3.50, 500, 'Paracetamol 500mg + Caffeine for pain and fever relief', 'Beximco Pharma', '1-2 tablets every 6 hours', FALSE),
('Seclo 20', 'Gastric', 5.00, 300, 'Omeprazole 20mg for acid reflux and ulcers', 'Square Pharma', '1 capsule daily before breakfast', FALSE),
('Filmet 400', 'Antibiotic', 8.50, 200, 'Metronidazole 400mg antibiotic', 'ACI Limited', '1 tablet 3 times daily', TRUE),
('Ace Plus', 'Blood Pressure', 12.00, 150, 'Amlodipine + Atenolol for hypertension', 'Incepta Pharma', '1 tablet once daily', TRUE),
('Fexo 120', 'Allergy', 6.00, 400, 'Fexofenadine 120mg antihistamine', 'Eskayef Pharma', '1 tablet once daily', FALSE),
('Glucophage 500', 'Diabetes', 4.50, 250, 'Metformin 500mg for type 2 diabetes', 'Square Pharma', '1-2 tablets twice daily with meals', TRUE),
('Alatrol 10', 'Allergy', 4.00, 350, 'Cetirizine 10mg for allergic rhinitis', 'Drug International', '1 tablet once daily', FALSE),
('Monas 10', 'Respiratory', 15.00, 180, 'Montelukast 10mg for asthma and allergies', 'Beximco Pharma', '1 tablet once daily at bedtime', TRUE),
('Maxpro 20', 'Gastric', 7.50, 220, 'Esomeprazole 20mg for GERD', 'Renata Limited', '1 capsule daily before meal', FALSE),
('Zimax 500', 'Antibiotic', 25.00, 100, 'Azithromycin 500mg antibiotic', 'Square Pharma', '1 tablet once daily for 3 days', TRUE),
('Atova 20', 'Cholesterol', 18.00, 160, 'Atorvastatin 20mg for high cholesterol', 'Healthcare Pharma', '1 tablet once daily at night', TRUE),
('Flexi 90', 'Pain Relief', 10.00, 280, 'Etoricoxib 90mg for arthritis pain', 'Aristopharma', '1 tablet once daily', TRUE),
('Caltrate Plus', 'Vitamin', 8.00, 300, 'Calcium + Vitamin D3 supplement', 'General Pharma', '1 tablet daily with meal', FALSE),
('Sergel 20', 'Mental Health', 22.00, 120, 'Sertraline 20mg for depression and anxiety', 'Incepta Pharma', '1 tablet once daily', TRUE),
('Osartil 50', 'Blood Pressure', 14.00, 190, 'Losartan 50mg for hypertension', 'ACI Limited', '1 tablet once daily', TRUE);

-- Sample Appointments
INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, symptoms, status, consultation_type) VALUES
(2, 1, '2024-04-15', '11:00:00', 'Chest pain and shortness of breath', 'confirmed', 'in-person'),
(3, 4, '2024-04-16', '14:00:00', 'Child has fever and cough', 'pending', 'in-person'),
(2, 2, '2024-04-17', '15:30:00', 'Skin rash on arms', 'confirmed', 'video');

-- Sample Medicine Orders
INSERT INTO medicine_orders (user_id, order_number, total_amount, delivery_address, phone, status) VALUES
(2, 'ORD-2024-0001', 45.50, '123 Mirpur, Dhaka', '+8801898765432', 'delivered'),
(3, 'ORD-2024-0002', 28.00, '456 Dhanmondi, Dhaka', '+8801787654321', 'processing');

INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES
(1, 1, 10, 3.50),
(1, 2, 2, 5.00),
(2, 5, 4, 6.00),
(2, 7, 1, 4.00);

-- Sample Ambulance Requests
INSERT INTO ambulance_requests (user_id, name, phone, pickup_location, destination, emergency_type, status, request_type) VALUES
(2, 'John Doe', '+8801898765432', 'House 45, Road 12, Mirpur', 'Square Hospital, Dhaka', 'Heart Emergency', 'completed', 'emergency'),
(NULL, 'Unknown Patient', '+8801999888777', 'Kawran Bazar, Dhaka', 'United Hospital, Dhaka', 'Accident', 'pending', 'emergency');

-- Phase 4 tables
CREATE TABLE doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    weekday TINYINT NOT NULL COMMENT '0 = Sunday ... 6 = Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_minutes SMALLINT NOT NULL DEFAULT 30,
    UNIQUE KEY uniq_doctor_day (doctor_id, weekday),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES medicine_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(500) NOT NULL DEFAULT '',
    link VARCHAR(200) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    kind ENUM('reset', 'verify') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_hash (token_hash),
    INDEX idx_user_kind (user_id, kind),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Give every existing doctor Monday to Friday, 09:00 to 17:00, 30 minute slots
INSERT IGNORE INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes)
SELECT d.id, w.weekday, '09:00:00', '17:00:00', 30
FROM doctors d
JOIN (SELECT 1 AS weekday UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) w;

INSERT INTO order_status_history (order_id, status, created_at)
SELECT id, 'pending', created_at FROM medicine_orders;

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action VARCHAR(60) NOT NULL,
    target_type VARCHAR(40) NOT NULL DEFAULT '',
    target_id INT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
