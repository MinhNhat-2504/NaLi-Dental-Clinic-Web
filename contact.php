<?php
session_start();


// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['auth']) && $_SESSION['auth'] === true;
$userName = $isLoggedIn ? ($_SESSION['auth_user']['name'] ?? '') : '';
// Lấy email cho cả user (khach_hang) và admin (users)
if ($isLoggedIn) {
    if (isset($_SESSION['auth_user']['email'])) {
        $userEmail = $_SESSION['auth_user']['email'];
    } elseif (isset($_SESSION['auth_user']['username'])) {
        $userEmail = $_SESSION['auth_user']['username'];
    } else {
        $userEmail = '';
    }
    $userId = $_SESSION['auth_user']['id'] ?? '';
} else {
    $userEmail = '';
    $userId = '';
}

// Lấy số điện thoại từ database nếu đã đăng nhập
$userPhone = '';
if ($isLoggedIn && $userId) {
    require_once 'config.php';
    $stmt = $conn->prepare("SELECT phone FROM patients WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $userPhone = $row['phone'];
    }
    $stmt->close();
}

require_once 'includes/components.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Lịch Hẹn - NALI Dental Clinic</title>
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ========== MOBILE-FIRST MULTI-STEP FORM ========== */
        * { box-sizing: border-box; }
        body { 
            background-color: #f8f9fa; 
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .contact-header {
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .contact-header h1 {
            font-size: 28px;
            margin: 0 0 10px 0;
            font-weight: 700;
        }
        
        .contact-header p {
            font-size: 16px;
            margin: 0;
            opacity: 0.95;
        }
        
        @media (min-width: 768px) {
            .contact-header {
                padding: 50px 20px;
            }
            .contact-header h1 {
                font-size: 36px;
            }
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 992px) {
            .main-container {
                grid-template-columns: 1.5fr 1fr;
                gap: 30px;
            }
        }
        
        /* ========== STEPPER (Thanh bước) ========== */
        .stepper-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .stepper {
            display: flex;
            justify-content: space-between;
            padding: 20px 15px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4ff 100%);
            position: relative;
        }
        
        /* Mobile: Stepper gọn hơn */
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: bold;
            font-size: 18px;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }
        
        .step-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            display: block;
            transition: color 0.3s;
        }
        
        .step.active .step-number {
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            color: white;
            border-color: #4da6ff;
            box-shadow: 0 4px 15px rgba(77, 166, 255, 0.4);
            transform: scale(1.1);
        }
        
        .step.active .step-label {
            color: #4da6ff;
        }
        
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        
        .step.completed .step-label {
            color: #28a745;
        }
        
        /* Desktop: Stepper lớn hơn, đẹp hơn */
        @media (min-width: 768px) {
            .stepper {
                padding: 30px 40px;
            }
            .step-number {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            .step-label {
                font-size: 14px;
            }
        }
        
        /* ========== FORM CARD (Khung form từng bước) ========== */
        .booking-form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 25px 20px;
            min-height: 400px;
            position: relative;
        }
        
        @media (min-width: 768px) {
            .booking-form-card {
                padding: 40px;
                min-height: 450px;
            }
        }

        .booking-form-card h2 { 
            color: #4da6ff; 
            margin: 0 0 10px 0;
            font-size: 22px;
        }
        
        .booking-form-card .subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        /* ========== FORM STEPS (Các bước ẩn/hiện) ========== */
        .form-step {
            display: none;
            animation: fadeInSlide 0.4s ease;
        }
        
        .form-step.active {
            display: block;
        }
        
        @keyframes fadeInSlide {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ========== FORM GROUPS (Touch-Friendly) ========== */
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #333;
            font-size: 14px;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 14px 16px; /* Tăng padding cho touch-friendly */
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px; /* Tránh zoom trên iOS */
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus, 
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4da6ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(77, 166, 255, 0.1);
        }
        
        .form-group small {
            color: #666;
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }
        
        /* Grid responsive */
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* ========== SERVICE CARDS (Thẻ dịch vụ touch-friendly) ========== */
        .service-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 15px;
        }
        
        @media (min-width: 768px) {
            .service-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .service-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            min-height: 60px; /* Touch-friendly height */
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .service-card:hover {
            border-color: #4da6ff;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(77, 166, 255, 0.15);
        }
        
        .service-card.selected {
            border-color: #4da6ff;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4ff 100%);
            box-shadow: 0 4px 15px rgba(77, 166, 255, 0.2);
        }
        
        .service-card input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .service-card-content {
            flex: 1;
        }
        
        .service-card-title {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .service-card-desc {
            font-size: 12px;
            color: #666;
        }
        
        /* ========== STICKY BOTTOM BUTTONS (Mobile-First) ========== */
        .form-navigation {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding: 15px 0;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
        }
        
        @media (max-width: 767px) {
            .form-navigation {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 15px 20px;
                box-shadow: 0 -4px 15px rgba(0,0,0,0.1);
                background: white;
                border-top: 1px solid #e0e0e0;
            }
            
            .booking-form-card {
                margin-bottom: 80px; /* Space for fixed buttons */
            }
        }
        
        .btn-prev,
        .btn-next,
        .btn-submit {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 50px; /* Touch-friendly */
        }
        
        .btn-prev {
            background: #f0f0f0;
            color: #666;
        }
        
        .btn-prev:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .btn-next,
        .btn-submit {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-next:hover,
        .btn-submit:hover {
            background: linear-gradient(135deg, #218838 0%, #1aa179 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-next:active,
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit:disabled,
        .btn-next:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* ========== PROGRESS BAR ========== */
        .progress-bar-container {
            background: #e0e0e0;
            height: 6px;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4da6ff 0%, #20c997 100%);
            transition: width 0.4s ease;
            border-radius: 10px;
        }

        /* Khung Thông tin bên phải */
        .contact-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        @media (max-width: 991px) {
            .contact-info-card {
                order: 2;
                margin-top: 20px;
            }
        }
        
        @media (min-width: 992px) {
            .main-container {
                display: grid;
                grid-template-columns: 1.5fr 1fr;
                gap: 30px;
                align-items: stretch;
            }
            .booking-form-card,
            .contact-info-card {
                min-height: 100%;
            }
        }
        
        .info-box {
            padding: 0;
        }
        
        .info-box h3 {
            font-size: 20px;
            color: #333;
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .branches-section {
            margin-top: 25px;
        }
        
        .branches-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .branch-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #4da6ff;
        }
        
        .branch-item h4 {
            margin: 0 0 8px 0;
            color: #4da6ff;
            font-size: 15px;
            font-weight: 600;
        }
        
        .branch-item p {
            margin: 0;
            color: #666;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .info-item { 
            display: flex; 
            align-items: flex-start; 
            gap: 15px; 
            margin-bottom: 20px;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-item i { 
            font-size: 20px; 
            color: #4da6ff; 
            margin-top: 3px;
        }
        
        .info-item h4 { 
            margin: 0 0 5px 0; 
            color: #333;
            font-size: 14px;
        }
        
        .info-item p { 
            margin: 0; 
            color: #666;
            font-size: 14px;
        }

        /* Bản đồ */
        .map-section {
            max-width: 1200px;
            margin: 40px auto 0;
            padding: 0 20px 40px;
        }
        
        .map-section h3 {
            font-size: 24px;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .maps-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 992px) {
            .maps-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .map-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .map-item h4 {
            padding: 15px 20px;
            margin: 0;
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            color: white;
            font-size: 16px;
            text-align: center;
        }
        
        .map-item p {
            padding: 10px 15px;
            margin: 0;
            font-size: 13px;
            color: #666;
            background: #f8f9fa;
            text-align: center;
        }
        
        .map-box {
            height: 250px;
        }
        
        .map-box iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        /* Doctor notification banner */
        .doctor-notification {
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInDown 0.5s ease;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        .contact-info-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 30px;
            padding-top: 40px; /* Thêm khoảng cách trên */
        }

        .info-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .info-item { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px; }
        .info-item i { font-size: 20px; color: #4da6ff; margin-top: 5px; }
        .info-item h4 { margin: 0 0 5px 0; color: #333; }
        .info-item p { margin: 0; color: #666; }

        /* Bản đồ */
        .map-box {
            flex-grow: 1;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            min-height: 300px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
            .contact-header { height: 180px; }
        }

        /* ========== QUICK CONTACT BUTTONS (Mobile) ========== */
        .quick-contact-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 500px;
            margin: -20px auto 20px;
            padding: 0 15px;
            position: relative;
            z-index: 10;
        }
        .quick-btn {
            background: white;
            border: none;
            border-radius: 12px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        .quick-btn:hover, .quick-btn:active {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(77,166,255,0.25);
        }
        .quick-btn i {
            font-size: 24px;
            color: #4da6ff;
            display: block;
            margin-bottom: 8px;
        }
        .quick-btn span {
            font-size: 12px;
            font-weight: 600;
            display: block;
        }
        .quick-btn.call-btn i { color: #28a745; }
        .quick-btn.zalo-btn i { color: #0068ff; }
        .quick-btn.messenger-btn i { color: #0084ff; }

        /* ========== FLOATING CALL BUTTON (Mobile) ========== */
        .floating-call {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            box-shadow: 0 4px 20px rgba(40,167,69,0.4);
            z-index: 999;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            animation: pulse 2s infinite;
        }
        .floating-call i {
            color: white;
            font-size: 26px;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        @media (max-width: 768px) {
            .floating-call { display: flex; }
        }

        /* ========== IMPROVED MOBILE FORM ========== */
        @media (max-width: 768px) {
            .contact-header { padding: 20px 15px; }
            .contact-header h1 { font-size: 22px; }
            .contact-header p { font-size: 14px; }
            
            .main-container { padding: 15px; }
            .booking-form-card { padding: 20px 15px; margin-bottom: 20px; }
            
            /* Larger touch targets */
            .booking-form-card input,
            .booking-form-card select,
            .booking-form-card textarea {
                padding: 14px !important;
                font-size: 16px !important;
                border-radius: 10px !important;
            }
            
            /* Full width date/time on mobile */
            .booking-form-card div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
            
            /* Bigger submit button */
            .booking-form-card button {
                padding: 18px !important;
                font-size: 17px !important;
                border-radius: 12px !important;
            }
            
            /* Maps section mobile */
            .map-section { padding: 0 15px 30px; margin-top: 30px; }
            .map-section h3 { font-size: 20px; margin-bottom: 20px; }
            .maps-grid { gap: 20px; }
            .map-box { height: 200px; }
            
            /* Feedback section mobile */
            .feedback-section-mobile {
                padding: 25px 15px !important;
                margin: 40px 15px !important;
            }
            .feedback-section-mobile div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
            .feedback-section-mobile h2 { font-size: 22px !important; }
            .feedback-section-mobile #starRating { justify-content: center; }
            .feedback-section-mobile .star { font-size: 38px !important; }
            
            /* Footer mobile */
            footer { padding: 40px 15px 25px !important; }
            footer > div > div:first-child {
                grid-template-columns: 1fr !important;
                text-align: center;
                gap: 30px !important;
            }
        }

        /* ========== SCROLL TO TOP (Mobile) ========== */
        .scroll-top {
            display: none;
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 45px;
            height: 45px;
            background: #4da6ff;
            border-radius: 50%;
            box-shadow: 0 3px 15px rgba(77,166,255,0.3);
            z-index: 998;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .scroll-top i { color: white; font-size: 20px; }
        .scroll-top.show { display: flex; }
    </style>
</head>
<body>

<?php renderHeader('contact'); ?>

    <div class="contact-header">
        <div>
            <h1><i class="fas fa-calendar-check"></i> Đặt Lịch Tư Vấn</h1>
            <p style="margin-top: 8px; opacity: 0.9; font-size: 14px;">Miễn phí tư vấn • Xác nhận trong 5 phút</p>
        </div>
    </div>

    <!-- Quick Contact Buttons (Mobile-friendly) -->
    <div class="quick-contact-bar">
        <a href="tel:0945457512" class="quick-btn call-btn">
            <i class="fas fa-phone-alt"></i>
            <span>Gọi ngay</span>
        </a>
        <a href="https://zalo.me/0945457512" class="quick-btn zalo-btn" target="_blank">
            <i class="fas fa-comment-dots"></i>
            <span>Chat Zalo</span>
        </a>
        <a href="https://m.me/nhat.trinhngocminh.7" class="quick-btn messenger-btn" target="_blank">
            <i class="fab fa-facebook-messenger"></i>
            <span>Messenger</span>
        </a>
    </div>

    <!-- BOOKING FORM SECTION (Centered like Feedback) -->
    <div style="max-width: 800px; margin: 30px auto; padding: 0 20px;">
        <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="color: #4da6ff; margin-bottom: 10px; font-size: 28px;">
                    <i class="fas fa-tooth"></i> Thông Tin Đặt Lịch Tư Vấn
                </h2>
                <p style="color: #666; font-size: 14px;">Điền thông tin để được tư vấn miễn phí</p>
            </div>

            <?php if (!$isLoggedIn): ?>
            <!-- Thông báo yêu cầu đăng nhập -->
            <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 25px; margin-bottom: 25px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: #ffc107; margin-bottom: 15px;"></i>
                <h3 style="color: #856404; margin: 0 0 10px 0;">Vui lòng đăng nhập để đặt lịch</h3>
                <p style="color: #856404; margin: 0 0 20px 0;">Bạn cần có tài khoản để đặt lịch hẹn và theo dõi lịch sử khám.</p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="login.html" style="padding: 12px 30px; background: #4da6ff; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-sign-in-alt"></i> Đăng Nhập
                    </a>
                    <a href="login.html" onclick="localStorage.setItem('showSignup', 'true')" style="padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-user-plus"></i> Đăng Ký Tài Khoản
                    </a>
                </div>
            </div>


            <form id="bookingForm" style="display: grid; gap: 20px; <?php echo !$isLoggedIn ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                            </form>
                            <?php endif; ?>
                <!-- Row 1: Name & Phone -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Họ và tên <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="text" id="lienheHoTen" placeholder="Nguyễn Văn A" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; transition: border-color 0.3s;">
                        <span id="errorLienheHoTen" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Họ tên chỉ được chứa chữ cái</span>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Số điện thoại <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="tel" id="lienheSdt" placeholder="0945457512" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; transition: border-color 0.3s;">
                        <span id="errorLienheSdt" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">SĐT phải có 10 số, bắt đầu bằng 0</span>
                    </div>
                </div>
                
                <!-- Row 2: Email -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Email <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="email" id="lienheEmail" placeholder="example@gmail.com" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; transition: border-color 0.3s;">
                    <span id="errorLienheEmail" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Email không hợp lệ</span>
                </div>
                
                <!-- Row 3: Date & Time -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Ngày hẹn <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="date" id="lienheNgayHen" 
                            style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; transition: border-color 0.3s;">
                        <span id="errorLienheNgayHen" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Ngày không được là quá khứ</span>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Giờ hẹn <span style="color: #dc3545;">*</span>
                        </label>
                        <select id="lienheGioHen" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white;">
                            <option value="">Chọn giờ</option>
                            <option value="08:00">08:00</option>
                            <option value="09:00">09:00</option>
                            <option value="10:00">10:00</option>
                            <option value="11:00">11:00</option>
                            <option value="12:00">12:00</option>
                            <option value="13:00">13:00</option>
                            <option value="14:00">14:00</option>
                            <option value="15:00">15:00</option>
                            <option value="16:00">16:00</option>
                            <option value="17:00">17:00</option>
                            <option value="18:00">18:00</option>
                            <option value="19:00">19:00</option>
                            <option value="20:00">20:00</option>
                        </select>
                        <span id="errorLienheGioHen" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Giờ từ 08:00 - 20:00</span>
                    </div>
                </div>
                
                <!-- Row 4: Category -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Bạn thuộc đối tượng nào? <span style="color: #dc3545;">*</span>
                    </label>
                    <select id="lienheDoiTuong" onchange="updateServicesByCategory()" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white;">
                        <option value="">-- Chọn đối tượng --</option>
                        <option value="children">👶 Nha Khoa Trẻ Em (0-15 tuổi)</option>
                        <option value="adults">👨‍💼 Người Trưởng Thành (16-59 tuổi)</option>
                        <option value="elderly">👴 Người Cao Tuổi (60+ tuổi)</option>
                        <option value="chronic">🏥 Bệnh Lý Nền (Mọi lứa tuổi)</option>
                    </select>
                    <span id="errorLienheDoiTuong" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Vui lòng chọn đối tượng</span>
                </div>
                
                <!-- Row 5: Service & Doctor -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Dịch vụ quan tâm <span style="color: #dc3545;">*</span>
                        </label>
                        <select id="lienheService" onchange="updateDoctorsByService()" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white;">
                            <option value="">-- Chọn đối tượng trước --</option>
                        </select>
                        <span id="errorLienheService" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Vui lòng chọn dịch vụ</span>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Chọn bác sĩ
                        </label>
                        <select id="lienheBacSi" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white;">
                            <option value="">-- Chọn dịch vụ trước --</option>
                        </select>
                    </div>
                </div>
                
                <!-- Row 6: Notes -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Ghi chú / Triệu chứng
                    </label>
                    <textarea id="lienheGhiChu" placeholder="Mô tả triệu chứng hoặc yêu cầu đặc biệt..." 
                        style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; resize: vertical; min-height: 100px;"></textarea>
                </div>
                
                <!-- Submit Button -->
                <button type="button" onclick="submitLienheBooking()" 
                    style="width: 100%; padding: 18px; background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 17px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(40,167,69,0.3);"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 25px rgba(40,167,69,0.4)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(40,167,69,0.3)';">
                    <i class="fas fa-calendar-check"></i> Xác Nhận Đặt Lịch Tư Vấn
                </button>
            </form>
            
            <!-- Info Tip -->
            <div style="background: rgba(255,255,255,0.7); padding: 15px 20px; border-radius: 12px; margin-top: 25px; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-info-circle" style="color: #4da6ff; font-size: 20px;"></i>
                <p style="margin: 0; color: #333; font-size: 14px;">
                    <strong>Miễn phí tư vấn!</strong> Sau khi gửi, nhân viên sẽ gọi xác nhận trong 5 phút.
                </p>
            </div>
        </div>
    </div>

    <!-- Google Maps Section (Bottom) - 3 Branches -->
    <div class="map-section">
        <h3><i class="fas fa-map-marked-alt"></i> Bản Đồ Các Cơ Sở</h3>
        <div class="maps-grid">
            <!-- Cơ Sở 1 - Bình Thạnh -->
            <div class="map-item">
                <h4><i class="fas fa-hospital"></i> Cơ Sở 1 - Bình Thạnh</h4>
                <p><i class="fas fa-map-marker-alt"></i> 69/68 Đặng Thùy Trâm, P. Bình Lợi Trung</p>
                <div class="map-box">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3918.8579647391974!2d106.71847087584655!3d10.822898889329258!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3175283ad5764a81%3A0xa5a8f7b8b7e5f2b2!2zNjkvNjggxJDhuqFuZyBUaOG7p3kgVHLDom0sIELDrG5oIEzhu6NpLCBCw6xuaCBUaOG6oW5oLCBUaMOgbmggcGjhu5EgSOG7kyBDaMOtIE1pbmg!5e0!3m2!1svi!2s!4v1703427890123!5m2!1svi!2s" 
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            
            <!-- Cơ Sở 2 - Quận 1 -->
            <div class="map-item">
                <h4><i class="fas fa-hospital"></i> Cơ Sở 2 - Quận 1</h4>
                <p><i class="fas fa-map-marker-alt"></i> 123 Nguyễn Huệ, P. Bến Nghé, Quận 1</p>
                <div class="map-box">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.5198459387574!2d106.70077847584613!3d10.772544089387608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f4670702e31%3A0xa5777fb3a5bb9972!2zMTIzIE5ndXnhu4VuIEh14buHLCBC4bq_biBOZ2jDqSwgUXXhuq1uIDEsIFRow6BuaCBwaOG7kSBI4buTIENow60gTWluaCwgVmnhu4d0IE5hbQ!5e0!3m2!1svi!2s!4v1703427890124!5m2!1svi!2s" 
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
            
            <!-- Cơ Sở 3 - Gò Vấp -->
            <div class="map-item">
                <h4><i class="fas fa-hospital"></i> Cơ Sở 3 - Gò Vấp</h4>
                <p><i class="fas fa-map-marker-alt"></i> 456 Quang Trung, P.10, Quận Gò Vấp</p>
                <div class="map-box">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3918.4889877595775!2d106.66407087584684!3d10.850775689276748!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752890d4ab7e97%3A0x60a9a747d06ae8d9!2zNDU2IFF1YW5nIFRydW5nLCBQaMaw4budbmcgMTAsIEfDsiBW4bqlcCwgVGjDoG5oIHBo4buRIEjhu5MgQ2jDrSBNaW5oLCBWaeG7h3QgTmFt!5e0!3m2!1svi!2s!4v1703427890125!5m2!1svi!2s" 
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- FEEDBACK SECTION -->
    <div style="max-width: 800px; margin: 60px auto; padding: 0 20px;">
        <div class="feedback-section-mobile" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="color: #4da6ff; margin-bottom: 10px; font-size: 28px;">
                    <i class="fas fa-comment-dots"></i> Góp Ý & Phản Hồi
                </h2>
                <p style="color: #666; font-size: 14px;">Ý kiến của bạn giúp chúng tôi phục vụ tốt hơn</p>
            </div>
            
            <form id="feedbackForm" style="display: grid; gap: 20px;">
                <!-- Row 1: Name & Phone -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Họ và tên <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="text" id="feedbackName" placeholder="Nguyễn Văn A" 
                            style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white; transition: border-color 0.3s;">
                        <span id="errorFeedbackName" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Vui lòng nhập họ tên</span>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                            Số điện thoại <span style="color: #dc3545;">*</span>
                        </label>
                        <input type="tel" id="feedbackPhone" placeholder="0945457512" 
                            style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white; transition: border-color 0.3s;">
                        <span id="errorFeedbackPhone" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Số điện thoại phải có 10 số</span>
                    </div>
                </div>
                
                <!-- Row 2: Email -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Email <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="email" id="feedbackEmail" placeholder="example@gmail.com" 
                        style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white; transition: border-color 0.3s;">
                    <span id="errorFeedbackEmail" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Email không hợp lệ</span>
                </div>
                
                <!-- Row 3: Rating -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Mức độ hài lòng <span style="color: #dc3545;">*</span>
                    </label>
                    <div id="starRating" style="display: flex; gap: 8px; font-size: 32px; cursor: pointer;">
                        <span class="star" data-value="1" style="color: #ddd; transition: color 0.2s;">★</span>
                        <span class="star" data-value="2" style="color: #ddd; transition: color 0.2s;">★</span>
                        <span class="star" data-value="3" style="color: #ddd; transition: color 0.2s;">★</span>
                        <span class="star" data-value="4" style="color: #ddd; transition: color 0.2s;">★</span>
                        <span class="star" data-value="5" style="color: #ddd; transition: color 0.2s;">★</span>
                        <span id="ratingText" style="font-size: 14px; color: #666; margin-left: 10px; align-self: center;"></span>
                    </div>
                    <input type="hidden" id="feedbackRating" value="">
                    <span id="errorFeedbackRating" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Vui lòng chọn mức độ hài lòng</span>
                </div>
                
                <!-- Row 4: Feedback Type -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Loại phản hồi <span style="color: #dc3545;">*</span>
                    </label>
                    <select id="feedbackType" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white;">
                        <option value="">-- Chọn loại phản hồi --</option>
                        <option value="compliment">🌟 Khen ngợi dịch vụ</option>
                        <option value="suggestion">💡 Góp ý cải thiện</option>
                        <option value="complaint">⚠️ Khiếu nại</option>
                        <option value="question">❓ Câu hỏi thắc mắc</option>
                        <option value="other">📝 Khác</option>
                    </select>
                    <span id="errorFeedbackType" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Vui lòng chọn loại phản hồi</span>
                </div>
                
                <!-- Row 5: Message -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">
                        Nội dung phản hồi <span style="color: #dc3545;">*</span>
                    </label>
                    <textarea id="feedbackMessage" placeholder="Chia sẻ trải nghiệm của bạn với chúng tôi..." 
                        style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white; resize: vertical; min-height: 120px;"></textarea>
                    <span id="errorFeedbackMessage" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">Vui lòng nhập nội dung phản hồi</span>
                </div>
                
                <!-- Submit Button -->
                <button type="button" onclick="submitFeedback()" 
                    style="width: 100%; padding: 16px; background: linear-gradient(135deg, #4da6ff, #2196F3); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 16px; transition: transform 0.2s, box-shadow 0.2s;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 20px rgba(77,166,255,0.4)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-paper-plane"></i> Gửi Phản Hồi
                </button>
            </form>
        </div>
    </div>

    <?php renderFooter(); ?>

    <script>
        // ========== STAR RATING FUNCTIONALITY ==========
        const ratingTexts = {
            1: '😞 Rất không hài lòng',
            2: '😕 Không hài lòng',
            3: '😐 Bình thường',
            4: '😊 Hài lòng',
            5: '🤩 Rất hài lòng'
        };
        
        document.querySelectorAll('#starRating .star').forEach(star => {
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                document.getElementById('feedbackRating').value = value;
                document.getElementById('ratingText').textContent = ratingTexts[value];
                
                // Update star colors
                document.querySelectorAll('#starRating .star').forEach((s, index) => {
                    s.style.color = index < value ? '#ffc107' : '#ddd';
                });
            });
            
            star.addEventListener('mouseover', function() {
                const value = this.dataset.value;
                document.querySelectorAll('#starRating .star').forEach((s, index) => {
                    s.style.color = index < value ? '#ffc107' : '#ddd';
                });
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = document.getElementById('feedbackRating').value;
                document.querySelectorAll('#starRating .star').forEach((s, index) => {
                    s.style.color = index < currentRating ? '#ffc107' : '#ddd';
                });
            });
        });
        
        // ========== FEEDBACK FORM SUBMISSION ==========
        function submitFeedback() {
            // Clear previous errors
            const errorIds = ['errorFeedbackName', 'errorFeedbackPhone', 'errorFeedbackEmail', 'errorFeedbackRating', 'errorFeedbackType', 'errorFeedbackMessage'];
            const inputIds = ['feedbackName', 'feedbackPhone', 'feedbackEmail', 'feedbackType', 'feedbackMessage'];
            
            errorIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            inputIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.borderColor = '#e0e0e0';
            });
            
            // Get values
            const name = document.getElementById('feedbackName').value.trim();
            const phone = document.getElementById('feedbackPhone').value.trim();
            const email = document.getElementById('feedbackEmail').value.trim();
            const rating = document.getElementById('feedbackRating').value;
            const type = document.getElementById('feedbackType').value;
            const message = document.getElementById('feedbackMessage').value.trim();
            
            let hasError = false;
            
            // Validate name
            const nameRegex = /^[a-zA-ZÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠàáâãèéêìíòóôõùúăđĩũơƯĂẠẢẤẦẨẪẬẮẰẲẴẶẸẺẼỀỀỂẾưăạảấầẩẫậắằẳẵặẹẻẽềềểếỄỆỈỊỌỎỐỒỔỖỘỚỜỞỠỢỤỦỨỪễệỉịọỏốồổỗộớờởỡợụủứừỬỮỰỲỴÝỶỸửữựỳỵỷỹ\s]+$/;
            if (!name || !nameRegex.test(name)) {
                document.getElementById('errorFeedbackName').style.display = 'block';
                document.getElementById('feedbackName').style.borderColor = '#dc3545';
                hasError = true;
            }
            
            // Validate phone
            if (!phone || !/^0[0-9]{9}$/.test(phone)) {
                document.getElementById('errorFeedbackPhone').style.display = 'block';
                document.getElementById('feedbackPhone').style.borderColor = '#dc3545';
                hasError = true;
            }
            
            // Validate email
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('errorFeedbackEmail').style.display = 'block';
                document.getElementById('feedbackEmail').style.borderColor = '#dc3545';
                hasError = true;
            }
            
            // Validate rating
            if (!rating) {
                document.getElementById('errorFeedbackRating').style.display = 'block';
                hasError = true;
            }
            
            // Validate type
            if (!type) {
                document.getElementById('errorFeedbackType').style.display = 'block';
                document.getElementById('feedbackType').style.borderColor = '#dc3545';
                hasError = true;
            }
            
            // Validate message
            if (!message) {
                document.getElementById('errorFeedbackMessage').style.display = 'block';
                document.getElementById('feedbackMessage').style.borderColor = '#dc3545';
                hasError = true;
            }
            
            if (hasError) return;
            
            // Gửi phản hồi lên server để lưu vào database
            const feedbackData = { name, phone, email, rating, type, message };
            
            // Disable button và hiển thị loading
            const submitBtn = document.querySelector('#feedbackForm button[type="button"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
            
            fetch('save_feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(feedbackData)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Hiển thị thông báo thành công
                    document.getElementById('feedbackForm').innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 60px; margin-bottom: 20px;">✅</div>
                            <h3 style="color: #28a745; margin-bottom: 10px;">Cảm ơn bạn đã gửi phản hồi!</h3>
                            <p style="color: #666;">Chúng tôi sẽ xem xét và phản hồi trong thời gian sớm nhất.</p>
                            <button onclick="location.reload()" style="margin-top: 20px; padding: 12px 30px; background: #4da6ff; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-redo"></i> Gửi phản hồi khác
                            </button>
                        </div>
                    `;
                } else {
                    alert(result.message || 'Có lỗi xảy ra, vui lòng thử lại!');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(err => {
                alert('Không thể kết nối máy chủ. Vui lòng thử lại!');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }
        
        // ========== SERVICE DATA BY CATEGORY ==========
        const servicesByCategory = {
            children: [
                'Bôi Fluoride Phòng Sâu Răng',
                'Trám Răng Sữa',
                'Nhổ Răng Sữa An Toàn',
                'Niềng Răng Sớm',
                'Tư Vấn Chăm Sóc Răng Trẻ Em',
                'Làm Sạch Răng Cho Trẻ'
            ],
            adults: [
                'Niềng Răng Invisalign Trong Suốt',
                'Bọc Răng Sứ Emax Cao Cấp',
                'Cấy Ghép Implant Răng',
                'Tẩy Trắng Răng Laser',
                'Mặt Dán Sứ Veneer',
                'Làm Sạch Răng Chuyên Sâu',
                'Niềng Răng Mắc Cài Kim Loại',
                'Điều Trị Tủy Răng'
            ],
            elderly: [
                'Cấy Ghép Implant All-on-4',
                'Hàm Giả Tháo Lắp Cao Cấp',
                'Điều Trị Nha Chu (Viêm Lợi)',
                'Phục Hình Toàn Hàm Răng Sứ',
                'Tư Vấn Chăm Sóc Tại Nhà',
                'Cấy Ghép Implant Đơn Lẻ'
            ],
            chronic: [
                'Khám Tổng Quát Đánh Giá',
                'Điều Trị Tủy Không Đau',
                'Nhổ Răng An Toàn Có Màn Hình',
                'Làm Sạch Nha Chu Kháng Sinh',
                'Tư Vấn Phối Hợp Bác Sĩ Nội Khoa',
                'Làm Sạch Răng Nhẹ Nhàng',
                'Phục Hình Răng An Toàn'
            ]
        };

        // ========== DOCTORS DATA BY CATEGORY ==========
        const doctorsByCategory = {
            children: [
                { name: 'BS. CK1. Hoàng Minh Tuấn', role: 'Nha Khoa Trẻ Em' },
                { name: 'BS. Nguyễn Thị Hương', role: 'Nha Khoa Trẻ Em' }
            ],
            adults: [
                { name: 'BS. Lê Văn Hùng', role: 'Nha Khoa Thẩm Mỹ' },
                { name: 'ThS. BS. Nguyễn Thị Lan', role: 'Chuyên Gia Chỉnh Nha' }
            ],
            elderly: [
                { name: 'PGS.TS. BS. Đỗ Văn Minh', role: 'Chuyên gia Người cao tuổi' },
                { name: 'ThS. BS. Vũ Thu Hằng', role: 'Nha Chu & Nha Công Cộng' }
            ],
            chronic: [
                { name: 'TS. BS. Trần Minh Nhật', role: 'Giám Đốc Chuyên Môn' },
                { name: 'BS. Phạm Thị Mai', role: 'Nha Khoa Nội Nha' }
            ]
        };

        // ========== UPDATE SERVICES DROPDOWN BY CATEGORY ==========
        function updateServicesByCategory() {
            const categorySelect = document.getElementById('lienheDoiTuong');
            const serviceSelect = document.getElementById('lienheService');
            const doctorSelect = document.getElementById('lienheBacSi');
            const selectedCategory = categorySelect.value;
            
            // Clear current options
            serviceSelect.innerHTML = '';
            doctorSelect.innerHTML = '';
            
            if (!selectedCategory) {
                serviceSelect.innerHTML = '<option value="">-- Vui lòng chọn đối tượng trước --</option>';
                doctorSelect.innerHTML = '<option value="">-- Vui lòng chọn dịch vụ trước --</option>';
                return;
            }
            
            // Add default option for services
            serviceSelect.innerHTML = '<option value="">-- Chọn dịch vụ --</option>';
            
            // Add services for selected category
            const services = servicesByCategory[selectedCategory] || [];
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service;
                option.textContent = service;
                serviceSelect.appendChild(option);
            });
            
            // Reset doctor dropdown
            doctorSelect.innerHTML = '<option value="">-- Vui lòng chọn dịch vụ trước --</option>';
        }

        // ========== UPDATE DOCTORS DROPDOWN BY SERVICE ==========
        function updateDoctorsByService() {
            const categorySelect = document.getElementById('lienheDoiTuong');
            const serviceSelect = document.getElementById('lienheService');
            const doctorSelect = document.getElementById('lienheBacSi');
            const selectedCategory = categorySelect.value;
            const selectedService = serviceSelect.value;
            
            // Clear current options
            doctorSelect.innerHTML = '';
            
            if (!selectedService) {
                doctorSelect.innerHTML = '<option value="">-- Vui lòng chọn dịch vụ trước --</option>';
                return;
            }
            
            // Add default option
            doctorSelect.innerHTML = '<option value="">-- Chọn bác sĩ (không bắt buộc) --</option>';
            
            // Add doctors for selected category
            const doctors = doctorsByCategory[selectedCategory] || [];
            doctors.forEach(doctor => {
                const option = document.createElement('option');
                option.value = doctor.name;
                option.textContent = `${doctor.name} - ${doctor.role}`;
                doctorSelect.appendChild(option);
            });
        }

        // ========== SIMPLE BOOKING FORM HANDLER ==========
        function clearLienheErrors() {
            const errorIds = ['errorLienheHoTen', 'errorLienheSdt', 'errorLienheEmail', 'errorLienheNgayHen', 'errorLienheGioHen', 'errorLienheDoiTuong', 'errorLienheService'];
            const inputIds = ['lienheHoTen', 'lienheSdt', 'lienheEmail', 'lienheNgayHen', 'lienheGioHen', 'lienheDoiTuong', 'lienheService'];
            
            errorIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            inputIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.borderColor = '#e0e0e0';
            });
        }
        
        function showLienheError(fieldId, errorId) {
            const field = document.getElementById(fieldId);
            const error = document.getElementById(errorId);
            if (field) field.style.borderColor = '#dc3545';
            if (error) error.style.display = 'block';
        }
        
        async function submitLienheBooking() {
            // Clear previous errors
            clearLienheErrors();
            
            // Get form data
            const name = document.getElementById('lienheHoTen').value.trim();
            const phone = document.getElementById('lienheSdt').value.trim();
            const email = document.getElementById('lienheEmail').value.trim();
            const date = document.getElementById('lienheNgayHen').value;
            const time = document.getElementById('lienheGioHen').value;
            const category = document.getElementById('lienheDoiTuong').value;
            const service = document.getElementById('lienheService').value;
            const doctor = document.getElementById('lienheBacSi').value;
            const notes = document.getElementById('lienheGhiChu').value.trim();
            
            // Validate with inline errors
            let hasError = false;
            
            // Name validation - must contain only letters (including Vietnamese), spaces, no numbers
            const nameRegex = /^[a-zA-ZÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠàáâãèéêìíòóôõùúăđĩũơƯĂẠẢẤẦẨẪẬẮẰẲẴẶẸẺẼỀỀỂẾưăạảấầẩẫậắằẳẵặẹẻẽềềểếỄỆỈỊỌỎỐỒỔỖỘỚỜỞỠỢỤỦỨỪễệỉịọỏốồổỗộớờởỡợụủứừỬỮỰỲỴÝỶỸửữựỳỵỷỹ\s]+$/;
            if (!name || !nameRegex.test(name)) { showLienheError('lienheHoTen', 'errorLienheHoTen'); hasError = true; }
            if (!phone || !/^0[0-9]{9}$/.test(phone)) { showLienheError('lienheSdt', 'errorLienheSdt'); hasError = true; }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showLienheError('lienheEmail', 'errorLienheEmail'); hasError = true; }
            
            // Validate date - not in the past
            if (!date) {
                showLienheError('lienheNgayHen', 'errorLienheNgayHen'); hasError = true;
            } else {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const selectedDate = new Date(date);
                if (selectedDate < today) {
                    showLienheError('lienheNgayHen', 'errorLienheNgayHen'); hasError = true;
                }
            }
            
            // Validate time - must be between 08:00 and 20:00
            if (!time) {
                showLienheError('lienheGioHen', 'errorLienheGioHen'); hasError = true;
            } else {
                const [hours, minutes] = time.split(':').map(Number);
                if (hours < 8 || hours > 20 || minutes !== 0) {
                    showLienheError('lienheGioHen', 'errorLienheGioHen'); hasError = true;
                }
            }
            
            if (!category) { showLienheError('lienheDoiTuong', 'errorLienheDoiTuong'); hasError = true; }
            if (!service) { showLienheError('lienheService', 'errorLienheService'); hasError = true; }
            
            if (hasError) {
                // Scroll to first error
                const firstError = document.querySelector('[style*="border-color: rgb(220, 53, 69)"]');
                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            
            // Show loading
            const submitBtn = event.target;
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Đang xử lý...';
            
            try {
                // Call API to save to database
                const response = await fetch('book_appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: name,
                        phone: phone,
                        email: email,
                        date: date,
                        time: time,
                        service: service,
                        category: category,
                        doctor: doctor,
                        notes: notes,
                        bookingType: 'consultation'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Generate booking code from appointment_id
                    const bookingCode = 'NALI' + String(result.appointment_id).padStart(6, '0');

                    // Save to localStorage for success page
                    localStorage.setItem('lastBooking', JSON.stringify({
                        bookingType: 'consultation',
                        bookingCode: bookingCode,
                        customerName: name,
                        customerPhone: phone,
                        customerEmail: email,
                        appointmentDate: date,
                        appointmentTime: time,
                        service: service,
                        category: category,
                        doctor: doctor,
                        notes: notes
                    }));

                    // Redirect to success page
                    window.location.href = 'success.html';
                } else {
                    // Kiểm tra nếu yêu cầu đăng nhập
                    if (result.require_login) {
                        alert('⚠️ Vui lòng đăng nhập để đặt lịch hẹn!');
                        window.location.href = 'login.html';
                    } else {
                        alert('❌ ' + (result.message || 'Đặt lịch thất bại. Vui lòng thử lại.'));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            } catch (error) {
                console.error('Booking error:', error);
                alert('❌ Có lỗi xảy ra. Vui lòng kiểm tra kết nối và thử lại.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        // ========== AUTO-FILL FROM QUERY PARAMETER ==========
        document.addEventListener('DOMContentLoaded', function() {
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const doctorName = urlParams.get('doctor');
            const serviceName = urlParams.get('service');
            
            if (serviceName && doctorName) {
                // Find which category this service belongs to
                let foundCategory = null;
                for (const [category, services] of Object.entries(servicesByCategory)) {
                    if (services.includes(serviceName)) {
                        foundCategory = category;
                        break;
                    }
                }
                
                if (foundCategory) {
                    // 1. Set category dropdown
                    const categorySelect = document.getElementById('lienheDoiTuong');
                    categorySelect.value = foundCategory;
                    
                    // 2. Trigger service dropdown update
                    updateServicesByCategory();
                    
                    // 3. Set service dropdown
                    const serviceSelect = document.getElementById('lienheService');
                    serviceSelect.value = serviceName;
                    
                    // 4. Trigger doctor dropdown update
                    updateDoctorsByService();
                    
                    // 5. Set doctor dropdown
                    const doctorSelect = document.getElementById('lienheBacSi');
                    doctorSelect.value = doctorName;
                    
                    // 6. Show notification
                    const notificationHTML = `
                        <div style="background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-user-md" style="font-size: 24px;"></i>
                            <div>
                                <strong style="display: block; font-size: 14px;">Đặt lịch với ${doctorName}</strong>
                                <span style="font-size: 13px; opacity: 0.9;">Dịch vụ: ${serviceName}</span>
                            </div>
                        </div>
                    `;
                    
                    const formCard = document.querySelector('.booking-form-card');
                    const formTitle = formCard.querySelector('h2');
                    if (formTitle) {
                        formTitle.insertAdjacentHTML('afterend', notificationHTML);
                    }
                }
            }
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('lienheNgayHen');
            if (dateInput) {
                dateInput.setAttribute('min', today);
            }
        });
    </script>
    
    <script>
    // Mark active navigation link based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        const navLinks = document.querySelectorAll('nav a');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            }
        });
    });
    </script>
    <script src="header-user.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // SweetAlert2 logout handler (KHÔNG dùng confirm cũ)
    document.addEventListener('DOMContentLoaded', function() {
        var logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.onclick = function(e) {
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
            };
        }
    });
    </script>
    <script>
    // AJAX logout handler for all pages
    document.addEventListener('DOMContentLoaded', function() {
        var logoutBtn = document.getElementById('logoutBtn');
        // Removed legacy confirm() logout handler. Only SweetAlert2 is used now.
            });
        }
    });
    </script>

    <!-- Floating Call Button (Mobile) -->
    <a href="tel:0945457512" class="floating-call" title="Gọi ngay">
        <i class="fas fa-phone-alt"></i>
    </a>

    <!-- Scroll to Top Button -->
    <div class="scroll-top" onclick="window.scrollTo({top:0, behavior:'smooth'})" title="Lên đầu trang">
        <i class="fas fa-chevron-up"></i>
    </div>

    <script>
        // Show/hide scroll-to-top button
        window.addEventListener('scroll', function() {
            const scrollTop = document.querySelector('.scroll-top');
            if (window.scrollY > 300) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });
    </script>
    
    </body>
</html>