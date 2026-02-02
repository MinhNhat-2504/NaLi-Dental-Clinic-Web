<?php
/**
 * NALI Dental Clinic - Common Components
 * Header và Footer dùng chung cho tất cả các trang
 */

// Khởi tạo session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['auth']) && $_SESSION['auth'] === true;
$userName = $isLoggedIn ? ($_SESSION['auth_user']['name'] ?? 'Khách') : '';
$userRole = $isLoggedIn ? ($_SESSION['auth_user']['role'] ?? 'user') : '';

/**
 * Render Header với Navigation
 * @param string $currentPage - Tên trang hiện tại để đánh dấu active
 */
function renderHeader($currentPage = '') {
    global $isLoggedIn, $userName, $userRole;
    
    $navItems = [
        ['href' => 'about.php', 'label' => 'Về NALI', 'icon' => 'fa-info-circle', 'key' => 'about'],
        ['href' => 'services.php', 'label' => 'Dịch vụ', 'icon' => 'fa-tooth', 'key' => 'services'],
        ['href' => 'contact.php', 'label' => 'Đặt lịch', 'icon' => 'fa-calendar-check', 'key' => 'contact'],
        ['href' => 'news.php', 'label' => 'Kiến thức', 'icon' => 'fa-newspaper', 'key' => 'news'],
        ['href' => 'team.php', 'label' => 'Bác sĩ', 'icon' => 'fa-user-md', 'key' => 'team'],
    ];
    ?>
    <header class="site-header">
        <div class="header-container">
            <a href="services.php" class="site-logo">
                <span class="logo-icon">🦷</span>
                <span>NALI Dental</span>
            </a>
            
            <button class="menu-toggle" id="menuToggle" onclick="toggleMobileMenu()" aria-label="Menu">
                <i class="fas fa-bars" id="menuIcon"></i>
            </button>
            
            <nav class="main-nav" id="mainNav">
                <button class="menu-close" onclick="closeMobileMenu()" aria-label="Đóng menu">
                    <i class="fas fa-times"></i>
                </button>
                <?php foreach ($navItems as $item): ?>
                    <a href="<?php echo $item['href']; ?>" 
                       class="nav-link <?php echo ($currentPage === $item['key']) ? 'active' : ''; ?>">
                        <i class="fas <?php echo $item['icon']; ?>"></i>
                        <?php echo $item['label']; ?>
                    </a>
                <?php endforeach; ?>
                
                <?php if ($isLoggedIn): ?>
                    <div class="user-menu">
                        <span class="user-name">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($userName); ?>
                        </span>
                        <?php if ($userRole === 'admin'): ?>
                            <a href="admin_panel.php" class="nav-link" style="background: #28a745; color: white;">
                                <i class="fas fa-cog"></i> Admin
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link btn-logout-mobile">
                            <i class="fas fa-sign-out-alt"></i> Đăng xuất
                        </a>
                    </div>
                <?php else: ?>
                    <a href="auth.php" class="nav-link btn-login">
                        <i class="fas fa-sign-in-alt"></i> Đăng nhập
                    </a>
                <?php endif; ?>
            </nav>
            
            <!-- Overlay cho mobile menu -->
            <div class="menu-overlay" id="menuOverlay" onclick="closeMobileMenu()"></div>
        </div>
    </header>
    
    <style>
    /* Mobile menu enhancements */
    .menu-close {
        display: none;
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        z-index: 1001;
    }
    
    .menu-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 998;
    }
    
    @media (max-width: 768px) {
        .menu-close {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .menu-overlay.show {
            display: block;
        }
        
        .main-nav .nav-link i {
            width: 24px;
            margin-right: 8px;
        }
        
        .btn-logout-mobile {
            background: rgba(255,100,100,0.2) !important;
            border: 1px solid rgba(255,100,100,0.3) !important;
        }
    }
    </style>
    
    <script>
    function toggleMobileMenu() {
        const nav = document.getElementById('mainNav');
        const overlay = document.getElementById('menuOverlay');
        const icon = document.getElementById('menuIcon');
        
        nav.classList.toggle('show');
        overlay.classList.toggle('show');
        
        // Ngăn scroll body khi menu mở
        document.body.style.overflow = nav.classList.contains('show') ? 'hidden' : '';
    }
    
    function closeMobileMenu() {
        const nav = document.getElementById('mainNav');
        const overlay = document.getElementById('menuOverlay');
        
        nav.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Đóng menu khi nhấn vào link
    document.querySelectorAll('.main-nav .nav-link').forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });
    
    // Đóng menu khi nhấn nút Back
    window.addEventListener('popstate', closeMobileMenu);
    </script>
    <?php
}

/**
 * Render Footer
 */
function renderFooter() {
    ?>
    <footer class="site-footer">
        <div class="footer-main">
            <div class="footer-brand">
                <div class="logo">
                    <img src="images/Gemini_Generated_Image_krshx6krshx6krsh.png" alt="NALI Dental Clinic Logo" style="height: 60px; margin-bottom: 10px;">
                </div>
                <div class="logo" style="margin-bottom: 15px;">
                    <span>🦷</span>
                    <span>NALI Dental Clinic</span>
                </div>
                <p>Hệ thống Nha khoa Công nghệ cao tích hợp AI - Mang đến nụ cười hoàn hảo cho bạn.</p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Youtube"><i class="fab fa-youtube"></i></a>
                    <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
            
            <div class="footer-section footer-contact">
                <h4>Liên hệ</h4>
                <p>
                    <i class="fas fa-phone"></i>
                    <span style="color: #ff6b6b; font-weight: bold;">0945 457 512</span>
                </p>
                <p>
                    <i class="fas fa-envelope"></i>
                    <span>nalidental@gmail.com</span>
                </p>
                <p>
                    <i class="fas fa-clock"></i>
                    <span>T2 - CN: 08:00 - 20:00</span>
                </p>
            </div>
            
            <div class="footer-section">
                <h4>Chi nhánh</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-hospital"></i> <strong>Bình Thạnh:</strong> 69/68 Đặng Thùy Trâm</a></li>
                    <li><a href="#"><i class="fas fa-hospital"></i> <strong>Quận 1:</strong> 123 Nguyễn Huệ</a></li>
                    <li><a href="#"><i class="fas fa-hospital"></i> <strong>Gò Vấp:</strong> 456 Quang Trung</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> NALI Dental Clinic. All rights reserved.</p>
        </div>
    </footer>
    <?php
}
?>
