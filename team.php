<?php
require_once 'includes/components.php';
require_once 'config.php';
require_once 'content_repository.php';
ensureContentSchema($conn);

// Chỉ công khai nhân sự có trong CSDL; không xuất số điện thoại cá nhân.
$doctorResult = $conn->query("SELECT u.id, u.full_name, u.specialty, p.slug, p.is_published, p.introduction, p.photo FROM users u LEFT JOIN doctor_profiles p ON p.user_id=u.id WHERE u.role = 'doctor' ORDER BY u.id ASC");
$publicDoctors = $doctorResult ? $doctorResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đội Ngũ Bác Sĩ - NALI Dental Clinic</title>
    <?php renderSeo('Đội ngũ bác sĩ - NALI Dental Clinic', 'Xem thông tin đội ngũ bác sĩ và gửi yêu cầu đặt lịch tư vấn tại NALI Dental.'); ?>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="dental-theme.css">
    <link rel="stylesheet" href="animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ========== CSS RIÊNG CHO TRANG BÁC SĨ ========== */
        body { background-color: #f8f9fa; }

        .team-header {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 70, 147, 0.8)), url('images/dental-bg.webp');
            background-size: cover;
            background-position: center;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            margin-bottom: 50px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 50px 20px;
        }
        
        /* Bộ lọc chuyên khoa */
        .specialty-filter {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
            text-align: center;
        }
        
        .specialty-filter h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .filter-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 12px 30px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 25px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            border-color: #4da6ff;
            color: #4da6ff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 166, 255, 0.2);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            color: white;
            border-color: #4da6ff;
        }
        
        .filter-btn i {
            font-size: 18px;
        }

        /* Lưới bác sĩ */
        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .doctor-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            position: relative;
            cursor: pointer;
        }

        .doctor-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0,123,255,0.25);
        }
        
        /* Clickable indicator */
        .view-detail-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.95);
            color: #4da6ff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 5;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        
        .doctor-card:hover .view-detail-badge {
            background: #4da6ff;
            color: white;
            transform: scale(1.1);
        }
        
        /* Modal chi tiết bác sĩ */
        .doctor-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            max-width: 900px;
            margin: 50px auto;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
            font-size: 20px;
            color: #666;
        }
        
        .modal-close:hover {
            background: #ff4757;
            color: white;
            transform: rotate(90deg);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            padding: 40px;
            color: white;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .modal-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header-info h2 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        
        .modal-header-info .role {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .modal-body {
            padding: 40px;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            color: #4da6ff;
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-section ul {
            list-style: none;
            padding: 0;
        }
        
        .info-section li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .info-section li:last-child {
            border-bottom: none;
        }
        
        .info-section li i {
            color: #4da6ff;
            margin-top: 3px;
            font-size: 14px;
        }
        
        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .achievement-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #4da6ff;
        }
        
        .achievement-card h4 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 1rem;
        }
        
        .achievement-card p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.5;
        }
        
        .modal-footer {
            padding: 30px 40px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .contact-info {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .contact-info i {
            color: #4da6ff;
        }
        
        /* Overlay xuất hiện khi hover */
        .doctor-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(77, 166, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
            z-index: 10;
            padding: 30px;
            text-align: center;
        }
        
        .doctor-card:hover .doctor-overlay {
            opacity: 1;
            visibility: visible;
        }
        
        .overlay-content {
            transform: translateY(20px);
            transition: transform 0.4s ease;
        }
        
        .doctor-card:hover .overlay-content {
            transform: translateY(0);
        }
        
        .overlay-icon {
            font-size: 60px;
            color: white;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .overlay-text {
            color: white;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 25px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-book-doctor {
            display: inline-block;
            padding: 15px 40px;
            background: white;
            color: #4da6ff;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-book-doctor:hover {
            background: #f8f9fa;
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .btn-book-doctor i {
            margin-right: 8px;
        }

        /* Ảnh bác sĩ */
        .doctor-img-box {
            height: 350px;
            overflow: hidden;
            position: relative;
        }
        
        .doctor-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .doctor-card:hover .doctor-img {
            transform: scale(1.05);
        }

        /* Thông tin bác sĩ */
        .doctor-info {
            padding: 25px;
        }

        .doctor-name {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 5px;
        }

        .doctor-role {
            color: #4da6ff;
            font-weight: bold;
            font-size: 0.95rem;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doctor-desc {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .social-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
        }

        .social-btn:hover {
            background-color: #4da6ff;
            color: white;
        }

        /* Nút đặt lịch riêng cho từng bác sĩ */
        .btn-book-doc {
            display: block;
            width: 80%;
            margin: 20px auto 0;
            padding: 10px;
            border: 1px solid #4da6ff;
            color: #4da6ff;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-book-doc:hover {
            background-color: #4da6ff;
            color: white;
        }

        /* Responsive - Mobile Optimized */
        @media (max-width: 992px) {
            .doctor-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .team-header {
                height: 180px;
                margin-bottom: 30px;
            }
            
            .team-header h1 {
                font-size: 1.6rem;
            }
            
            .team-header p {
                font-size: 0.95rem;
            }
            
            .container {
                padding: 0 16px 40px;
            }
            
            .specialty-filter {
                padding: 20px 15px;
                margin-bottom: 30px;
            }
            
            .specialty-filter h3 {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }
            
            .filter-buttons {
                gap: 10px;
            }
            
            .filter-btn {
                padding: 12px 16px;
                font-size: 14px;
                flex: 1;
                min-width: calc(50% - 10px);
                justify-content: center;
            }
            
            .doctor-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .doctor-card {
                border-radius: 15px;
            }
            
            .doctor-img-wrapper {
                height: 250px;
            }
            
            .doctor-info {
                padding: 20px;
            }
            
            .doctor-info h3 {
                font-size: 1.2rem;
            }
            
            .btn-book-doc {
                padding: 12px 20px;
                font-size: 0.95rem;
                min-height: 48px;
            }
            
            .btn-book-doc:active {
                background-color: #4da6ff;
                color: white;
                transform: scale(0.98);
            }
        }
        
        @media (max-width: 375px) {
            .team-header h1 {
                font-size: 1.4rem;
            }
            
            .filter-btn {
                font-size: 13px;
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body>

<?php renderHeader('team'); ?>

    <div class="team-header">
        <div>
            <h1>Chuyên Gia Của Chúng Tôi</h1>
            <p>Đội ngũ bác sĩ đầu ngành - Tận tâm - Y đức</p>
        </div>
    </div>

    <div class="container">
        <!-- Bộ lọc chuyên khoa -->
        <div class="specialty-filter" hidden>
            <h3><i class="fas fa-filter"></i> Lọc theo Đối tượng khách hàng</h3>
            <div class="filter-buttons">
                <button class="filter-btn active" data-specialty="all" onclick="filterBySpecialty('all')">
                    <i class="fas fa-th"></i> Tất cả
                </button>
                <button class="filter-btn" data-specialty="trẻ em" onclick="filterBySpecialty('trẻ em')">
                    <i class="fas fa-child"></i> Trẻ em (0-15 tuổi)
                </button>
                <button class="filter-btn" data-specialty="người lớn" onclick="filterBySpecialty('người lớn')">
                    <i class="fas fa-user"></i> Người trưởng thành (16-59)
                </button>
                <button class="filter-btn" data-specialty="người cao tuổi" onclick="filterBySpecialty('người cao tuổi')">
                    <i class="fas fa-user-friends"></i> Người cao tuổi (60+)
                </button>
                <button class="filter-btn" data-specialty="bệnh lý nền" onclick="filterBySpecialty('bệnh lý nền')">
                    <i class="fas fa-heartbeat"></i> Bệnh lý nền
                </button>
            </div>
        </div>

        <!-- Bọc lưới bác sĩ trong box trắng căn giữa -->
        <div class="team-content-box">
            <div class="doctor-grid actual-doctor-grid">
                <?php if ($publicDoctors): ?>
                    <?php foreach ($publicDoctors as $index => $doctor): ?>
                        <?php $photo = $doctor['photo'] ?: ($index % 2 === 0 ? 'images/doctor-male-elderly.webp' : 'images/doctor-female-pediatric.webp'); ?>
                        <article class="doctor-card reveal hover-lift">
                            <div class="doctor-img-box">
                                <img src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh minh hoạ chuyên môn - <?= htmlspecialchars($doctor['full_name'], ENT_QUOTES, 'UTF-8') ?>" class="doctor-img" loading="lazy">
                            </div>
                            <div class="doctor-info">
                                <p class="doctor-role"><?= htmlspecialchars($doctor['specialty'] ?: 'Bác sĩ nha khoa', ENT_QUOTES, 'UTF-8') ?></p>
                                <h3 class="doctor-name"><?= htmlspecialchars($doctor['full_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="doctor-desc">Thông tin chuyên môn được cập nhật từ hệ thống NALI. Vui lòng đặt lịch để được tư vấn phù hợp.</p>
                                <p class="doctor-note">Ảnh minh hoạ chuyên môn.</p>
                                <?php if (!empty($doctor['is_published']) && !empty($doctor['slug'])): ?>
                                    <a class="btn-primary" href="doctor.php?slug=<?= urlencode($doctor['slug']) ?>">Xem hồ sơ</a>
                                <?php else: ?>
                                    <a class="btn-primary" href="contact.php">Đặt lịch với bác sĩ</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-state">Đội ngũ bác sĩ đang được cập nhật. Vui lòng liên hệ hotline chung để đặt lịch.</p>
                <?php endif; ?>
            </div>
            <div class="doctor-grid stagger-container legacy-doctor-grid" aria-hidden="true">

            <div class="doctor-card reveal hover-lift tilt-effect" 
                 data-doctor="TS. BS. Trần Minh Nhật" 
                 data-specialty="bệnh lý nền"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-male-elderly.webp" alt="TS. BS. Trần Minh Nhật - Giám Đốc Chuyên Môn" class="doctor-img">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">TS. BS. Trần Minh Nhật</h3>
                    <p class="doctor-role">Giám Đốc Chuyên Môn</p>
                    <p class="doctor-desc">
                        Hơn 15 năm kinh nghiệm trong lĩnh vực Phục hình răng sứ và Cấy ghép Implant. Tốt nghiệp Thủ khoa Đại học Y Dược TP.HCM.
                    </p>
                </div>
                <!-- Overlay hiện khi hover -->
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>TS. BS. Trần Minh Nhật</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('TS. BS. Trần Minh Nhật')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <div class="doctor-card reveal hover-lift tilt-effect" 
                 data-doctor="ThS. BS. Nguyễn Thị Lan" 
                 data-specialty="người lớn"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-female-pediatric.webp" alt="ThS. BS. Nguyễn Thị Lan - Chuyên Gia Chỉnh Nha" class="doctor-img">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">ThS. BS. Nguyễn Thị Lan</h3>
                    <p class="doctor-role">Chuyên Gia Chỉnh Nha (Niềng Răng)</p>
                    <p class="doctor-desc">
                        Chứng chỉ chỉnh nha Invisalign (Mỹ). Đã thực hiện thành công hơn 2.000 ca niềng răng trong suốt và mắc cài phức tạp.
                    </p>
                </div>
                <!-- Overlay hiện khi hover -->
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>ThS. BS. Nguyễn Thị Lan</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('ThS. BS. Nguyễn Thị Lan')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <div class="doctor-card reveal hover-lift tilt-effect" 
                 data-doctor="BS. Lê Văn Hùng" 
                 data-specialty="người lớn"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-male-elderly.webp" alt="BS. Lê Văn Hùng - Nha Khoa Thẩm Mỹ & Tiểu Phẫu" class="doctor-img">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">BS. Lê Văn Hùng</h3>
                    <p class="doctor-role">Nha Khoa Thẩm Mỹ & Tiểu Phẫu</p>
                    <p class="doctor-desc">
                        Chuyên gia về thiết kế nụ cười (Smile Design) và xử lý các ca nhổ răng khôn không đau. Tận tâm, nhẹ nhàng với khách hàng.
                    </p>
                </div>
                <!-- Overlay hiện khi hover -->
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>BS. Lê Văn Hùng</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('BS. Lê Văn Hùng')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <div class="doctor-card reveal hover-lift tilt-effect" 
                 data-doctor="BS. Phạm Thị Mai" 
                 data-specialty="bệnh lý nền"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-female-pediatric.webp" alt="BS. Phạm Thị Mai - Nha Khoa Nội Nha" class="doctor-img">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">BS. Phạm Thị Mai</h3>
                    <p class="doctor-role">Nha Khoa Nội Nha (Nội Khoa)</p>
                    <p class="doctor-desc">
                        Chuyên sâu về điều trị tủy răng và bảo tồn răng thật. Có hơn 10 năm kinh nghiệm xử lý các ca nội nha phức tạp.
                    </p>
                </div>
                <!-- Overlay hiện khi hover -->
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>BS. Phạm Thị Mai</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('BS. Phạm Thị Mai')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <div class="doctor-card reveal hover-lift tilt-effect"
                 data-doctor="BS. CK1. Hoàng Minh Tuấn"
                 data-specialty="trẻ em"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-male-elderly.webp" alt="BS. CK1. Hoàng Minh Tuấn - Nha Khoa Trẻ Em" class="doctor-img">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">BS. CK1. Hoàng Minh Tuấn</h3>
                    <p class="doctor-role">Nha Khoa Trẻ Em</p>
                    <p class="doctor-desc">
                        Chuyên gia về nha khoa trẻ em với kỹ năng giao tiếp tốt, giúp các bé thoải mái và không sợ khám răng.
                    </p>
                </div>
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>BS. CK1. Hoàng Minh Tuấn</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('BS. CK1. Hoàng Minh Tuấn')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <div class="doctor-card reveal hover-lift tilt-effect"
                 data-doctor="ThS. BS. Vũ Thu Hằng"
                 data-specialty="người cao tuổi"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-female-pediatric.webp" alt="ThS. BS. Vũ Thu Hằng - Nha Chu & Nha Công Cộng" class="doctor-img">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">ThS. BS. Vũ Thu Hằng</h3>
                    <p class="doctor-role">Nha Chu & Nha Công Cộng</p>
                    <p class="doctor-desc">
                        Chuyên điều trị các bệnh lý nha chu, viêm lợi, tiêu xương hàm. Tư vấn chăm sóc sức khỏe răng miệng toàn diện.
                    </p>
                </div>
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>ThS. BS. Vũ Thu Hằng</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('ThS. BS. Vũ Thu Hằng')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bác sĩ mới 1: Nữ - Trẻ em -->
            <div class="doctor-card reveal hover-lift tilt-effect"
                 data-doctor="BS. Nguyễn Thị Hương"
                 data-specialty="trẻ em"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-female-pediatric.webp" alt="BS. Nguyễn Thị Hương - Nha Khoa Trẻ Em" class="doctor-img" onerror="this.onerror=null;this.src='images/logo.png'">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">BS. Nguyễn Thị Hương</h3>
                    <p class="doctor-role">Nha Khoa Trẻ Em</p>
                    <p class="doctor-desc">
                        7+ năm kinh nghiệm chăm sóc răng miệng cho trẻ, chuyên trị liệu phòng ngừa sâu răng và chỉnh nha sớm.
                    </p>
                </div>
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>BS. Nguyễn Thị Hương</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('BS. Nguyễn Thị Hương')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bác sĩ mới 2: Nam - Người cao tuổi -->
            <div class="doctor-card reveal hover-lift tilt-effect"
                 data-doctor="PGS.TS. BS. Đỗ Văn Minh"
                 data-specialty="người cao tuổi"
                 onclick="showDoctorDetail(this)">
                <span class="view-detail-badge">
                    <i class="fas fa-info-circle"></i> Xem chi tiết
                </span>
                <div class="doctor-img-box">
                    <img src="images/doctor-male-elderly.webp" alt="PGS.TS. BS. Đỗ Văn Minh - Chuyên gia Người cao tuổi" class="doctor-img" onerror="this.onerror=null;this.src='images/logo.png'">
                </div>
                <div class="doctor-info">
                    <h3 class="doctor-name">PGS.TS. BS. Đỗ Văn Minh</h3>
                    <p class="doctor-role">Chuyên gia Người cao tuổi & Bệnh lý nền</p>
                    <p class="doctor-desc">
                        Tập trung tư vấn chăm sóc răng miệng cho người cao tuổi, phục hình toàn hàm và nhu cầu có bệnh lý nền.
                    </p>
                </div>
                <div class="doctor-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-calendar-check overlay-icon"></i>
                        <p class="overlay-text">Đặt lịch hẹn với<br><strong>PGS.TS. BS. Đỗ Văn Minh</strong></p>
                        <button class="btn-book-doctor" onclick="bookWithDoctor('PGS.TS. BS. Đỗ Văn Minh')">
                            <i class="fas fa-user-md"></i> Đặt Lịch Ngay
                        </button>
                    </div>
                </div>
            </div>

            </div>
        </div>
    </div>

    
    <!-- Modal chi tiết bác sĩ -->
    <div class="doctor-modal" id="doctorModal">
        <div class="modal-content">
            <div class="modal-close" onclick="closeDoctorModal()">
                <i class="fas fa-times"></i>
            </div>
            <div class="modal-header">
                <img id="modalAvatar" src="" alt="" class="modal-avatar">
                <div class="modal-header-info">
                    <h2 id="modalName"></h2>
                    <p class="role" id="modalRole"></p>
                </div>
            </div>
            <div class="modal-body">
                <div class="info-section">
                    <h3><i class="fas fa-user-graduate"></i> Học vấn & Chứng chỉ</h3>
                    <ul id="modalEducation"></ul>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-briefcase"></i> Kinh nghiệm làm việc</h3>
                    <ul id="modalExperience"></ul>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-trophy"></i> Công trình nghiên cứu & Thành tựu</h3>
                    <div class="achievement-grid" id="modalAchievements"></div>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-check-circle"></i> Ca điều trị tiêu biểu</h3>
                    <ul id="modalCases"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <div class="contact-info">
                    <span><i class="fas fa-phone"></i> 0945 457 512</span>
                    <span><i class="fas fa-envelope"></i> nalidental@gmail.com</span>
                </div>
                <button class="btn-book-doctor" onclick="bookFromModal()">
                    <i class="fas fa-calendar-check"></i> Đặt lịch với bác sĩ này
                </button>
            </div>
        </div>
    </div>

    <?php renderFooter(); ?>

    <script>
    // ========== DOCTOR DETAIL DATABASE ==========
    const doctorDetails = {
        "TS. BS. Trần Minh Nhật": {
            avatar: "images/doctor-male-elderly.webp",
            name: "TS. BS. Trần Minh Nhật",
            role: "Giám Đốc Chuyên Môn - Chuyên gia Phục hình & Implant",
            education: [
                "Tiến sĩ Nha khoa - Đại học Y Dược TP.HCM (2010)",
                "Thạc sĩ Phục hình răng sứ - Đại học Seoul, Hàn Quốc (2008)",
                "Bằng tốt nghiệp Thủ khoa - Đại học Y Dược TP.HCM (2005)",
                "Chứng chỉ Implant cao cấp - Viện Straumann, Thụy Sĩ (2012)"
            ],
            experience: [
                "Giám đốc chuyên môn NALI Dental Clinic (2018 - nay)",
                "Phó Giám đốc Y khoa - Nha khoa Quốc tế Diamond (2015-2018)",
                "Bác sĩ điều trị - Bệnh viện Răng Hàm Mặt TP.HCM (2005-2015)",
                "Giảng viên thỉnh giảng - Khoa Răng Hàm Mặt ĐHYD (2010-2018)"
            ],
            achievements: [
                {
                    title: "Công trình nghiên cứu: Ứng dụng AI trong thiết kế Implant",
                    desc: "Đăng trên tạp chí Journal of Dental Research (2023). Tỷ lệ thành công 98.5%."
                },
                {
                    title: "Giải thưởng: Bác sĩ xuất sắc nhất năm 2022",
                    desc: "Hiệp hội Nha khoa Việt Nam vinh danh vì đóng góp trong lĩnh vực phục hình."
                },
                {
                    title: "Chuyên gia đào tạo: 150+ bác sĩ được đào tạo",
                    desc: "Tổ chức 25 khóa đào tạo Implant & CAD/CAM cho bác sĩ trên toàn quốc."
                },
                {
                    title: "Nghiên cứu: All-on-4 cho bệnh nhân tiêu xương",
                    desc: "Phát triển quy trình cải tiến giúp giảm 30% thời gian điều trị."
                }
            ],
            cases: [
                "Phục hồi toàn hàm All-on-6 cho bệnh nhân mất răng hoàn toàn (250+ ca)",
                "Cấy ghép 8 Implant đồng thời kết hợp ghép xương (180+ ca)",
                "Thiết kế nụ cười thẩm mỹ bằng Veneer Emax (500+ ca)",
                "Phục hình răng sứ Zirconia cho răng cửa (1,200+ ca)"
            ]
        },
        "ThS. BS. Nguyễn Thị Lan": {
            avatar: "images/doctor-female-pediatric.webp",
            name: "ThS. BS. Nguyễn Thị Lan",
            role: "Chuyên gia Chỉnh nha - Invisalign Provider",
            education: [
                "Thạc sĩ Chỉnh nha - Đại học Y Dược TP.HCM (2015)",
                "Chứng chỉ Invisalign Diamond Provider - Align Technology, Mỹ (2018)",
                "Bằng tốt nghiệp Bác sĩ Răng Hàm Mặt - ĐH Y Dược TP.HCM (2012)",
                "Khóa đào tạo Damon System - Ormco, Mỹ (2016)"
            ],
            experience: [
                "Trưởng khoa Chỉnh nha - NALI Dental Clinic (2020 - nay)",
                "Bác sĩ chỉnh nha - Nha khoa Pháp Việt (2016-2020)",
                "Resident - Khoa Chỉnh nha BV RHM TP.HCM (2012-2016)"
            ],
            achievements: [
                {
                    title: "Top 10 Invisalign Provider tại Việt Nam",
                    desc: "Năm 2023-2024, được Align Technology công nhận vì số lượng ca điều trị xuất sắc."
                },
                {
                    title: "Chuyên gia tư vấn: Hội nghị Chỉnh nha Đông Nam Á",
                    desc: "Diễn giả tại AAO Meeting 2023 về chủ đề 'Digital Orthodontics'."
                },
                {
                    title: "Nghiên cứu: Chỉnh nha cho người trưởng thành",
                    desc: "Bài báo đăng trên Vietnam Journal of Dentistry (2022)."
                },
                {
                    title: "2,000+ ca niềng răng thành công",
                    desc: "Tỷ lệ hài lòng 99.2%. Chuyên xử lý ca móm, hô, răng khấp khểnh phức tạp."
                }
            ],
            cases: [
                "Chỉnh nha Invisalign cho ca hô vẩu 8mm không cần nhổ răng (350+ ca)",
                "Niềng răng móm kết hợp phẫu thuật hàm (45+ ca)",
                "Chỉnh nha trẻ em 8-12 tuổi bằng MRC/Trainer (280+ ca)",
                "Niềng răng tái phát sau khi tháo mắc cài (120+ ca)"
            ]
        },
        "BS. Lê Văn Hùng": {
            avatar: "images/doctor-male-elderly.webp",
            name: "BS. Lê Văn Hùng",
            role: "Chuyên gia Nha khoa Thẩm mỹ & Tiểu phẫu",
            education: [
                "Bằng Bác sĩ Răng Hàm Mặt - ĐH Y Dược Huế (2010)",
                "Chứng chỉ Smile Design - New York University (2016)",
                "Chứng chỉ Nhổ răng khôn an toàn - Seoul Dental Hospital (2014)",
                "Khóa đào tạo Veneer Emax - Ivoclar Vivadent (2017)"
            ],
            experience: [
                "Trưởng khoa Thẩm mỹ - NALI Dental Clinic (2019 - nay)",
                "Bác sĩ điều trị - Nha khoa Paris (2014-2019)",
                "Bác sĩ nội trú - Bệnh viện TW Huế (2010-2014)"
            ],
            achievements: [
                {
                    title: "Chuyên gia thiết kế nụ cười Golden Ratio",
                    desc: "Ứng dụng tỷ lệ vàng trong thiết kế veneer và răng sứ thẩm mỹ."
                },
                {
                    title: "Kỹ thuật nhổ răng khôn không đau",
                    desc: "Phát triển quy trình nhổ răng an toàn với thời gian hồi phục nhanh 50%."
                },
                {
                    title: "800+ ca Veneer Emax thẩm mỹ",
                    desc: "Chuyên xử lý răng ố vàng, mẻ, thưa, lệch lạc bằng veneer siêu mỏng."
                },
                {
                    title: "Diễn giả hội thảo: Digital Smile Design",
                    desc: "Chia sẻ kinh nghiệm tại Hội nghị Nha khoa thẩm mỹ Việt Nam 2023."
                }
            ],
            cases: [
                "Bọc răng sứ Emax cho 4 răng cửa bị ố vàng nặng (450+ ca)",
                "Nhổ 4 răng khôn mọc lệch, mọc ngầm trong 1 buổi (600+ ca)",
                "Veneer siêu mỏng không mài răng (Lumineers) (320+ ca)",
                "Tiểu phẫu nướu cắt lợi tạo đường cười đẹp (280+ ca)"
            ]
        },
        "BS. Phạm Thị Mai": {
            avatar: "images/doctor-female-pediatric.webp",
            name: "BS. Phạm Thị Mai",
            role: "Chuyên gia Nội nha - Điều trị tủy răng",
            education: [
                "Bằng Bác sĩ Răng Hàm Mặt - ĐH Y Dược TP.HCM (2012)",
                "Chứng chỉ Nội nha hiển vi - Đại học Tokyo (2016)",
                "Khóa đào tạo Root Canal Treatment - Dentsply Sirona (2015)",
                "Chứng chỉ Xử lý tái điều trị tủy - Bangkok Dental Center (2018)"
            ],
            experience: [
                "Trưởng khoa Nội nha - NALI Dental Clinic (2020 - nay)",
                "Bác sĩ điều trị - Nha khoa Đức (2016-2020)",
                "Resident - BV RHM TP.HCM (2012-2016)"
            ],
            achievements: [
                {
                    title: "Chuyên gia điều trị tủy răng phức tạp",
                    desc: "Tỷ lệ thành công 97% cho các ca răng cong, tủy vôi hóa, răng đã điều trị tủy."
                },
                {
                    title: "Kỹ thuật nội nha hiển vi Micro-Endo",
                    desc: "Sử dụng kính hiển vi phóng đại 20x giúp tìm đường tủy chính xác 100%."
                },
                {
                    title: "1,500+ ca điều trị tủy thành công",
                    desc: "Bảo tồn răng thật tối đa, tránh nhổ răng và cấy implant tốn kém."
                },
                {
                    title: "Giảng viên đào tạo: Kỹ thuật nội nha hiện đại",
                    desc: "Hướng dẫn 50+ bác sĩ trẻ về quy trình điều trị tủy chuẩn quốc tế."
                }
            ],
            cases: [
                "Điều trị tủy răng cửa bị chấn thương đen đục (280+ ca)",
                "Xử lý răng hàm 4 ống tủy phức tạp (450+ ca)",
                "Tái điều trị tủy răng đã điều trị thất bại (320+ ca)",
                "Điều trị tủy cho răng bị vôi hóa, cong (180+ ca)"
            ]
        }
    };
    
    // ========== SHOW DOCTOR DETAIL MODAL ==========
    function showDoctorDetail(element) {
        // Ngăn overlay trigger book appointment
        event.stopPropagation();
        
        const doctorName = element.getAttribute('data-doctor');
        const doctor = doctorDetails[doctorName];
        
        if (!doctor) return;
        
        // Fill modal content
        document.getElementById('modalAvatar').src = doctor.avatar;
        document.getElementById('modalAvatar').alt = doctor.name;
        document.getElementById('modalName').textContent = doctor.name;
        document.getElementById('modalRole').textContent = doctor.role;
        
        // Education
        const educationHTML = doctor.education.map(item => 
            `<li><i class="fas fa-graduation-cap"></i> <span>${item}</span></li>`
        ).join('');
        document.getElementById('modalEducation').innerHTML = educationHTML;
        
        // Experience
        const experienceHTML = doctor.experience.map(item => 
            `<li><i class="fas fa-briefcase"></i> <span>${item}</span></li>`
        ).join('');
        document.getElementById('modalExperience').innerHTML = experienceHTML;
        
        // Achievements
        const achievementsHTML = doctor.achievements.map(item => 
            `<div class="achievement-card">
                <h4>${item.title}</h4>
                <p>${item.desc}</p>
            </div>`
        ).join('');
        document.getElementById('modalAchievements').innerHTML = achievementsHTML;
        
        // Cases
        const casesHTML = doctor.cases.map(item => 
            `<li><i class="fas fa-check-circle"></i> <span>${item}</span></li>`
        ).join('');
        document.getElementById('modalCases').innerHTML = casesHTML;
        
        // Show modal
        document.getElementById('doctorModal').style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent background scroll
        
        // Store current doctor for booking
        window.currentDoctorModal = doctorName;
    }
    
    function closeDoctorModal() {
        document.getElementById('doctorModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function bookFromModal() {
        if (window.currentDoctorModal) {
            bookWithDoctor(window.currentDoctorModal);
        }
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('doctorModal');
        if (e.target === modal) {
            closeDoctorModal();
        }
    });
    
    // ========== FILTER BY SPECIALTY ==========
    let currentSpecialty = 'all';
    
    function filterBySpecialty(specialty) {
        currentSpecialty = specialty;
        
        // Update active button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // Filter doctor cards
        const doctorCards = document.querySelectorAll('.doctor-card');
        let visibleCount = 0;
        
        doctorCards.forEach(card => {
            const cardSpecialty = card.getAttribute('data-specialty');
            
            if (specialty === 'all' || cardSpecialty === specialty) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Smooth scroll to doctor grid
        document.querySelector('.doctor-grid').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
    
    // ========== BOOKING WITH DOCTOR FUNCTIONALITY ==========
    
    function bookWithDoctor(doctorName) {
        // Lưu tên bác sĩ vào localStorage
        localStorage.setItem('selectedDoctor', doctorName);
        
        // Chuyển hướng đến trang đặt lịch
        window.location.href = 'contact.php?doctor=' + encodeURIComponent(doctorName);
    }
    
    // ========== AUTO OPEN DOCTOR MODAL FROM URL PARAMETER ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Check if URL has doctor parameter
        const urlParams = new URLSearchParams(window.location.search);
        const doctorName = urlParams.get('doctor');
        
        if (doctorName && doctorDetails[doctorName]) {
            // Find the doctor card element
            const doctorCard = document.querySelector(`[data-doctor="${doctorName}"]`);
            
            if (doctorCard) {
                // Wait a bit for page to fully load, then show modal
                setTimeout(() => {
                    showDoctorDetail(doctorCard);
                    // Scroll to doctor grid area
                    document.querySelector('.doctor-grid').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }, 300);
            }
        }
    });
    </script>
    
    <script>
    // Mark active navigation link based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        const navLinks = document.querySelectorAll('nav a.nav-btn');
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            // So sánh tên file hoặc phần đầu của href với trang hiện tại
            if (href && (href.split('?')[0].split('#')[0] === currentPage || (currentPage === '' && href === 'index.php'))) {
                link.classList.add('active');
            }
        });
    });
    </script>
        <script src="header-user.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        // SweetAlert2 logout handler for Đội ngũ Bác sĩ
        document.addEventListener('DOMContentLoaded', function() {
            var logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Bạn chắc chắn muốn đăng xuất?',
                        text: 'Hẹn gặp lại bạn lần sau nhé!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Vâng, đăng xuất!',
                        cancelButtonText: 'Huỷ bỏ'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('logout.php')
                              .then(() => { window.location.href = 'index.php'; });
                        }
                    });
                });
            }
        });
        </script>

        <script src="animations.js"></script>
</body>
</html>
