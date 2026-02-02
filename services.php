<?php
require_once 'includes/components.php';
require_once 'config.php';

// Bắt tín hiệu group trên URL
$group = $_GET['group'] ?? '';
$groupNames = [
    'children' => 'Nha Khoa Trẻ Em',
    'adults' => 'Nha Khoa Người Lớn',
    'elderly' => 'Nha Khoa Người Cao Tuổi',
    'chronic' => 'Nha Khoa Bệnh Lý Nền'
];
$title = $groupNames[$group] ?? 'Tất Cả Dịch Vụ';

if ($group) {
    $sql = "SELECT * FROM products WHERE target_group = '" . $conn->real_escape_string($group) . "'";
} else {
    $sql = "SELECT * FROM products";
}
$result = $conn->query($sql);

// Lấy tất cả services theo nhóm
$allServices = [];
if ($result && $result->num_rows > 0) {
    while ($service = $result->fetch_assoc()) {
        $allServices[$service['target_group']][] = $service;
    }
}

$groups = [
    'children' => ['label' => 'Trẻ Em', 'icon' => '👶'],
    'adults' => ['label' => 'Người Lớn', 'icon' => '👨‍💼'],
    'elderly' => ['label' => 'Người Cao Tuổi', 'icon' => '👴'],
    'chronic' => ['label' => 'Bệnh Lý Nền', 'icon' => '🏥']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - NALI Dental Clinic</title>
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ========== CSS RIÊNG CHO TRANG DỊCH VỤ ========== */
        body { background-color: #f8f9fa; }

        .services-header {
            background: linear-gradient(rgba(0, 123, 255, 0.8), rgba(0, 70, 147, 0.8)), url('images/dental-bg.jpg');
            background-size: cover;
            background-position: center;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            margin-bottom: 40px;
        }

        .services-header h1 {
            font-size: 2.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 50px 20px;
        }

        /* Nút quay lại */
        .btn-back {
            background: #4da6ff;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(77,166,255,0.15);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            margin-bottom: 30px;
        }
        .btn-back:hover {
            background: #238be6;
            box-shadow: 0 4px 16px rgba(77,166,255,0.25);
            transform: translateY(-2px);
        }

        /* Bộ lọc nhóm */
        .group-filter {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
            text-align: center;
        }

        .group-filter h3 {
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
            padding: 12px 25px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 25px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            border-color: #4da6ff;
            color: #4da6ff;
            background: #e7f1ff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 166, 255, 0.2);
        }

        .filter-btn.active {
            background: #4da6ff;
            color: white;
        }

        /* Tiêu đề nhóm */
        .group-title {
            color: #4da6ff;
            font-size: 1.6rem;
            margin: 40px 0 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e7f1ff;
        }

        .group-title .icon {
            font-size: 1.8rem;
        }

        /* Grid dịch vụ */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Card dịch vụ */
        .service-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .service-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .service-card-content {
            padding: 25px;
        }

        .service-card h3 {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }

        .service-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
            min-height: 50px;
        }

        .service-card .price {
            display: block;
            color: #4da6ff;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .service-card .order-btn {
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .service-card .order-btn:hover {
            background: linear-gradient(135deg, #3d8fe8 0%, #2c7ad1 100%);
            box-shadow: 0 5px 15px rgba(77, 166, 255, 0.4);
        }

        /* Thông báo trống */
        .empty-message {
            text-align: center;
            color: #888;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .empty-message i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        /* Responsive - Mobile First */
        @media (max-width: 768px) {
            .services-header {
                height: 180px;
                margin-bottom: 25px;
            }
            
            .services-header h1 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 0 16px 40px;
            }
            
            .btn-back {
                padding: 12px 20px;
                font-size: 0.95rem;
                margin-bottom: 20px;
            }
            
            .group-filter {
                padding: 20px 15px;
                margin-bottom: 25px;
            }
            
            .group-filter h3 {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }
            
            .filter-buttons {
                gap: 8px;
            }
            
            .filter-btn {
                padding: 12px 16px;
                font-size: 14px;
                flex: 1;
                min-width: calc(50% - 8px);
                justify-content: center;
            }
            
            .filter-btn span {
                font-size: 1.2rem;
            }
            
            .group-title {
                font-size: 1.3rem;
                margin: 30px 0 20px;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .group-title a {
                font-size: 0.85rem !important;
                margin-left: 0 !important;
                width: 100%;
                margin-top: 8px;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .service-card img {
                height: 180px;
            }
            
            .service-card-content {
                padding: 20px;
            }
            
            .service-card h3 {
                font-size: 1.1rem;
            }
            
            .service-card p {
                font-size: 0.9rem;
                min-height: auto;
                margin-bottom: 12px;
            }
            
            .service-card .price {
                font-size: 1.2rem;
            }
            
            .service-card .order-btn {
                padding: 14px 20px;
                font-size: 1rem;
                min-height: 52px;
            }
            
            .service-card .order-btn:active {
                transform: scale(0.98);
            }
            
            .empty-message {
                padding: 40px 20px;
            }
            
            .empty-message i {
                font-size: 3rem;
            }
        }
        
        /* Small mobile */
        @media (max-width: 375px) {
            .services-header h1 {
                font-size: 1.3rem;
            }
            
            .filter-btn {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .service-card-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <?php renderHeader('services'); ?>

    <!-- Header Banner -->
    <div class="services-header">
        <h1><i class="fas fa-tooth"></i> <?php echo htmlspecialchars($title); ?></h1>
    </div>

    <div class="container">
        <!-- Nút quay lại (chỉ hiển thị khi đang xem 1 nhóm) -->
        <?php if ($group): ?>
        <a href="services.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Xem tất cả dịch vụ
        </a>
        <?php endif; ?>

        <!-- Bộ lọc nhóm -->
        <div class="group-filter">
            <h3><i class="fas fa-filter"></i> Chọn nhóm khách hàng</h3>
            <div class="filter-buttons">
                <a href="services.php" class="filter-btn <?php echo !$group ? 'active' : ''; ?>">
                    <span>🦷</span> Tất cả
                </a>
                <?php foreach ($groups as $key => $info): ?>
                <a href="services.php?group=<?php echo $key; ?>" class="filter-btn <?php echo $group === $key ? 'active' : ''; ?>">
                    <span><?php echo $info['icon']; ?></span> <?php echo $info['label']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($group && isset($groups[$group])): ?>
            <!-- Hiển thị 1 nhóm cụ thể -->
            <?php if (!empty($allServices[$group])): ?>
                <div class="services-grid">
                    <?php foreach ($allServices[$group] as $service):
                        $imgSrc = $service['image'] ? (strpos($service['image'], 'http') === 0 ? $service['image'] : 'images/' . $service['image']) : 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?w=500';
                    ?>
                    <div class="service-card">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" loading="lazy">
                        <div class="service-card-content">
                            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p><?php echo htmlspecialchars($service['description']); ?></p>
                            <span class="price"><?php echo $service['price'] ? number_format($service['price'], 0, ',', '.') . ' VNĐ' : 'Liên hệ'; ?></span>
                            <button class="order-btn" onclick="openPaymentModal('<?php echo addslashes($service['name']); ?>', '<?php echo addslashes($service['target_group']); ?>')">
                                <i class="fas fa-calendar-check"></i> Đặt lịch khám
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-inbox"></i>
                    <p>Chưa có dịch vụ nào cho nhóm này.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Hiển thị tất cả nhóm -->
            <?php 
            $hasAny = false;
            foreach ($groups as $key => $info):
                if (!empty($allServices[$key])): 
                    $hasAny = true; 
            ?>
                <h2 class="group-title">
                    <span class="icon"><?php echo $info['icon']; ?></span>
                    <?php echo $info['label']; ?>
                    <a href="services.php?group=<?php echo $key; ?>" style="margin-left: auto; font-size: 0.9rem; color: #4da6ff; text-decoration: none;">
                        Xem tất cả <i class="fas fa-chevron-right"></i>
                    </a>
                </h2>
                <div class="services-grid">
                    <?php foreach ($allServices[$key] as $service):
                        $imgSrc = $service['image'] ? (strpos($service['image'], 'http') === 0 ? $service['image'] : 'images/' . $service['image']) : 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?w=500';
                    ?>
                    <div class="service-card">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" loading="lazy">
                        <div class="service-card-content">
                            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p><?php echo htmlspecialchars($service['description']); ?></p>
                            <span class="price"><?php echo $service['price'] ? number_format($service['price'], 0, ',', '.') . ' VNĐ' : 'Liên hệ'; ?></span>
                            <button class="order-btn" onclick="openPaymentModal('<?php echo addslashes($service['name']); ?>', '<?php echo addslashes($service['target_group']); ?>')">
                                <i class="fas fa-calendar-check"></i> Đặt lịch khám
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php 
                endif;
            endforeach;
            
            if (!$hasAny): 
            ?>
                <div class="empty-message">
                    <i class="fas fa-inbox"></i>
                    <p>Chưa có dịch vụ nào.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="script.js"></script>
    <?php renderFooter(); ?>
</body>
</html>
