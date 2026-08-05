<?php
require_once 'includes/components.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiến Thức Nha Khoa - NALI Dental Clinic</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* ========== CSS RIÊNG CHO TRANG TIN TỨC ========== */
        body { background-color: #f8f9fa; }

        .news-header {
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 50px 20px;
        }

        /* Bài viết nổi bật (Featured) */
        .featured-post {
            display: flex;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 50px;
            transition: transform 0.3s;
        }
        .featured-post:hover { transform: translateY(-5px); }
        
        .featured-img {
            flex: 1;
            min-height: 350px;
            object-fit: cover;
        }
        .featured-content {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .badge {
            display: inline-block;
            background-color: #e7f1ff;
            color: #4da6ff;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 15px;
            width: fit-content;
        }
        .featured-content h2 { color: #333; font-size: 2rem; margin-bottom: 15px; }
        .featured-content p { color: #666; font-size: 1.1rem; line-height: 1.6; margin-bottom: 25px; }
        .read-more {
            display: inline-block;
            text-decoration: none;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 10px 20px;
            background: linear-gradient(135deg, #4da6ff 0%, #3d8fe8 100%);
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(77, 166, 255, 0.3);
        }
        .read-more:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 166, 255, 0.5);
            background: linear-gradient(135deg, #3d8fe8 0%, #2c7ad1 100%);
        }
        .read-more i {
            margin-left: 5px;
            transition: transform 0.3s ease;
        }
        .read-more:hover i {
            transform: translateX(5px);
        }

        /* Lưới bài viết thường */
        .section-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 30px;
            border-left: 5px solid #4da6ff;
            padding-left: 15px;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .news-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .news-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .news-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease, opacity 0.3s ease;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .news-card:hover img {
            transform: scale(1.1);
        }
        .news-card-img-wrapper {
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        /* Lazy Loading Optimization */
        img[loading="lazy"] {
            opacity: 0;
            transition: opacity 0.3s ease-in;
        }
        img[loading="lazy"].loaded {
            opacity: 1;
        }
        
        /* Blur placeholder while loading */
        img[loading="lazy"]:not(.loaded) {
            filter: blur(10px);
            transform: scale(1.05);
        }

        .news-body { padding: 20px; }
        .news-body h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
            min-height: 60px;
            transition: color 0.3s ease;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.4;
        }
        .news-card:hover .news-body h3 {
            color: #4da6ff;
        }
        .news-meta { font-size: 0.9rem; color: #888; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .news-desc { font-size: 0.95rem; color: #666; margin-bottom: 20px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

        /* Search & Filter Section */
        .search-filter-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }

        .search-box {
            position: relative;
            margin-bottom: 25px;
            width: 100%;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }

        .search-box input {
            width: 100%;
            padding: 15px 90px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .search-box input:focus {
            border-color: #4da6ff;
            outline: none;
            box-shadow: 0 0 10px rgba(77, 166, 255, 0.2);
        }

        .search-box button {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: #4da6ff;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .search-box button:hover {
            background: #3d8fe8;
        }

        .category-filter {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .category-filter label {
            font-weight: 600;
            color: #333;
            margin-right: 10px;
        }

        .category-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            color: #666;
        }

        .category-btn:hover {
            border-color: #4da6ff;
            color: #4da6ff;
            transform: translateY(-2px);
        }

        .category-btn.active {
            background: #4da6ff;
            color: white;
            border-color: #4da6ff;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-results i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-results h3 {
            color: #666;
            margin-bottom: 10px;
        }

        /* Responsive - Mobile Optimized */
        @media (max-width: 992px) {
            .news-grid { grid-template-columns: repeat(2, 1fr); }
            .featured-post { flex-direction: column; }
            .featured-img { height: 250px; }
        }
        
        @media (max-width: 768px) {
            .news-header {
                height: 180px;
                margin-bottom: 25px;
            }
            
            .news-header h1 {
                font-size: 1.6rem;
            }
            
            .news-header p {
                font-size: 0.95rem;
            }
            
            .container {
                padding: 0 16px 40px;
            }
            
            .search-filter-section {
                padding: 20px 15px;
                margin-bottom: 25px;
            }
            
            .search-box {
                margin-bottom: 20px;
            }
            
            .search-box input {
                padding: 14px 80px 14px 16px;
                font-size: 16px;
            }
            
            .search-box button {
                padding: 14px 16px;
            }
            
            .category-filter {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .category-filter label {
                width: 100%;
                margin-bottom: 8px;
            }
            
            .category-btn {
                padding: 10px 14px;
                font-size: 13px;
                flex: 1;
                min-width: calc(33.33% - 8px);
                text-align: center;
            }
            
            .featured-post {
                margin-bottom: 30px;
            }
            
            .featured-img {
                min-height: 200px;
                height: 200px;
            }
            
            .featured-content {
                padding: 25px 20px;
            }
            
            .featured-content h2 {
                font-size: 1.4rem;
            }
            
            .featured-content p {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.4rem;
                margin-bottom: 20px;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .news-card img {
                height: 180px;
            }
            
            .news-body {
                padding: 18px;
            }
            
            .news-body h3 {
                font-size: 1.1rem;
                min-height: auto;
            }
            
            .read-more {
                padding: 12px 20px;
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 375px) {
            .news-header h1 {
                font-size: 1.4rem;
            }
            
            .category-btn {
                min-width: calc(50% - 8px);
                font-size: 12px;
            }
            
            .featured-content h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>

<?php renderHeader('news'); ?>

    <div class="news-header">
        <div>
            <h1>Cẩm Nang Nha Khoa</h1>
            <p>Cập nhật kiến thức chăm sóc răng miệng & Công nghệ mới nhất</p>
        </div>
    </div>

    <div class="container">
        
        <!-- Search & Filter Section -->
        <div class="search-filter-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Tìm kiếm bài viết... (VD: đau răng, niềng răng, implant)" />
                <button onclick="filterNews()">
                    <i class="fas fa-search"></i> Tìm
                </button>
            </div>
            
            <div class="category-filter">
                <label>Lọc theo chủ đề:</label>
                <button class="category-btn active" data-category="all" onclick="filterByCategory('all')">
                    Tất cả
                </button>
                <button class="category-btn" data-category="công nghệ 4.0" onclick="filterByCategory('công nghệ 4.0')">
                    AI & Công nghệ
                </button>
                <button class="category-btn" data-category="chỉnh nha" onclick="filterByCategory('chỉnh nha')">
                    Niềng răng
                </button>
                <button class="category-btn" data-category="phục hình" onclick="filterByCategory('phục hình')">
                    Răng sứ & Implant
                </button>
                <button class="category-btn" data-category="thẩm mỹ" onclick="filterByCategory('thẩm mỹ')">
                    Tẩy trắng
                </button>
                <button class="category-btn" data-category="nha khoa trẻ em" onclick="filterByCategory('nha khoa trẻ em')">
                    Trẻ em
                </button>
                <button class="category-btn" data-category="bệnh lý" onclick="filterByCategory('bệnh lý')">
                    Bệnh lý
                </button>
            </div>
        </div>
        
        <div class="featured-post">
            <div class="news-card-img-wrapper">
                <img src="images/news-ai.jpg" 
                     alt="AI trong Nha Khoa" 
                     class="featured-img" 
                     width="800" 
                     height="350"
                     onerror="this.src='https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
            </div>
            <div class="featured-content">
                <span class="badge">Công Nghệ 4.0</span>
                <h2>Ứng dụng AI trong Chẩn đoán hình ảnh tại NALI Dental</h2>
                <p>Trí tuệ nhân tạo (AI) đang tạo ra cuộc cách mạng trong ngành nha khoa. Tại NALI, chúng tôi sử dụng AI để phân tích phim X-quang, phát hiện sâu răng sớm và mô phỏng kết quả niềng răng chỉ trong 30 giây...</p>
                <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <h2 class="section-title">Bài Viết Mới Nhất</h2>
        
        <!-- No results message -->
        <div class="no-results" id="noResults" style="display: none;">
            <i class="fas fa-search"></i>
            <h3>Không tìm thấy bài viết phù hợp</h3>
            <p>Vui lòng thử từ khóa khác hoặc chọn danh mục khác</p>
        </div>
        
        <div class="news-grid" id="newsGrid">
            
            <div class="news-card" data-category="chỉnh nha" data-keywords="niềng răng invisalign chỉnh nha trong suốt mắc cài">
                <div class="news-card-img-wrapper">
                    <img src="images/news-nieng-rang.jpg" 
                         alt="Niềng răng" 
                         loading="lazy" 
                         width="400" 
                         height="200"
                         onerror="this.src='https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
                </div>
                <div class="news-body">
                    <div class="news-meta">
                        <span><i class="far fa-folder"></i> Chỉnh nha</span>
                        <span><i class="far fa-calendar"></i> 20/12/2025</span>
                    </div>
                    <h3>Niềng răng trong suốt Invisalign: Có thực sự hiệu quả?</h3>
                    <p class="news-desc">So sánh ưu nhược điểm giữa niềng răng mắc cài truyền thống và khay niềng trong suốt...</p>
                    <a href="#" class="read-more">Xem chi tiết</a>
                </div>
            </div>

            <div class="news-card" data-category="phục hình" data-keywords="implant trồng răng mất răng cấy ghép phục hình tiêu xương">
                <div class="news-card-img-wrapper">
                    <img src="images/news-implant.jpg" 
                         alt="Implant" 
                         loading="lazy" 
                         width="400" 
                         height="200"
                         onerror="this.src='https://images.unsplash.com/photo-1606811841689-23dfddce3e95?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
                </div>
                <div class="news-body">
                    <div class="news-meta">
                        <span><i class="far fa-folder"></i> Phục hình</span>
                        <span><i class="far fa-calendar"></i> 18/12/2025</span>
                    </div>
                    <h3>Mất răng lâu năm: Tại sao nên trồng Implant sớm?</h3>
                    <p class="news-desc">Hậu quả của việc tiêu xương hàm khi mất răng và giải pháp cấy ghép Implant vĩnh viễn...</p>
                    <a href="#" class="read-more">Xem chi tiết</a>
                </div>
            </div>

            <div class="news-card" data-category="thẩm mỹ" data-keywords="tẩy trắng răng laser whitening răng trắng thẩm mỹ">
                <div class="news-card-img-wrapper">
                    <img src="images/news-tay-trang.jpg" 
                         alt="Tẩy trắng" 
                         loading="lazy" 
                         width="400" 
                         height="200"
                         onerror="this.src='https://images.unsplash.com/photo-1609840114035-3c981b782dfe?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
                </div>
                <div class="news-body">
                    <div class="news-meta">
                        <span><i class="far fa-folder"></i> Thẩm mỹ</span>
                        <span><i class="far fa-calendar"></i> 15/12/2025</span>
                    </div>
                    <h3>Tẩy trắng răng bằng Laser: An toàn tuyệt đối</h3>
                    <p class="news-desc">Công nghệ Laser Whitening giúp răng trắng sáng bật 3 tông chỉ sau 45 phút điều trị...</p>
                    <a href="#" class="read-more">Xem chi tiết</a>
                </div>
            </div>

            <div class="news-card" data-category="nha khoa trẻ em" data-keywords="trẻ em bé nha khoa trẻ em răng sữa khám răng đầu tiên">
                <div class="news-card-img-wrapper">
                    <img src="images/news-children.jpg" 
                         alt="Trẻ em" 
                         loading="lazy" 
                         width="400" 
                         height="200"
                         onerror="this.src='https://images.unsplash.com/photo-1596495577886-d920f1fb7238?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
                </div>
                <div class="news-body">
                    <div class="news-meta">
                        <span><i class="far fa-folder"></i> Nha khoa trẻ em</span>
                        <span><i class="far fa-calendar"></i> 10/12/2025</span>
                    </div>
                    <h3>Khi nào nên cho bé đi khám răng lần đầu?</h3>
                    <p class="news-desc">Các chuyên gia khuyến cáo nên đưa trẻ đi khám ngay khi chiếc răng sữa đầu tiên mọc lên...</p>
                    <a href="#" class="read-more">Xem chi tiết</a>
                </div>
            </div>

            <div class="news-card" data-category="bệnh lý" data-keywords="sâu răng đau răng viêm tủy bệnh lý nha khoa">
                <div class="news-card-img-wrapper">
                    <img src="images/news-sau-rang.jpg" 
                         alt="Sâu răng" 
                         loading="lazy" 
                         width="400" 
                         height="200"
                         onerror="this.src='https://images.unsplash.com/photo-1628177142898-93e36e4e3a50?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
                </div>
                <div class="news-body">
                    <div class="news-meta">
                        <span><i class="far fa-folder"></i> Bệnh lý</span>
                        <span><i class="far fa-calendar"></i> 05/12/2025</span>
                    </div>
                    <h3>5 thói quen xấu hàng ngày đang phá hủy men răng của bạn</h3>
                    <p class="news-desc">Uống nước đá, chải răng quá mạnh, hay ăn đồ chua... là những nguyên nhân hàng đầu...</p>
                    <a href="#" class="read-more">Xem chi tiết</a>
                </div>
            </div>

            <div class="news-card" style="background-color: #e7f1ff; border: 2px dashed #4da6ff; display: flex; align-items: center; justify-content: center; text-align: center;">
                <div class="news-body">
                    <i class="fas fa-robot" style="font-size: 3rem; color: #4da6ff; margin-bottom: 20px;"></i>
                    <h3>Bạn có câu hỏi khác?</h3>
                    <p style="margin-bottom: 20px;">Hỏi ngay trợ lý ảo NALI AI để được giải đáp trong 3 giây!</p>
                    <button onclick="alert('Hãy bấm vào icon Chat ở góc màn hình nhé!')" style="padding: 10px 20px; background: #4da6ff; color: white; border: none; border-radius: 5px; cursor: pointer;">Chat Ngay</button>
                </div>
            </div>

        </div>
    </div>

<?php renderFooter(); ?>

    <script>
    // ========== LAZY LOADING IMAGE OPTIMIZATION ==========
    
    // Add 'loaded' class when images finish loading for smooth fade-in effect
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        
        lazyImages.forEach(img => {
            // If image already loaded (cached)
            if (img.complete) {
                img.classList.add('loaded');
            } else {
                // Add loaded class when image loads
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
            }
        });
        
        // Fallback for browsers that don't support native lazy loading
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px' // Start loading 50px before image enters viewport
            });
            
            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        }
    });
    </script>
    
    <script>
    // ========== SEARCH & FILTER FUNCTIONALITY ==========
    
    let currentCategory = 'all';
    
    // Filter by category
    function filterByCategory(category) {
        currentCategory = category;
        
        // Update active button
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // Clear search input
        document.getElementById('searchInput').value = '';
        
        // Filter news
        filterNews();
    }
    
    // Search and filter function
    function filterNews() {
        const searchQuery = document.getElementById('searchInput').value.toLowerCase().trim();
        const newsCards = document.querySelectorAll('.news-card');
        const noResults = document.getElementById('noResults');
        let visibleCount = 0;
        
        newsCards.forEach(card => {
            // Skip the AI chat card
            if (card.querySelector('.fas.fa-robot')) {
                return;
            }
            
            const category = card.getAttribute('data-category') || '';
            const keywords = card.getAttribute('data-keywords') || '';
            const title = card.querySelector('h3') ? card.querySelector('h3').textContent.toLowerCase() : '';
            const desc = card.querySelector('.news-desc') ? card.querySelector('.news-desc').textContent.toLowerCase() : '';
            
            // Check category match
            const categoryMatch = currentCategory === 'all' || category === currentCategory;
            
            // Check search match
            const searchMatch = !searchQuery || 
                                title.includes(searchQuery) || 
                                desc.includes(searchQuery) || 
                                keywords.includes(searchQuery) ||
                                category.includes(searchQuery);
            
            // Show/hide card
            if (categoryMatch && searchMatch) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (visibleCount === 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }
    
    // Real-time search
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        
        searchInput.addEventListener('input', function() {
            filterNews();
        });
        
        // Allow Enter key to search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filterNews();
            }
        });
    });
    </script>
    
    <script>
    // Mark active navigation link based on current page    document.addEventListener('DOMContentLoaded', function() {
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
    // SweetAlert2 logout handler
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
    <script>
    // AJAX logout handler for all pages
    document.addEventListener('DOMContentLoaded', function() {
        var logoutBtn = document.getElementById('logoutBtn');
        // Removed legacy confirm() logout handler. Only SweetAlert2 is used now.
            });
        }
    });
    </script>
</body>
</html>
