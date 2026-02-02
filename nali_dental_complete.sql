-- =====================================================
-- NALI DENTAL CLINIC - DATABASE HOÀN CHỈNH
-- Cổng: 3307
-- Ngày tạo: 26/12/2025
-- =====================================================

-- Xóa database cũ nếu tồn tại (CẢNH BÁO: Mất hết dữ liệu!)
DROP DATABASE IF EXISTS nali_dental;

-- Tạo database mới
CREATE DATABASE nali_dental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nali_dental;

-- =====================================================
-- 1. BẢNG KHÁCH HÀNG (User Side - Đăng nhập)
-- =====================================================
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    gender ENUM('Nam', 'Nữ', 'Khác') DEFAULT 'Khác',
    birthday DATE NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. BẢNG NHÂN SỰ (Admin, Bác sĩ, Lễ tân)
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'doctor', 'receptionist') NOT NULL DEFAULT 'doctor',
    avatar VARCHAR(255) NULL,
    phone VARCHAR(15) NULL,
    specialty VARCHAR(100) NULL COMMENT 'Chuyên khoa nếu là bác sĩ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. DANH MỤC DỊCH VỤ
-- =====================================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. BẢNG DỊCH VỤ
-- =====================================================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10, 2) DEFAULT 0,
    image VARCHAR(255) NULL,
    duration INT DEFAULT 30 COMMENT 'Thời gian (phút)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. LỊCH HẸN
-- =====================================================
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NULL COMMENT 'Link tới bảng users có role doctor',
    service_id INT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_date (appointment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. HỒ SƠ BỆNH ÁN
-- =====================================================
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNIQUE NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    diagnosis TEXT NOT NULL COMMENT 'Chẩn đoán bệnh',
    treatment_plan TEXT NULL COMMENT 'Hướng điều trị',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. SƠ ĐỒ RĂNG (Quan trọng cho thi)
-- =====================================================
CREATE TABLE tooth_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    tooth_number INT NOT NULL COMMENT 'Số răng (1-32)',
    condition_text VARCHAR(100) NOT NULL COMMENT 'Vd: Sâu răng, Đã hàn, Mất răng',
    note TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient (patient_id),
    INDEX idx_tooth (tooth_number),
    UNIQUE KEY unique_patient_tooth (patient_id, tooth_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. HÓA ĐƠN (Hỗ trợ trả góp)
-- =====================================================
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medical_record_id INT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    paid_amount DECIMAL(15, 2) DEFAULT 0,
    status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid' COMMENT 'partial là đang trả góp',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. LỊCH SỬ THANH TOÁN
-- =====================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'card') DEFAULT 'cash',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id),
    INDEX idx_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DỮ LIỆU MẪU
-- =====================================================

-- Thêm danh mục dịch vụ
INSERT INTO categories (name, description) VALUES 
('Nha Khoa Trẻ Em', 'Dành cho bé từ 1-16 tuổi'),
('Thẩm mỹ', 'Làm đẹp nụ cười'),
('Chỉnh nha', 'Niềng răng và chỉnh hình răng'),
('Cấy ghép', 'Implant và trồng răng'),
('Điều trị', 'Điều trị bệnh lý răng miệng'),
('Tổng quát', 'Khám và vệ sinh răng miệng');

-- Thêm dịch vụ mẫu
INSERT INTO services (category_id, name, description, price, duration) VALUES 
(2, 'Tẩy trắng răng Laser', 'Công nghệ làm trắng răng tiên tiến với tia Laser an toàn', 2500000, 60),
(2, 'Bọc răng sứ Titan', 'Bọc răng sứ cao cấp, bền đẹp lâu dài', 4500000, 90),
(3, 'Niềng răng Invisalign', 'Niềng răng trong suốt không đau', 65000000, 120),
(4, 'Cấy ghép Implant', 'Trồng răng Implant công nghệ Hàn Quốc', 18000000, 120),
(5, 'Nhổ răng khôn', 'Nhổ răng khôn an toàn không đau', 1500000, 45),
(5, 'Điều trị tủy răng', 'Lấy tủy, điều trị viêm tủy chuyên sâu', 2000000, 60),
(6, 'Trám răng thẩm mỹ', 'Trám răng bằng composite cao cấp', 300000, 30),
(6, 'Lấy cao răng', 'Làm sạch cao răng, vệ sinh răng miệng', 200000, 30);

-- Thêm tài khoản nhân sự (Mật khẩu: 123456)
INSERT INTO users (username, password, full_name, role, specialty, phone) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản Trị Viên', 'admin', '', '0901234567'),
('admin2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Phụ', 'admin', '', '0905555555'),
('bs_nhat', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BS. Trần Minh Nhật', 'doctor', 'Chỉnh nha', '0902222222'),
('bs_mai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BS. Nguyễn Thị Mai', 'doctor', 'Cấy ghép Implant', '0903333333'),
('letân_lan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Võ Thị Lan', 'receptionist', '', '0904444444');

-- Thêm khách hàng mẫu (Mật khẩu: password123)
INSERT INTO patients (full_name, email, password, phone, gender, birthday, address) VALUES 
('Trần Thị Lan Anh', 'lananh@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0912345678', 'Nữ', '1995-08-20', '123 Đường ABC, Quận 1, TP.HCM'),
('Lê Hoàng Nam', 'namle@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0923456789', 'Nam', '1988-12-10', '456 Đường XYZ, Quận 3, TP.HCM'),
('Phạm Minh Anh', 'anhpm@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0934567890', 'Nữ', '1992-03-25', '789 Đường DEF, Quận 5, TP.HCM');

-- Thêm lịch hẹn mẫu
INSERT INTO appointments (patient_id, doctor_id, service_id, appointment_date, status, note) VALUES 
(1, 2, 1, '2025-12-28 09:00:00', 'confirmed', 'Muốn tư vấn thêm về quy trình'),
(2, 3, 3, '2025-12-29 14:00:00', 'pending', 'Đã từng niềng răng, muốn tư vấn lại'),
(3, 2, 5, '2025-12-30 10:30:00', 'confirmed', 'Răng khôn mọc lệch, đau nhiều');

-- =====================================================
-- KIỂM TRA KẾT QUẢ
-- =====================================================
SELECT 'Patients' AS 'Table', COUNT(*) AS 'Records' FROM patients
UNION ALL
SELECT 'Users (Staff)', COUNT(*) FROM users
UNION ALL
SELECT 'Categories', COUNT(*) FROM categories
UNION ALL
SELECT 'Services', COUNT(*) FROM services
UNION ALL
SELECT 'Appointments', COUNT(*) FROM appointments;

-- =====================================================
-- THÔNG TIN ĐĂNG NHẬP
-- =====================================================
-- ADMIN:
--   Username: admin
--   Password: 123456

-- BÁC SĨ:
--   Username: bs_nhat / Password: 123456
--   Username: bs_mai / Password: 123456

-- LỄ TÂN:
--   Username: letan_lan / Password: 123456

-- KHÁCH HÀNG:
--   Email: lananh@gmail.com / Password: password123
--   Email: namle@gmail.com / Password: password123
-- =====================================================
