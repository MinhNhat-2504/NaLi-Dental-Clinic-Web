-- =====================================================================
-- nali_dental_schema_REAL.sql
-- SCHEMA ĐÚNG khớp với CODE ĐANG CHẠY của web (api/*.php, admin_panel.php).
--
-- LƯU Ý QUAN TRỌNG:
--   * Hai file cũ database_complete.sql / nali_dental_complete.sql định nghĩa
--     bảng `appointments` và `services` theo kiểu KHÁC, KHÔNG khớp code thật.
--   * File này tái dựng đúng bộ cột mà code thật đọc/ghi (đặc biệt là bảng
--     `appointments` "phẳng" và bảng `products`).
--   * Dùng CREATE TABLE IF NOT EXISTS + KHÔNG có DROP -> an toàn, không xoá dữ liệu.
--
-- ➜ Để có bản backup CHÍNH XÁC TUYỆT ĐỐI của dữ liệu thật: chạy backup_db.bat
--   (khi MySQL đang bật). File này chỉ là tham chiếu cấu trúc.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS nali_dental
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nali_dental;

-- ---------- Khách hàng (đăng nhập phía user) ----------
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    gender ENUM('Nam','Nữ','Khác') DEFAULT 'Khác',
    birthday DATE NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Nhân sự (admin / bác sĩ / lễ tân) ----------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','doctor','receptionist') NOT NULL DEFAULT 'doctor',
    avatar VARCHAR(255) NULL,
    phone VARCHAR(15) NULL,
    specialty VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Dịch vụ (code dùng bảng `products`, KHÔNG phải `services`) ----------
-- Cột khớp api/products.php: name, description, price, image, target_group, duration, is_active
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(12,2) DEFAULT 0,
    image VARCHAR(255) NULL,
    target_group VARCHAR(50) DEFAULT 'adults' COMMENT 'children/adults/elderly/...',
    duration INT DEFAULT 30 COMMENT 'Thời gian (phút)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target (target_group),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Lịch hẹn (schema "PHẲNG" mà app thật đang dùng) ----------
-- Cột khớp api/appointments.php + book_appointment.php + admin_panel.php.
-- Ngày và giờ TÁCH RIÊNG (DATE + TIME), lưu thông tin khách trực tiếp.
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'patients.id nếu khách đã đăng nhập, NULL nếu khách vãng lai',
    product_ids VARCHAR(255) NULL COMMENT 'Danh sách id dịch vụ, phân tách bằng dấu phẩy',
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(15) NOT NULL,
    customer_email VARCHAR(100) NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    notes TEXT NULL,
    admin_notes TEXT NULL,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    payment_method ENUM('cash','transfer','card') DEFAULT 'cash',
    discount_code VARCHAR(50) NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (appointment_date),
    INDEX idx_status (status),
    INDEX idx_phone (customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- GỢI Ý: nếu bảng products đang trống, thêm vài dịch vụ mẫu để test AI/RAG:
-- =====================================================================
-- INSERT INTO products (name, description, price, target_group, duration) VALUES
-- ('Tẩy trắng răng Laser','Làm trắng răng bằng tia Laser an toàn',2500000,'adults',60),
-- ('Cấy ghép Implant','Trồng răng Implant công nghệ Hàn Quốc',18000000,'adults',120),
-- ('Nhổ răng khôn','Nhổ răng khôn an toàn không đau',1500000,'adults',45),
-- ('Lấy cao răng','Làm sạch cao răng, vệ sinh răng miệng',200000,'adults',30);
