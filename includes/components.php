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
    <script>/* Áp dark mode sớm (chống nháy sáng) */(function(){try{var t=localStorage.getItem('nali-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
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

    <!-- Nút chuyển Dark Mode / Light Mode -->
    <button id="naliThemeToggle" aria-label="Chuyển chế độ tối/sáng" title="Chế độ tối / sáng">
        <i class="fas fa-moon"></i>
    </button>
    <script>
    (function () {
        var btn = document.getElementById('naliThemeToggle');
        if (!btn) return;
        var icon = btn.querySelector('i');
        function sync() {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
        }
        sync();
        btn.addEventListener('click', function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (dark) { document.documentElement.removeAttribute('data-theme'); try { localStorage.setItem('nali-theme', ''); } catch (e) {} }
            else { document.documentElement.setAttribute('data-theme', 'dark'); try { localStorage.setItem('nali-theme', 'dark'); } catch (e) {} }
            sync();
        });
    })();
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
    // Nhúng widget Trợ lý AI (chatbot RAG + đặt lịch) trên mọi trang
    $__aiWidget = __DIR__ . '/../ai_chat_widget.php';
    if (is_file($__aiWidget)) {
        include $__aiWidget;
    }
    ?>
    <!-- Thanh tiến trình cuộn + nút về đầu trang -->
    <div id="naliProgress"></div>
    <button id="naliTop" aria-label="Về đầu trang" title="Về đầu trang"><i class="fas fa-arrow-up"></i></button>
    <script>
    (function () {
        var prog = document.getElementById('naliProgress');
        var topBtn = document.getElementById('naliTop');
        var header = document.querySelector('.site-header');
        function onScroll() {
            var st = window.pageYOffset || document.documentElement.scrollTop;
            var h = document.documentElement.scrollHeight - window.innerHeight;
            if (prog) prog.style.width = (h > 0 ? (st / h) * 100 : 0) + '%';
            if (header) header.classList.toggle('scrolled', st > 40);
            if (topBtn) topBtn.classList.toggle('show', st > 400);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        onScroll();
        if (topBtn) topBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();
    </script>
    <script>
    /* Hiệu ứng cuộn hiện dần + đếm số. Progressive enhancement:
       JS gắn .reveal để ẩn rồi hiện; nếu JS/IO lỗi, nội dung vẫn hiển thị. */
    (function () {
        // CHỈ reveal các thẻ nội dung nằm DƯỚI màn hình đầu (không đụng hero/stats
        // để không bao giờ ẩn nội dung trên cùng).
        var SEL = '.service-card,.why-card,.review-card,.doctor-card,.news-card,' +
                  '.article-card,.about-section,.cta-band,.knowledge-card,.info-card';
        // Chỉ ẩn-để-reveal các phần tử ĐANG Ở DƯỚI viewport -> tránh nháy phần trên.
        var vh = window.innerHeight || 800;
        var els = [].slice.call(document.querySelectorAll(SEL)).filter(function (el) {
            if (el.getBoundingClientRect().top < vh * 0.9) return false; // đang hiện -> giữ nguyên
            el.classList.add('reveal');
            return true;
        });

        function reveal(el) { el.classList.add('in'); }

        if (els.length && 'IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) { reveal(e.target); io.unobserve(e.target); }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -5% 0px' });
            els.forEach(function (el) { io.observe(el); });
            // Bảo hiểm: nếu IO không kích hoạt, ép hiện (không bao giờ để ẩn nội dung)
            setTimeout(function () { els.forEach(reveal); }, 6000);
        } else {
            els.forEach(reveal);
        }

        // Đếm số cho thanh thống kê (LUÔN hiển thị, chỉ chạy hiệu ứng đếm lên)
        [].slice.call(document.querySelectorAll('.stat .num')).forEach(function (node) {
            var m = node.textContent.trim().match(/^(\d[\d.,]*)(.*)$/);
            if (!m) return;
            var target = parseFloat(m[1].replace(/[.,]/g, '')), suffix = m[2] || '';
            if (!target || target > 1000000) return;
            var dur = 1200, start = null, finalText = m[1] + suffix;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                node.textContent = Math.floor((0.5 - Math.cos(p * Math.PI) / 2) * target) + suffix;
                if (p < 1) requestAnimationFrame(step); else node.textContent = finalText;
            }
            requestAnimationFrame(step);
        });
    })();
    </script>
    <?php
}
?>
