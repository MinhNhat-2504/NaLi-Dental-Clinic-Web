<?php
require_once 'includes/components.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Về Chúng Tôi - NALI Dental Clinic</title>
    <?php renderSeo('Về NALI Dental Clinic', 'Tìm hiểu định hướng, dịch vụ và trải nghiệm đặt lịch nha khoa trực tuyến của NALI Dental.'); ?>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="dental-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ========== ABOUT PAGE SPECIFIC STYLES ========== */
        
        /* Hero Section */
        .about-hero {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), 
                        url('images/hero-tech.webp') center/cover no-repeat;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 60px 20px;
        }
        
        .about-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .about-hero p {
            font-size: 1.2rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Content Sections */
        .about-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        .about-section {
            display: flex;
            align-items: center;
            gap: 50px;
            margin-bottom: 80px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        
        .about-section.reverse {
            flex-direction: row-reverse;
        }
        
        .about-img {
            flex: 1;
            min-width: 300px;
            height: 350px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .about-content {
            flex: 1;
        }
        
        .about-content h2 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .about-content h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 2px;
        }
        
        .about-content p {
            color: var(--text-light);
            font-size: 1.05rem;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        .about-content ul {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .about-content ul li {
            padding: 8px 0;
            color: var(--text-medium);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .about-content ul li i {
            color: var(--secondary);
            margin-top: 3px;
        }
        
        /* Features Grid */
        .features-section {
            background: linear-gradient(135deg, var(--primary-light) 0%, #f0f8ff 100%);
            padding: 80px 20px;
            margin-top: 40px;
        }
        
        .features-section h2 {
            text-align: center;
            font-size: 2.2rem;
            color: var(--text-dark);
            margin-bottom: 50px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1100px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: white;
            padding: 40px 30px;
            text-align: center;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(77, 166, 255, 0.2);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-light), #d4edff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        
        .feature-icon i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .feature-card h3 {
            color: var(--text-dark);
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .feature-card p {
            color: var(--text-light);
            line-height: 1.7;
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 60px 20px;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }
        
        .stat-item h3 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stat-item p {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .about-hero h1 {
                font-size: 2rem;
            }
            
            .about-section, .about-section.reverse {
                flex-direction: column;
                padding: 30px;
            }
            
            .about-img {
                min-width: 100%;
                height: 250px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .stat-item h3 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php renderHeader('about'); ?>

<!-- Hero Section -->
<section class="about-hero">
    <div>
        <h1>Câu Chuyện Của NALI</h1>
        <p>Tiên phong ứng dụng AI vào chăm sóc sức khỏe răng miệng</p>
    </div>
</section>

<!-- About Content -->
<div class="about-container">
    
    <section class="about-section slide-up">
        <img src="images/service-whitening-ai.webp" alt="Không gian tư vấn nha khoa NALI" class="about-img">
        <div class="about-content">
            <h2>Về NALI Dental Clinic</h2>
            <p>Được thành lập với sứ mệnh <strong>"Kiến tạo nụ cười hoàn mỹ"</strong>, NALI Dental Clinic không chỉ là một phòng khám nha khoa truyền thống. Chúng tôi là đơn vị tiên phong trong việc kết hợp y học hiện đại với công nghệ thông tin.</p>
            <p>Tại NALI, chúng tôi hiểu rằng thời gian của khách hàng là vàng bạc. Vì vậy, hệ thống đặt lịch và tư vấn tự động giúp quy trình thăm khám trở nên nhanh chóng, chính xác và thuận tiện hơn bao giờ hết.</p>
        </div>
    </section>

    <section class="about-section reverse slide-up">
        <img src="images/service-implant-ai.webp" alt="Tư vấn kế hoạch điều trị nha khoa" class="about-img">
        <div class="about-content">
            <h2>Sức Mạnh Của Trí Tuệ Nhân Tạo</h2>
            <p>Điểm khác biệt lớn nhất của NALI chính là <strong>Trợ lý ảo NALI AI</strong>, được tích hợp sâu vào hệ thống website với khả năng:</p>
            <ul>
                <li><i class="fas fa-check-circle"></i> Tư vấn sơ bộ về tình trạng răng miệng 24/7</li>
                <li><i class="fas fa-check-circle"></i> Giải đáp thắc mắc về chi phí và quy trình điều trị ngay lập tức</li>
                <li><i class="fas fa-check-circle"></i> Hỗ trợ đặt lịch hẹn tự động mà không cần chờ nhân viên</li>
                <li><i class="fas fa-check-circle"></i> Nhắc nhở lịch hẹn và theo dõi quá trình điều trị</li>
            </ul>
            <p>Đây là giải pháp chuyển đổi số toàn diện, giúp nâng cao trải nghiệm khách hàng trong kỷ nguyên 4.0.</p>
        </div>
    </section>

</div>

<!-- Stats Section -->
<section class="stats-section">
    <div class="stats-grid">
        <div class="stat-item">
            <h3><i class="fas fa-list-check"></i></h3>
            <p>Dịch vụ theo nhóm</p>
        </div>
        <div class="stat-item">
            <h3><i class="fas fa-calendar-check"></i></h3>
            <p>Đặt lịch trực tuyến</p>
        </div>
        <div class="stat-item">
            <h3><i class="fas fa-robot"></i></h3>
            <p>Trợ lý AI hỗ trợ</p>
        </div>
        <div class="stat-item">
            <h3><i class="fas fa-comments"></i></h3>
            <p>Tra cứu thông tin nhanh</p>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <h2>Tại Sao Chọn NALI?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <h3>Đội Ngũ Chuyên Gia</h3>
            <p>Người dùng có thể xem thông tin bác sĩ, lựa chọn nhu cầu và gửi yêu cầu đặt lịch trực tiếp trên hệ thống.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-microchip"></i>
            </div>
            <h3>Công Nghệ Tiên Tiến</h3>
            <p>Hệ thống máy chụp X-quang CT Cone Beam, công nghệ thiết kế nụ cười và Trợ lý ảo AI độc quyền.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h3>Tận Tâm & Y Đức</h3>
            <p>Chúng tôi coi khách hàng như người thân, luôn tư vấn giải pháp điều trị hiệu quả và tiết kiệm nhất.</p>
        </div>
    </div>
</section>

<?php renderFooter(); ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
