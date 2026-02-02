# 🦷 NALI Dental Clinic - Website Nha Khoa Công Nghệ Cao

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)

## 📋 Giới Thiệu

**NALI Dental Clinic** là hệ thống website quản lý phòng khám nha khoa hiện đại, tích hợp công nghệ AI. Website được thiết kế với giao diện thân thiện, tối ưu cho cả desktop và mobile, giúp khách hàng dễ dàng đặt lịch khám và tìm hiểu dịch vụ.

## ✨ Tính Năng Chính

### 👤 Dành cho Khách Hàng
- **Xem dịch vụ** - Danh sách dịch vụ theo 4 nhóm: Trẻ em, Người lớn, Người cao tuổi, Bệnh lý nền
- **Đặt lịch hẹn** - Form đặt lịch thông minh với chọn bác sĩ, dịch vụ, thời gian
- **Tìm hiểu đội ngũ** - Thông tin chi tiết các bác sĩ với chuyên khoa
- **Kiến thức nha khoa** - Bài viết, tin tức về chăm sóc răng miệng
- **Đăng ký/Đăng nhập** - Hệ thống xác thực người dùng

### 🔧 Dành cho Admin
- **Dashboard tổng quan** - Thống kê lịch hẹn, bệnh nhân, doanh thu
- **Quản lý lịch hẹn** - Thêm, sửa, xóa, lọc lịch hẹn theo ngày/trạng thái
- **Quản lý bệnh nhân** - Danh sách và thông tin khách hàng
- **Quản lý dịch vụ** - CRUD sản phẩm/dịch vụ
- **Quản lý bác sĩ** - Thêm, sửa thông tin bác sĩ

## 🗂️ Cấu Trúc Dự Án

```
nali/
├── 📁 api/                      # RESTful API endpoints
│   ├── appointments.php         # CRUD lịch hẹn
│   ├── patients.php             # CRUD bệnh nhân
│   ├── products.php             # CRUD dịch vụ
│   ├── doctors.php              # CRUD bác sĩ
│   └── dashboard.php            # Thống kê dashboard
│
├── 📁 includes/                 # Components tái sử dụng
│   └── components.php           # Header & Footer components
│
├── 📁 images/                   # Hình ảnh
│
├── 📄 Trang chính
│   ├── index.php                # Redirect → services.php
│   ├── services.php             # Trang dịch vụ (trang chủ)
│   ├── contact.php              # Đặt lịch hẹn
│   ├── team.php                 # Đội ngũ bác sĩ
│   ├── news.php                 # Kiến thức nha khoa
│   └── about.php                # Giới thiệu
│
├── 📄 Xác thực
│   ├── auth.php                 # Đăng nhập/Đăng ký khách hàng
│   ├── login.php                # Xử lý đăng nhập
│   ├── register.php             # Xử lý đăng ký
│   └── logout.php               # Đăng xuất
│
├── 📄 Admin Panel
│   ├── admin_panel.php          # Giao diện quản trị
│   └── admin_login.php          # Đăng nhập admin
│
├── 📄 CSS
│   ├── common.css               # CSS chung (Design System)
│   ├── style.css                # CSS bổ sung
│   └── animations.css           # Hiệu ứng animation
│
├── 📄 JavaScript
│   ├── script.js                # JS chính
│   ├── animations.js            # JS animation
│   └── header-user.js           # JS header
│
├── 📄 Config & Database
│   ├── config.php               # Cấu hình kết nối DB
│   ├── database_complete.sql    # SQL tạo database
│   └── nali_dental_complete.sql # SQL đầy đủ với data mẫu
│
└── 📄 README.md                 # File này
```

## 🗄️ Cơ Sở Dữ Liệu

### Các Bảng Chính

| Bảng | Mô tả |
|------|-------|
| `patients` | Thông tin bệnh nhân/khách hàng |
| `users` | Tài khoản admin |
| `appointments` | Lịch hẹn khám |
| `products` | Dịch vụ nha khoa |
| `doctors` | Thông tin bác sĩ |
| `services` | Danh mục dịch vụ |
| `categories` | Phân loại |

### Kết Nối Database

```php
// config.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nali_dental";
$port = 3306; // Hoặc 3307 tùy cấu hình XAMPP
```

## 🚀 Cài Đặt & Chạy

### Yêu Cầu
- XAMPP (Apache + MySQL)
- PHP 7.4+
- MySQL 5.7+

### Các Bước Cài Đặt

