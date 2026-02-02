-- =============================================
-- DATABASE: NALI DENTAL CLINIC
-- Ngày tạo: 26/12/2025
-- Mô tả: Tạo 4 bảng chính cho hệ thống
-- =============================================

-- Xóa bảng cũ nếu tồn tại (Cẩn thận khi chạy lại!)
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS patients;

-- =============================================
-- 1. BẢNG PATIENTS (Khách hàng)
-- =============================================
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other') DEFAULT 'other',
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 2. BẢNG SERVICES (Dịch vụ)
-- =============================================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(12, 2) NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    image VARCHAR(255),
    duration INT DEFAULT 30 COMMENT 'Thời gian dịch vụ (phút)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 3. BẢNG DOCTORS (Bác sĩ)
-- =============================================
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL COMMENT 'Chuyên môn',
    experience INT DEFAULT 0 COMMENT 'Số năm kinh nghiệm',
    education TEXT COMMENT 'Học vấn',
    description TEXT,
    image VARCHAR(255),
    phone VARCHAR(15),
    email VARCHAR(150),
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_specialty (specialty),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 4. BẢNG APPOINTMENTS (Đặt lịch hẹn)
-- =============================================
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    service_id INT NOT NULL,
    doctor_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT COMMENT 'Ghi chú của khách hàng',
    admin_notes TEXT COMMENT 'Ghi chú của admin',
    total_price DECIMAL(12, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_date (appointment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DỮ LIỆU MẪU
-- =============================================

-- Thêm Khách hàng mẫu
INSERT INTO patients (name, email, phone, password, address, date_of_birth, gender, role) VALUES
('Nguyễn Văn Admin', 'admin@nalidental.com', '0901234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123 Đường ABC, Quận 1, TP.HCM', '1990-05-15', 'male', 'admin'),
('Trần Thị Lan', 'lannt@gmail.com', '0912345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '456 Đường XYZ, Quận 3, TP.HCM', '1995-08-20', 'female', 'customer'),
('Lê Hoàng Nam', 'namle@gmail.com', '0923456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '789 Đường DEF, Quận 5, TP.HCM', '1988-12-10', 'male', 'customer'),
('Phạm Minh Anh', 'anhpm@gmail.com', '0934567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '321 Đường GHI, Quận 7, TP.HCM', '1992-03-25', 'female', 'customer');

-- Thêm Dịch vụ mẫu
INSERT INTO services (name, description, price, category, image, duration) VALUES
('Tẩy trắng răng Laser', 'Công nghệ làm trắng răng tiên tiến nhất hiện nay với tia Laser an toàn, hiệu quả tức thì', 2500000, 'cosmetic', 'https://via.placeholder.com/300x200', 60),
('Bọc răng sứ Titan', 'Bọc răng sứ cao cấp, bền đẹp lâu dài, màu sắc tự nhiên như răng thật', 4500000, 'restoration', 'https://via.placeholder.com/300x200', 90),
('Niềng răng Invisalign', 'Niềng răng trong suốt không đau, tháo lắp tiện lợi, hiệu quả cao', 65000000, 'orthodontics', 'https://via.placeholder.com/300x200', 120),
('Cấy ghép Implant', 'Trồng răng Implant công nghệ Hàn Quốc, vĩnh viễn như răng thật', 18000000, 'implant', 'https://via.placeholder.com/300x200', 120),
('Nhổ răng khôn', 'Nhổ răng khôn an toàn, không đau với công nghệ hiện đại', 1500000, 'surgery', 'https://via.placeholder.com/300x200', 45),
('Điều trị tủy răng', 'Lấy tủy, điều trị viêm tủy chuyên sâu với thiết bị hiện đại', 2000000, 'treatment', 'https://via.placeholder.com/300x200', 60),
('Trám răng thẩm mỹ', 'Trám răng bằng composite cao cấp, màu sắc tự nhiên', 300000, 'general', 'https://via.placeholder.com/300x200', 30),
('Lấy cao răng', 'Làm sạch cao răng, vệ sinh răng miệng định kỳ', 200000, 'general', 'https://via.placeholder.com/300x200', 30);

-- Thêm Bác sĩ mẫu
INSERT INTO doctors (name, specialty, experience, education, description, image, phone, email) VALUES
('BS. Nguyễn Thị Mai', 'Chỉnh nha', 12, 'Thạc sĩ - Đại học Y Dược TP.HCM', 'Chuyên gia hàng đầu về niềng răng và chỉnh nha thẩm mỹ', 'https://via.placeholder.com/200x200', '0901111111', 'mai.nguyen@nalidental.com'),
('BS. Trần Văn Hùng', 'Cấy ghép Implant', 15, 'Tiến sĩ - Đại học Y khoa Tokyo', 'Chuyên gia cấy ghép Implant với hơn 5000 ca thành công', 'https://via.placeholder.com/200x200', '0902222222', 'hung.tran@nalidental.com'),
('BS. Lê Thanh Tú', 'Răng sứ thẩm mỹ', 10, 'Thạc sĩ - Đại học Nha khoa Seoul', 'Chuyên gia về bọc răng sứ và phục hồi thẩm mỹ', 'https://via.placeholder.com/200x200', '0903333333', 'tu.le@nalidental.com'),
('BS. Phạm Minh Đức', 'Phẫu thuật hàm mặt', 8, 'Bác sĩ - Đại học Y Hà Nội', 'Chuyên về nhổ răng khôn và phẫu thuật nha khoa', 'https://via.placeholder.com/200x200', '0904444444', 'duc.pham@nalidental.com'),
('BS. Võ Thị Lan', 'Nha khoa tổng quát', 7, 'Bác sĩ - Đại học Y Dược Cần Thơ', 'Điều trị tổng quát, trám răng, lấy cao răng chuyên nghiệp', 'https://via.placeholder.com/200x200', '0905555555', 'lan.vo@nalidental.com');

-- Thêm Lịch hẹn mẫu
INSERT INTO appointments (patient_id, service_id, doctor_id, appointment_date, appointment_time, status, notes, total_price) VALUES
(2, 1, 1, '2025-12-28', '09:00:00', 'confirmed', 'Muốn tư vấn thêm về quy trình', 2500000),
(3, 3, 2, '2025-12-29', '14:00:00', 'pending', 'Đã từng niềng răng, muốn tư vấn lại', 65000000),
(4, 5, 4, '2025-12-30', '10:30:00', 'confirmed', 'Răng khôn mọc lệch, đau nhiều', 1500000),
(2, 7, 5, '2026-01-02', '15:00:00', 'pending', 'Răng bị sâu cần trám gấp', 300000);

-- =============================================
-- KIỂM TRA KẾT QUẢ
-- =============================================
SELECT 'Patients' AS 'Table', COUNT(*) AS 'Records' FROM patients
UNION ALL
SELECT 'Services', COUNT(*) FROM services
UNION ALL
SELECT 'Doctors', COUNT(*) FROM doctors
UNION ALL
SELECT 'Appointments', COUNT(*) FROM appointments;

-- =============================================
-- GHI CHÚ QUAN TRỌNG
-- =============================================
-- 1. Password mẫu (đã mã hóa): password123
-- 2. Admin account: admin@nalidental.com / password123
-- 3. Chạy file này trong phpMyAdmin hoặc MySQL command line
-- 4. Đảm bảo database 'nali_dental' đã được tạo trước
-- =============================================