1. **Clone/Copy dự án vào thư mục XAMPP**
   ```bash
   cd C:\xampp\htdocs\
   # Copy folder nali vào đây
   ```

2. **Khởi động XAMPP**
   - Mở XAMPP Control Panel
   - Start **Apache** và **MySQL**

3. **Tạo Database**
   - Truy cập http://localhost/phpmyadmin
   - Tạo database mới: `nali_dental`
   - Import file `database_complete.sql` hoặc `nali_dental_complete.sql`

4. **Cấu hình kết nối** (nếu cần)
   - Mở `config.php`
   - Kiểm tra port MySQL (3306 hoặc 3307)

5. **Truy cập website**
   - Trang chính: http://localhost/nali/
   - Admin Panel: http://localhost/nali/admin_panel.php

### Tài Khoản Mặc Định

| Loại | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |

## 🎨 Design System

### CSS Variables (common.css)

```css
:root {
    --primary: #4da6ff;        /* Màu chính - xanh dương */
    --primary-dark: #3d8fe8;   /* Màu chính đậm */
    --primary-light: #e8f4ff;  /* Màu chính nhạt */
    --secondary: #28a745;      /* Màu phụ - xanh lá */
    --accent: #ff6b6b;         /* Màu nhấn - đỏ cam */
    --text-dark: #333;
    --text-light: #666;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 20px;
}
```

### Components Tái Sử Dụng

```php
// Sử dụng Header & Footer
require_once 'includes/components.php';

renderHeader('services');  // Tham số: tên trang hiện tại
renderFooter();
```

## 📱 Responsive Design

Website được tối ưu cho các thiết bị:

| Breakpoint | Thiết bị |
|------------|----------|
| > 992px | Desktop |
| 768px - 992px | Tablet |
| 375px - 768px | Mobile |
| < 375px | Mobile nhỏ (iPhone SE) |

### Tính Năng Mobile
- ✅ Menu hamburger với overlay
- ✅ Touch-friendly buttons (min 48px)
- ✅ Font-size 16px (ngăn zoom iOS)
- ✅ Smooth scrolling
- ✅ Touch feedback thay hover

## 🔌 API Endpoints

### Appointments API (`/api/appointments.php`)

| Method | Action | Mô tả |
|--------|--------|-------|
| GET | `?action=list` | Lấy danh sách lịch hẹn |
| GET | `?action=get&id=1` | Lấy chi tiết 1 lịch hẹn |
| POST | `action=add` | Thêm lịch hẹn mới |
| POST | `action=update` | Cập nhật lịch hẹn |
| POST | `action=delete` | Xóa lịch hẹn |

### Dashboard API (`/api/dashboard.php`)

```javascript
// Response example
{
    "today_appointments": 5,
    "total_patients": 120,
    "pending_appointments": 8,
    "monthly_revenue": 15000000
}
```

## 📝 Changelog

### Version 1.0.0 (2026-02-02)

#### Backend
- ✅ Cấu trúc API RESTful cho CRUD operations
- ✅ Admin Panel với quản lý lịch hẹn đầy đủ
- ✅ Hệ thống xác thực (đăng nhập/đăng ký)
- ✅ Database với các bảng patients, appointments, products, doctors

#### Frontend
- ✅ Design System với CSS Variables
- ✅ Components tái sử dụng (Header/Footer)
- ✅ Responsive hoàn chỉnh cho mobile
- ✅ Đồng bộ giao diện tất cả các trang

#### Các Trang
- ✅ **services.php** - Trang dịch vụ với filter theo nhóm
- ✅ **contact.php** - Form đặt lịch multi-step
- ✅ **team.php** - Danh sách bác sĩ với modal chi tiết
- ✅ **news.php** - Bài viết với tìm kiếm/lọc
- ✅ **about.php** - Giới thiệu phòng khám
- ✅ **auth.php** - Đăng nhập/Đăng ký khách hàng

## 📞 Thông Tin Liên Hệ

**NALI Dental Clinic**
- 📱 Hotline: **0945 457 512**
- 📧 Email: nalidental@gmail.com
- ⏰ Giờ làm việc: T2 - CN: 08:00 - 20:00

**Chi nhánh:**
- 🏥 Bình Thạnh: 69/68 Đặng Thùy Trâm
- 🏥 Quận 1: 123 Nguyễn Huệ
- 🏥 Gò Vấp: 456 Quang Trung

---

© 2026 NALI Dental Clinic. All rights reserved.
