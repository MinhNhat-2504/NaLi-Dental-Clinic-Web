<?php
require_once 'includes/components.php';
require_once 'config.php';
require_once 'content_repository.php';
ensureContentSchema($conn);
$publicFaqs = contentRows($conn, "SELECT question, answer FROM faqs WHERE is_active=1 ORDER BY sort_order,id");
$approvedReviews = contentRows($conn, "SELECT name, rating, type, message FROM feedback WHERE status='approved' ORDER BY created_at DESC LIMIT 6");

// Bắt tín hiệu group trên URL
$group = $_GET['group'] ?? '';
$groupNames = [
    'children' => 'Nha Khoa Trẻ Em',
    'adults' => 'Nha Khoa Người Lớn',
    'elderly' => 'Nha Khoa Người Cao Tuổi',
    'chronic' => 'Nha Khoa Bệnh Lý Nền'
];
$title = $groupNames[$group] ?? 'Dịch Vụ Nha Khoa';

if ($group) {
    $sql = "SELECT * FROM products WHERE target_group = '" . $conn->real_escape_string($group) . "' AND COALESCE(is_active,1)=1";
} else {
    $sql = "SELECT * FROM products WHERE COALESCE(is_active,1)=1";
}
$result = $conn->query($sql);

$allServices = [];
$totalServices = 0;
if ($result && $result->num_rows > 0) {
    while ($service = $result->fetch_assoc()) {
        $allServices[$service['target_group']][] = $service;
        $totalServices++;
    }
}

$groups = [
    'children' => ['label' => 'Trẻ Em', 'icon' => '👶', 'color' => '#ff9f43', 'grad' => 'linear-gradient(135deg,#ffb15e,#f39c12)'],
    'adults'   => ['label' => 'Người Lớn', 'icon' => '👨‍💼', 'color' => '#4da6ff', 'grad' => 'linear-gradient(135deg,#4da6ff,#2c7ad1)'],
    'elderly'  => ['label' => 'Người Cao Tuổi', 'icon' => '👴', 'color' => '#26de81', 'grad' => 'linear-gradient(135deg,#2bd47e,#12a85f)'],
    'chronic'  => ['label' => 'Bệnh Lý Nền', 'icon' => '🏥', 'color' => '#a55eea', 'grad' => 'linear-gradient(135deg,#b06bf0,#8854d0)'],
];

/**
 * Ảnh placeholder yếu trong repo -> sẽ thay bằng ô gradient + icon cho "có chủ đích".
 * Nếu sau này bạn bỏ ẢNH THẬT vào images/ với đúng tên, hãy xoá tên đó khỏi danh sách này.
 */
function isWeakImage($img) {
    $weak = ['tram-rang-sua.jpg','nieng-rang-tre.jpg','fluoride.jpg','ham-gia.jpg',
             'nha-chu.jpg','tu-van-cao-tuoi.jpg','default.jpg',''];
    return in_array($img ?? '', $weak, true);
}

/** Chọn icon nha khoa phù hợp theo tên dịch vụ */
function svcIcon($name) {
    $n = mb_strtolower($name, 'UTF-8');
    if (mb_strpos($n, 'niềng') !== false || mb_strpos($n, 'trẻ') !== false) return 'fa-child';
    if (mb_strpos($n, 'fluoride') !== false || mb_strpos($n, 'ngừa') !== false) return 'fa-shield-heart';
    if (mb_strpos($n, 'nha chu') !== false) return 'fa-notes-medical';
    if (mb_strpos($n, 'tư vấn') !== false) return 'fa-user-doctor';
    if (mb_strpos($n, 'cao răng') !== false || mb_strpos($n, 'sạch') !== false) return 'fa-broom';
    return 'fa-tooth';
}

/** Chọn ảnh AI riêng cho sáu dịch vụ chính; các dịch vụ khác giữ ảnh riêng từ dữ liệu. */
function serviceCardImage($service) {
    $images = [
        'Tẩy trắng răng Laser' => 'service-whitening-ai.webp',
        'Bọc răng sứ Titan' => 'service-veneer-ai.webp',
        'Niềng răng Invisalign' => 'service-aligner-ai.webp',
        'Cấy ghép Implant' => 'service-implant-ai.webp',
        'Nhổ răng khôn' => 'service-extraction-ai.webp',
        'Điều trị tủy răng' => 'service-root-canal-ai.webp',
    ];
    $name = $service['name'] ?? '';
    return $images[$name] ?? ($service['image'] ?? 'service-whitening-ai.webp');
}

function renderServiceCard($service, $groups) {
    $g = $groups[$service['target_group']] ?? ['label'=>'Dịch vụ','color'=>'#4da6ff'];
    $imageFile = serviceCardImage($service);
    $imgSrc = 'images/' . $imageFile;
    $price = (float)$service['price'];
    $duration = (int)($service['duration'] ?? 30);
    $weak = false;
    $priceText = $price > 0 ? number_format($price, 0, ',', '.') . 'đ' : 'Miễn phí tư vấn';
    ?>
    <div class="service-card"
         data-name="<?php echo htmlspecialchars($service['name']); ?>"
         data-desc="<?php echo htmlspecialchars($service['description']); ?>"
         data-price="<?php echo htmlspecialchars($priceText); ?>"
         data-dur="<?php echo $duration; ?>"
        data-img="<?php echo htmlspecialchars($imgSrc); ?>"
         data-grad="<?php echo htmlspecialchars($g['grad']); ?>"
         data-icon="<?php echo svcIcon($service['name']); ?>">
        <div class="service-thumb">
            <?php if ($weak): ?>
                <div class="thumb-fallback" style="background: <?php echo $g['grad']; ?>">
                    <i class="fas <?php echo svcIcon($service['name']); ?>"></i>
                </div>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" loading="lazy"
                     onerror="this.onerror=null;this.src='images/logo.png';">
            <?php endif; ?>
        </div>
        <div class="service-card-content">
            <div class="svc-meta">
                <span class="dur"><i class="far fa-clock"></i> <?php echo $duration; ?>′</span>
                <span class="svc-group"><?php echo htmlspecialchars($g['label']); ?></span>
            </div>
            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
            <p><?php echo htmlspecialchars($service['description']); ?></p>
            <div class="svc-foot">
                <div class="price-box">
                    <?php if ($price > 0): ?>
                        <small>Chỉ từ</small>
                        <span class="price"><?php echo number_format($price, 0, ',', '.'); ?>đ</span>
                    <?php else: ?>
                        <span class="price free">Miễn phí tư vấn</span>
                    <?php endif; ?>
                </div>
                <a href="contact.php" class="order-btn" title="Đặt lịch dịch vụ này">
                    <i class="fas fa-calendar-check"></i> Đặt lịch
                </a>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - NALI Dental Clinic</title>
    <?php renderSeo($title . ' - NALI Dental Clinic', 'Tham khảo dịch vụ nha khoa, mức giá và thời lượng dự kiến; gửi yêu cầu đặt lịch trực tuyến cùng NALI Dental.'); ?>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="content-pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --nali: #4da6ff; --nali-dark: #2c7ad1; --ink: #1a2b47; }
        body { font-family: 'Be Vietnam Pro', 'Segoe UI', sans-serif; background: #f4f8fd; color: var(--ink); }

        /* ===== HERO ===== */
        .hero {
            position: relative;
            background:
                radial-gradient(1200px 500px at 85% -10%, rgba(255,255,255,.18), transparent 60%),
                linear-gradient(135deg, rgba(77,166,255,.90) 0%, rgba(61,143,232,.90) 45%, rgba(37,103,201,.93) 100%),
                url('images/hero-tech.webp') right center/cover no-repeat;
            color: #fff; overflow: hidden; padding: 70px 20px 90px;
        }
        .hero::before, .hero::after {
            content: ''; position: absolute; border-radius: 50%; filter: blur(2px); opacity: .18; background: #fff;
        }
        .hero::before { width: 340px; height: 340px; top: -120px; left: -80px; }
        .hero::after { width: 220px; height: 220px; bottom: -90px; right: 8%; opacity: .12; }
        .hero-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1.15fr .85fr; gap: 40px; align-items: center; position: relative; z-index: 2; }
        .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3); padding: 8px 16px; border-radius: 30px; font-size: .85rem; font-weight: 600; margin-bottom: 20px; backdrop-filter: blur(6px); }
        .hero h1 { font-size: 3rem; line-height: 1.15; font-weight: 800; margin-bottom: 18px; letter-spacing: -.5px; }
        .hero h1 span { color: #ffe27a; }
        .hero p.lead { font-size: 1.15rem; opacity: .95; max-width: 520px; margin-bottom: 30px; line-height: 1.7; }
        .hero-cta { display: flex; gap: 14px; flex-wrap: wrap; }
        .btn-hero { display: inline-flex; align-items: center; gap: 10px; padding: 15px 30px; border-radius: 40px; font-weight: 700; font-size: 1rem; transition: .25s; border: 2px solid transparent; }
        .btn-hero.primary { background: #fff; color: var(--nali-dark); box-shadow: 0 12px 30px rgba(0,0,0,.2); }
        .btn-hero.primary:hover { transform: translateY(-3px); box-shadow: 0 18px 40px rgba(0,0,0,.28); }
        .btn-hero.ghost { border-color: rgba(255,255,255,.6); color: #fff; }
        .btn-hero.ghost:hover { background: rgba(255,255,255,.15); }
        .hero-visual { display: flex; justify-content: center; }
        .hero-card {
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.28); border-radius: 24px;
            padding: 28px; backdrop-filter: blur(10px); width: 100%; max-width: 360px; box-shadow: 0 20px 50px rgba(0,0,0,.2);
        }
        .hero-card .doc { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
        .hero-card .doc-ava { width: 56px; height: 56px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .hero-card .doc b { display: block; font-size: 1.05rem; }
        .hero-card .doc small { opacity: .85; }
        .hero-card .row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid rgba(255,255,255,.2); font-size: .95rem; }
        .hero-card .row i { width: 22px; color: #ffe27a; }

        /* ===== STATS BAR ===== */
        .stats-bar { max-width: 1120px; margin: -50px auto 0; position: relative; z-index: 5; background: #fff; border-radius: 20px; box-shadow: 0 15px 45px rgba(45,100,180,.15); display: grid; grid-template-columns: repeat(4,1fr); padding: 30px 20px; }
        .stat { text-align: center; padding: 10px; }
        .stat .num { font-size: 2.1rem; font-weight: 800; color: var(--nali); line-height: 1; }
        .stat .lbl { color: #64748b; font-size: .9rem; margin-top: 8px; }
        .stat + .stat { border-left: 1px solid #eef3f9; }

        /* ===== SECTIONS ===== */
        .section { max-width: 1200px; margin: 0 auto; padding: 70px 20px 0; }
        .section-head { text-align: center; max-width: 640px; margin: 0 auto 45px; }
        .section-head .eyebrow { color: var(--nali); font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; font-size: .82rem; }
        .section-head h2 { font-size: 2.1rem; font-weight: 800; margin: 10px 0 12px; }
        .section-head p { color: #64748b; font-size: 1.05rem; line-height: 1.7; }

        /* Filter */
        .group-filter { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 45px; }
        .filter-btn { padding: 11px 22px; border: 2px solid #e3ecf7; background: #fff; color: #475569; border-radius: 30px; cursor: pointer; font-size: .95rem; font-weight: 600; transition: .25s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .filter-btn:hover { border-color: var(--nali); color: var(--nali); transform: translateY(-2px); }
        .filter-btn.active { background: linear-gradient(135deg,var(--nali),var(--nali-dark)); color: #fff; border-color: transparent; box-shadow: 0 8px 20px rgba(77,166,255,.35); }

        .group-title { font-size: 1.45rem; font-weight: 700; margin: 45px 0 24px; display: flex; align-items: center; gap: 12px; }
        .group-title .icon { font-size: 1.6rem; }
        .group-title .line { flex: 1; height: 1px; background: #e3ecf7; }
        .group-title a { font-size: .9rem; color: var(--nali); text-decoration: none; font-weight: 600; }

        /* Grid + cards */
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 26px; }
        .service-card { background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 6px 22px rgba(45,100,180,.08); transition: .3s; border: 1px solid #eef3fa; display: flex; flex-direction: column; }
        .service-card:hover { transform: translateY(-8px); box-shadow: 0 22px 45px rgba(45,100,180,.18); }
        .service-thumb { position: relative; height: 200px; overflow: hidden; }
        .service-thumb img { width: 100%; height: 100%; object-fit: cover; transition: .5s; }
        .service-card:hover .service-thumb img { transform: scale(1.07); }
        /* Ô gradient thay ảnh yếu: icon lớn + hoa văn răng mờ -> trông "có chủ đích" */
        .thumb-fallback { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .thumb-fallback i { font-size: 4.6rem; color: rgba(255,255,255,.95); filter: drop-shadow(0 8px 18px rgba(0,0,0,.18)); z-index: 1; transition: transform .5s; }
        .service-card:hover .thumb-fallback i { transform: scale(1.1) rotate(-4deg); }
        .thumb-fallback::before {
            content: "\f5c9"; /* fa-tooth */ font-family: "Font Awesome 6 Free"; font-weight: 900;
            position: absolute; font-size: 12rem; color: rgba(255,255,255,.13);
            right: -26px; bottom: -34px; line-height: 1; transform: rotate(-12deg);
        }
        .thumb-fallback::after {
            content: "\f5c9"; font-family: "Font Awesome 6 Free"; font-weight: 900;
            position: absolute; font-size: 5rem; color: rgba(255,255,255,.1);
            left: -14px; top: -16px; transform: rotate(14deg);
        }
        .cat-tag { position: absolute; top: 14px; left: 14px; color: #fff; font-size: .75rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
        .promo-tag { position: absolute; top: 14px; right: 14px; background: #ff4757; color: #fff; font-size: .72rem; font-weight: 700; padding: 5px 11px; border-radius: 20px; }
        .service-card-content { padding: 20px 22px 22px; display: flex; flex-direction: column; flex: 1; }
        .svc-meta { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: #94a3b8; margin-bottom: 10px; }
        .svc-meta .stars { color: #f59e0b; font-weight: 700; }
        .svc-meta .dur { margin-left: auto; }
        .service-card h3 { font-size: 1.18rem; font-weight: 700; margin-bottom: 8px; }
        .service-card p { color: #64748b; font-size: .92rem; line-height: 1.6; margin-bottom: 18px; flex: 1; }
        .svc-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .price-box small { display: block; color: #94a3b8; font-size: .72rem; }
        .price-box .price { color: var(--nali-dark); font-size: 1.3rem; font-weight: 800; }
        .price-box .price.free { font-size: 1rem; }
        .order-btn { background: linear-gradient(135deg,var(--nali),var(--nali-dark)); color: #fff; border: none; padding: 11px 18px; border-radius: 12px; font-size: .9rem; font-weight: 700; cursor: pointer; transition: .25s; white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; }
        .order-btn:hover { box-shadow: 0 8px 20px rgba(77,166,255,.45); transform: translateY(-2px); }

        /* Why choose */
        .why-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 24px; }
        .why-card { background: #fff; border-radius: 18px; padding: 30px 26px; box-shadow: 0 6px 22px rgba(45,100,180,.07); transition: .3s; border: 1px solid #eef3fa; }
        .why-card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(45,100,180,.15); }
        .why-ico { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #fff; margin-bottom: 18px; }
        .why-card h4 { font-size: 1.15rem; font-weight: 700; margin-bottom: 10px; }
        .why-card p { color: #64748b; font-size: .93rem; line-height: 1.65; }

        /* Testimonials */
        .reviews-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap: 24px; }
        .review-card { background: #fff; border-radius: 18px; padding: 28px; box-shadow: 0 6px 22px rgba(45,100,180,.07); border: 1px solid #eef3fa; }
        .review-card .quote { color: #334155; font-size: .98rem; line-height: 1.75; margin-bottom: 20px; font-style: italic; }
        .review-card .stars { color: #f59e0b; margin-bottom: 14px; }
        .reviewer { display: flex; align-items: center; gap: 12px; }
        .reviewer .ava { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg,var(--nali),var(--nali-dark)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; }
        .reviewer b { display: block; font-size: .95rem; }
        .reviewer small { color: #94a3b8; }

        /* CTA band */
        .cta-band { max-width: 1160px; margin: 75px auto 0; background: linear-gradient(135deg,#2c7ad1,#4da6ff); border-radius: 26px; padding: 55px 40px; text-align: center; color: #fff; position: relative; overflow: hidden; }
        .cta-band h2 { font-size: 2rem; font-weight: 800; margin-bottom: 12px; }
        .cta-band p { opacity: .95; margin-bottom: 26px; font-size: 1.05rem; }
        .cta-band .btn-hero.primary { color: var(--nali-dark); }

        .empty-message { text-align: center; color: #94a3b8; padding: 60px 20px; background: #fff; border-radius: 18px; }
        .empty-message i { font-size: 3.5rem; color: #dbe6f3; margin-bottom: 16px; }

        /* ===== Slider so sánh Trước / Sau ===== */
        .ba-wrap { position: relative; max-width: 900px; margin: 0 auto; border-radius: 20px; overflow: hidden;
                   box-shadow: 0 18px 45px rgba(45,100,180,.22); cursor: ew-resize; user-select: none; touch-action: none; }
        .ba-wrap img { width: 100%; display: block; pointer-events: none; }
        .ba-before { position: absolute; inset: 0; clip-path: inset(0 50% 0 0); }
        .ba-handle { position: absolute; top: 0; bottom: 0; left: 50%; width: 3px; background: #fff;
                     transform: translateX(-50%); box-shadow: 0 0 12px rgba(0,0,0,.35); pointer-events: none; }
        .ba-grab { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
                   width: 46px; height: 46px; border-radius: 50%; background: #fff; color: var(--nali-dark);
                   display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 18px rgba(0,0,0,.3); }
        .ba-label { position: absolute; bottom: 16px; padding: 6px 14px; border-radius: 20px; font-size: .78rem;
                    font-weight: 700; color: #fff; background: rgba(0,0,0,.55); pointer-events: none; }
        .ba-label.l { left: 16px; }
        .ba-label.r { right: 16px; background: rgba(77,166,255,.9); }

        /* ===== Modal xem nhanh dịch vụ ===== */
        .service-card { cursor: pointer; }
        .svc-modal { position: fixed; inset: 0; z-index: 10001; display: none; overflow-y: auto; }
        .svc-modal.open { display: block; }
        .svc-modal-bg { position: fixed; inset: 0; background: rgba(15,23,42,.72); }
        .svc-modal-box { position: relative; max-width: 700px; margin: 6vh auto 4vh; background: #fff;
                         border-radius: 20px; overflow: hidden; box-shadow: 0 30px 70px rgba(0,0,0,.35);
                         animation: svcPop .25s ease-out; }
        @keyframes svcPop { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        .svc-modal-media { height: 250px; }
        .svc-modal-media img { width: 100%; height: 100%; object-fit: cover; }
        .svc-modal-body { padding: 24px 28px 28px; }
        .svc-modal-body h3 { font-size: 1.5rem; font-weight: 800; margin: 10px 0 12px; }
        .svc-modal-body p { color: #64748b; line-height: 1.7; margin-bottom: 20px; }
        .svc-modal-foot { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .svc-modal-close { position: absolute; top: 14px; right: 14px; width: 40px; height: 40px; border: none;
                           border-radius: 50%; background: rgba(255,255,255,.92); color: #334155; font-size: 1.2rem;
                           cursor: pointer; z-index: 2; transition: .25s; }
        .svc-modal-close:hover { background: #ff4757; color: #fff; transform: rotate(90deg); }
        [data-theme="dark"] .svc-modal-box { background: #1e293b; }

        /* ===== Carousel cảm nhận khách hàng ===== */
        .rv-viewport { overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth;
                       scrollbar-width: none; -ms-overflow-style: none; }
        .rv-viewport::-webkit-scrollbar { display: none; }
        .rv-track { display: flex; gap: 24px; padding-bottom: 4px; }
        .rv-track .review-card { flex: 0 0 calc((100% - 48px) / 3); scroll-snap-align: start; }
        .rv-nav { display: flex; justify-content: center; gap: 12px; margin-top: 26px; }
        .rv-btn { width: 44px; height: 44px; border-radius: 50%; border: 1px solid #e3ecf7; background: #fff;
                  color: var(--nali-dark); cursor: pointer; font-size: 1rem; transition: .25s; }
        .rv-btn:hover { background: var(--nali); color: #fff; border-color: transparent; transform: translateY(-2px); }
        [data-theme="dark"] .rv-btn { background: #1e293b; border-color: #334155; color: #6cb2ff; }
        @media (max-width: 900px) { .rv-track .review-card { flex: 0 0 100%; } }

        /* ===== FAQ accordion ===== */
        .faq-list { max-width: 860px; margin: 0 auto; }
        .faq-item { background: #fff; border: 1px solid #eef3fa; border-radius: 14px; margin-bottom: 14px;
                    overflow: hidden; box-shadow: 0 4px 16px rgba(45,100,180,.06); transition: box-shadow .3s; }
        .faq-item.open { box-shadow: 0 10px 28px rgba(45,100,180,.14); }
        .faq-q { width: 100%; text-align: left; background: none; border: none; padding: 18px 22px;
                 font-size: 1.02rem; font-weight: 600; color: var(--ink); cursor: pointer; font-family: inherit;
                 display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .faq-q i { color: var(--nali); transition: transform .3s; flex-shrink: 0; }
        .faq-item.open .faq-q i { transform: rotate(180deg); }
        .faq-a { max-height: 0; overflow: hidden; transition: max-height .35s ease; }
        .faq-a p { padding: 0 22px 18px; margin: 0; color: #64748b; line-height: 1.75; }
        [data-theme="dark"] .faq-item { background: #1e293b; border-color: #334155; }
        [data-theme="dark"] .faq-q { color: #f1f5f9; }

        /* ===== Responsive ===== */
        @media (max-width: 900px) {
            .hero-inner { grid-template-columns: 1fr; text-align: center; }
            .hero-cta { justify-content: center; }
            .hero p.lead { margin-left: auto; margin-right: auto; }
            .hero-visual { display: none; }
            .stats-bar { grid-template-columns: repeat(2,1fr); gap: 10px; }
            .stat:nth-child(3) { border-left: none; }
            .stat { border-top: 1px solid #eef3f9; }
            .stat:nth-child(-n+2) { border-top: none; }
        }
        @media (max-width: 768px) {
            .hero { padding: 50px 18px 80px; }
            .hero h1 { font-size: 2.1rem; }
            .section { padding-top: 55px; }
            .section-head h2 { font-size: 1.7rem; }
            .services-grid { grid-template-columns: 1fr; }
            .cta-band { padding: 40px 22px; }
            .cta-band h2 { font-size: 1.5rem; }
        }
        @media (max-width: 480px) {
            .stats-bar { grid-template-columns: 1fr 1fr; }
            .hero h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <?php renderHeader('services'); ?>

    <!-- ===== HERO ===== -->
    <section class="hero">
        <div class="hero-inner">
            <div>
                <span class="hero-badge"><i class="fas fa-calendar-check"></i> Nền tảng tham khảo dịch vụ và đặt lịch nha khoa</span>
                <h1>Nụ cười khỏe đẹp bắt đầu từ <span>NALI Dental</span></h1>
                <p class="lead">Tham khảo dịch vụ, giá hiển thị và thời lượng dự kiến trước khi gửi yêu cầu đặt lịch. Chỉ định, chi phí và quy trình phù hợp sẽ được phòng khám xác nhận sau khi thăm khám.</p>
                <div class="hero-cta">
                    <a href="contact.php" class="btn-hero primary"><i class="fas fa-calendar-check"></i> Đặt lịch hẹn ngay</a>
                    <a href="#dichvu" class="btn-hero ghost"><i class="fas fa-tooth"></i> Khám phá dịch vụ</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-portrait-wrap">
                    <img class="hero-human-portrait" src="images/hero-human-ai.webp" alt="Hình minh hoạ bác sĩ tư vấn cùng khách hàng trong không gian nha khoa" fetchpriority="high">
                </div>
                <div class="hero-card">
                    <div class="doc">
                        <div class="doc-ava">🦷</div>
                        <div><b>NALI Dental Clinic</b><small>Thông tin dịch vụ và yêu cầu hẹn trực tuyến</small></div>
                    </div>
                    <div class="row"><i class="fas fa-list-check"></i> Xem thông tin và giá tham khảo theo từng dịch vụ</div>
                    <div class="row"><i class="fas fa-calendar-check"></i> Gửi yêu cầu đặt lịch trực tuyến</div>
                    <div class="row"><i class="fas fa-comments"></i> Nhận hỗ trợ nhanh từ trợ lý AI</div>
                    <div class="row"><i class="fas fa-circle-info"></i> Xác nhận chi phí và lịch hẹn trước khi điều trị</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== STATS ===== -->
    <div class="stats-bar">
        <div class="stat"><div class="num"><?php echo $totalServices; ?></div><div class="lbl">Dịch vụ đang hiển thị</div></div>
        <div class="stat"><div class="num"><?php echo count($groups); ?></div><div class="lbl">Nhóm chăm sóc</div></div>
        <div class="stat"><div class="num"><i class="fas fa-calendar-check"></i></div><div class="lbl">Đặt lịch trực tuyến</div></div>
        <div class="stat"><div class="num"><i class="fas fa-robot"></i></div><div class="lbl">Trợ lý AI hỗ trợ</div></div>
    </div>

    <section class="intro-media-band" aria-labelledby="introMediaTitle">
        <div class="intro-media-copy">
            <span class="eyebrow">Bắt đầu thật nhẹ nhàng</span>
            <h2 id="introMediaTitle">Hiểu quy trình trước khi đến khám</h2>
            <p>Video giới thiệu giúp khách lần đầu hình dung hành trình tại phòng khám. Hiện website dùng poster minh hoạ; thay bằng video quay thực tế đã được phê duyệt trước khi công khai.</p>
            <a class="btn-hero primary" href="#quytrinh"><i class="fas fa-play"></i> Xem quy trình 4 bước</a>
        </div>
        <div class="video-poster" role="img" aria-label="Poster minh hoạ video giới thiệu quy trình nha khoa">
            <img src="images/hero-human-ai.webp" alt="Hình minh hoạ cho video giới thiệu NALI Dental" loading="lazy">
            <span class="video-play" aria-hidden="true"><i class="fas fa-play"></i></span>
            <span class="video-note">Poster minh hoạ · video thực tế sẽ cập nhật</span>
        </div>
    </section>

    <section class="visit-process" id="quytrinh" aria-labelledby="processTitle">
        <div class="visit-process-heading">
            <span class="eyebrow">Hành trình tại NALI</span>
            <h2 id="processTitle">Bốn bước để bạn an tâm hơn</h2>
        </div>
        <ol class="process-steps">
            <li><span>01</span><div><i class="fas fa-calendar-check"></i><h3>Đặt lịch</h3><p>Gửi nhu cầu và chọn thời gian phù hợp.</p></div></li>
            <li><span>02</span><div><i class="fas fa-tooth"></i><h3>Khám &amp; chụp phim</h3><p>Bác sĩ đánh giá tình trạng thực tế.</p></div></li>
            <li><span>03</span><div><i class="fas fa-clipboard-check"></i><h3>Tư vấn phác đồ</h3><p>Xác nhận lựa chọn, chi phí và thời gian.</p></div></li>
            <li><span>04</span><div><i class="fas fa-shield-heart"></i><h3>Điều trị &amp; theo dõi</h3><p>Nhận hướng dẫn chăm sóc sau điều trị.</p></div></li>
        </ol>
    </section>

    <!-- ===== DỊCH VỤ ===== -->
    <section class="section service-editorial" id="dichvu">
        <div class="section-head">
            <span class="eyebrow">Bảng giá dịch vụ</span>
            <h2>Dịch vụ nha khoa toàn diện</h2>
            <p>Từ chăm sóc răng miệng cơ bản đến thẩm mỹ và phục hình chuyên sâu — mọi dịch vụ đều minh bạch giá và bảo hành rõ ràng.</p>
        </div>

        <?php if ($totalServices > 0): ?>
            <div class="services-grid">
                <?php foreach ($allServices as $groupServices): ?>
                    <?php foreach ($groupServices as $service) renderServiceCard($service, $groups); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-message"><i class="fas fa-inbox"></i><p>Chưa có dịch vụ nào.</p></div>
        <?php endif; ?>
    </section>

    <!-- ===== THƯ VIỆN MINH HOẠ ===== -->
    <section class="case-library" aria-labelledby="caseLibraryTitle">
        <div class="case-library-head">
            <span class="eyebrow">Thư viện ca điều trị</span>
            <h2 id="caseLibraryTitle">Xem cách một kế hoạch được hình dung</h2>
            <p>Chỉ hiển thị ảnh minh hoạ cho đến khi có ca thực tế được bệnh nhân đồng ý công bố. Kết quả điều trị khác nhau theo từng tình trạng.</p>
            <div class="case-filters" aria-label="Lọc thư viện ca điều trị">
                <button type="button" class="case-filter active">Tất cả</button>
                <button type="button" class="case-filter">Thẩm mỹ</button>
                <button type="button" class="case-filter">Phục hình</button>
                <button type="button" class="case-filter">Chỉnh nha</button>
            </div>
        </div>
        <div class="case-library-stage">
        <div class="section-head">
            <span class="eyebrow">Hình minh hoạ dịch vụ</span>
            <h2>Thay đổi màu răng sau khi chăm sóc</h2>
            <p>Hình minh hoạ nhằm giúp bạn hình dung dịch vụ. Kết quả và thời gian điều trị thực tế phụ thuộc tình trạng răng miệng của từng người.</p>
        </div>
        <div class="ba-wrap" id="baWrap">
            <img src="images/after.webp" alt="Hình minh hoạ răng trắng sáng" loading="lazy">
            <div class="ba-before" id="baBefore">
                <img src="images/before.webp" alt="Hình minh hoạ màu răng ban đầu" loading="lazy">
            </div>
            <div class="ba-handle" id="baHandle"><span class="ba-grab"><i class="fas fa-arrows-left-right"></i></span></div>
            <span class="ba-label l">TRƯỚC</span>
            <span class="ba-label r">SAU</span>
        </div>
        <aside class="case-library-note"><i class="fas fa-lock"></i><strong>Quyền riêng tư bệnh nhân được ưu tiên</strong><span>Ca thực tế chỉ được đăng khi có văn bản đồng ý.</span></aside>
        </div>
    </section>

    <section class="facility-section" aria-labelledby="facilityTitle">
        <div class="facility-image"><img src="images/clinic-space-ai.webp" alt="Hình minh hoạ không gian tiếp đón và phòng điều trị nha khoa hiện đại" loading="lazy"></div>
        <div class="facility-copy"><span class="eyebrow">Không gian chăm sóc</span><h2 id="facilityTitle">Một không gian sáng, sạch và dễ chịu</h2><p>Hình minh hoạ định hướng trải nghiệm không gian. Khi có ảnh cơ sở thực tế, hãy thay ảnh này bằng ảnh đã được phê duyệt để khách biết chính xác nơi họ sẽ đến.</p><a href="contact.php" class="btn-secondary">Xem thông tin chi nhánh <i class="fas fa-arrow-right"></i></a></div>
    </section>

    <!-- ===== VÌ SAO CHỌN NALI ===== -->
    <section class="section trust-section">
        <div class="section-head">
            <span class="eyebrow">Cam kết của chúng tôi</span>
            <h2>Vì sao khách hàng chọn NALI?</h2>
            <p>Không chỉ là điều trị — chúng tôi mang đến trải nghiệm chăm sóc răng miệng an tâm và chuyên nghiệp.</p>
        </div>
        <div class="why-grid">
            <div class="why-card"><div class="why-ico" style="background:linear-gradient(135deg,#4da6ff,#2c7ad1)"><i class="fas fa-user-md"></i></div><h4>Tư vấn theo nhu cầu</h4><p>Thông tin dịch vụ được trình bày theo từng nhóm khách hàng để bạn dễ tham khảo trước khi đặt lịch.</p></div>
            <div class="why-card"><div class="why-ico" style="background:linear-gradient(135deg,#26de81,#20bf6b)"><i class="fas fa-microscope"></i></div><h4>Thông tin rõ ràng</h4><p>Mô tả, thời lượng dự kiến và mức giá tham khảo được hiển thị trực tiếp trên từng dịch vụ.</p></div>
            <div class="why-card"><div class="why-ico" style="background:linear-gradient(135deg,#ff9f43,#f39c12)"><i class="fas fa-calendar-check"></i></div><h4>Đặt lịch thuận tiện</h4><p>Gửi yêu cầu hẹn trực tuyến để phòng khám liên hệ xác nhận thời gian và nhu cầu của bạn.</p></div>
            <div class="why-card"><div class="why-ico" style="background:linear-gradient(135deg,#a55eea,#8854d0)"><i class="fas fa-comments"></i></div><h4>Hỗ trợ trước khi đến</h4><p>Chatbot AI hỗ trợ tra cứu thông tin cơ bản; nhân viên sẽ xác nhận các nội dung chuyên môn và chi phí.</p></div>
        </div>
    </section>

    <!-- ===== CẢM NHẬN KHÁCH HÀNG ===== -->
    <section class="section testimonial-section">
        <div class="section-head">
            <span class="eyebrow">Khách hàng nói gì</span>
            <h2>Hàng nghìn nụ cười hài lòng</h2>
        </div>
        <div class="rv-viewport" id="rvViewport">
            <div class="rv-track">
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"Mình niềng răng Invisalign ở NALI, bác sĩ tư vấn rất kỹ và theo sát tiến trình. Sau 1 năm răng đều đẹp hẳn, cực kỳ hài lòng!"</p>
                    <div class="reviewer"><div class="ava">T</div><div><b>Thu Trang</b><small>Niềng răng Invisalign</small></div></div>
                </div>
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"Trồng Implant tưởng đau lắm mà nhẹ nhàng ngoài mong đợi. Phòng khám sạch sẽ, nhân viên thân thiện, giá lại có trả góp."</p>
                    <div class="reviewer"><div class="ava">H</div><div><b>Hoàng Nam</b><small>Cấy ghép Implant</small></div></div>
                </div>
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"Đưa bé đi khám răng mà bé không sợ chút nào vì bác sĩ rất khéo dỗ. Không gian dễ thương, sẽ quay lại thường xuyên."</p>
                    <div class="reviewer"><div class="ava">M</div><div><b>Minh Anh</b><small>Nha khoa trẻ em</small></div></div>
                </div>
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"Tẩy trắng bằng Laser chỉ 45 phút mà răng sáng lên thấy rõ, lại không hề ê buốt như mình lo. Rất đáng tiền!"</p>
                    <div class="reviewer"><div class="ava">L</div><div><b>Lan Phương</b><small>Tẩy trắng răng Laser</small></div></div>
                </div>
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"Nhổ răng khôn mọc lệch mà chỉ hơi tê nhẹ, hôm sau đã ăn uống bình thường. Bác sĩ tay nghề cao, dặn dò kỹ lưỡng."</p>
                    <div class="reviewer"><div class="ava">Đ</div><div><b>Đức Huy</b><small>Nhổ răng khôn</small></div></div>
                </div>
                <div class="review-card">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"Mẹ mình làm hàm giả tháo lắp ở đây, ăn nhai thoải mái hẳn. Nhân viên hỗ trợ người lớn tuổi rất chu đáo, ân cần."</p>
                    <div class="reviewer"><div class="ava">N</div><div><b>Ngọc Diệp</b><small>Hàm giả tháo lắp</small></div></div>
                </div>
            </div>
        </div>
        <div class="rv-nav">
            <button class="rv-btn" id="rvPrev" aria-label="Đánh giá trước"><i class="fas fa-chevron-left"></i></button>
            <button class="rv-btn" id="rvNext" aria-label="Đánh giá sau"><i class="fas fa-chevron-right"></i></button>
        </div>
    </section>

    <!-- ===== FAQ ===== -->
    <section class="section">
        <div class="section-head">
            <span class="eyebrow">Giải đáp thắc mắc</span>
            <h2>Câu hỏi thường gặp</h2>
            <p>Những điều khách hàng hay hỏi NALI nhất. Chưa thấy câu trả lời? Chat với Trợ lý AI góc phải màn hình nhé!</p>
        </div>
        <div class="faq-list">
            <?php
            $faqs = [
                ['Đặt lịch tại NALI có mất phí không?',
                 'Hoàn toàn miễn phí. Bạn có thể đặt lịch trực tuyến trên website, gọi hotline 0945 457 512, hoặc chat với Trợ lý AI. Lễ tân sẽ gọi xác nhận trước buổi hẹn.'],
                ['Tôi có được tư vấn miễn phí trước khi điều trị không?',
                 'Có. Buổi khám và tư vấn đầu tiên tại NALI là miễn phí. Bác sĩ sẽ kiểm tra tổng quát, giải thích tình trạng và đề xuất phương án phù hợp với ngân sách của bạn.'],
                ['NALI có hỗ trợ trả góp không?',
                 'Vui lòng liên hệ trực tiếp để được xác nhận phương thức thanh toán và các chính sách áp dụng tại thời điểm đặt lịch.'],
                ['Điều trị tại NALI có đau không?',
                 'NALI sử dụng gây tê hiện đại và thiết bị ít xâm lấn (máy siêu âm Piezotome, laser). Đa số khách hàng chỉ cảm thấy tê nhẹ. Với người sợ đau, bác sĩ sẽ tư vấn thêm phương án giảm ê buốt.'],
                ['Các dịch vụ có được bảo hành không?',
                 'Có. Bọc răng sứ bảo hành 5–7 năm, trụ Implant bảo hành trọn đời theo chính sách hãng. Bạn sẽ nhận phiếu bảo hành ngay sau khi hoàn tất điều trị.'],
                ['Trẻ em mấy tuổi thì nên đi khám răng?',
                 'Nên cho bé khám lần đầu khi mọc chiếc răng sữa đầu tiên (khoảng 6–12 tháng), sau đó khám định kỳ 6 tháng/lần. Bác sĩ nhi khoa của NALI rất khéo trong việc giúp bé hợp tác.'],
            ];
            foreach ($faqs as $i => $f): ?>
            <div class="faq-item">
                <button class="faq-q" type="button">
                    <span><?php echo htmlspecialchars($f[0]); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-a"><p><?php echo htmlspecialchars($f[1]); ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Nội dung được duyệt từ cơ sở dữ liệu ===== -->
    <?php if ($approvedReviews): ?>
    <section class="section public-review-section">
        <div class="section-head"><span class="eyebrow">Phản hồi đã duyệt</span><h2>Khách hàng chia sẻ</h2><p>Các phản hồi dưới đây được khách gửi và quản trị viên duyệt trước khi công khai.</p></div>
        <div class="why-grid">
        <?php foreach ($approvedReviews as $review): ?><article class="why-card review-card"><div class="review-icon"><i class="fas fa-quote-left"></i></div><div class="stars"><?= str_repeat('★', max(0, min(5, (int)$review['rating']))) ?></div><p>“<?= htmlspecialchars($review['message']) ?>”</p><b><?= htmlspecialchars($review['name']) ?></b><?php if ($review['type']): ?><small><?= htmlspecialchars($review['type']) ?></small><?php endif; ?></article><?php endforeach; ?>
        </div><div class="content-actions" style="justify-content:center"><a href="reviews.php" class="btn-hero ghost">Xem tất cả phản hồi</a></div>
    </section>
    <?php endif; ?>
    <section class="section public-faq-section">
        <div class="section-head"><span class="eyebrow">Giải đáp đã duyệt</span><h2>Câu hỏi thường gặp</h2><p>Câu trả lời được quản trị viên cập nhật; với nội dung điều trị cụ thể, hãy xác nhận cùng bác sĩ khi thăm khám.</p></div>
        <?php if ($publicFaqs): ?><div class="faq-list"><?php foreach ($publicFaqs as $faq): ?><div class="faq-item"><button class="faq-q" type="button"><span><?= htmlspecialchars($faq['question']) ?></span><i class="fas fa-chevron-down"></i></button><div class="faq-a"><p><?= nl2br(htmlspecialchars($faq['answer'])) ?></p></div></div><?php endforeach; ?></div><?php else: ?><div class="content-empty">Chưa có câu hỏi được công bố. Vui lòng liên hệ để nhận thông tin chính xác theo nhu cầu.</div><?php endif; ?>
    </section>

    <!-- ===== CTA ===== -->
    <div class="cta-band">
        <h2>Sẵn sàng cho nụ cười mới?</h2>
        <p>Đặt lịch hẹn chỉ trong 30 giây — hoặc chat ngay với Trợ lý AI của NALI để được tư vấn miễn phí.</p>
        <a href="contact.php" class="btn-hero primary"><i class="fas fa-calendar-check"></i> Đặt lịch hẹn ngay</a>
    </div>

    <div style="height:60px"></div>

    <!-- Modal xem nhanh dịch vụ -->
    <div class="svc-modal" id="svcModal" role="dialog" aria-modal="true">
        <div class="svc-modal-bg" data-close></div>
        <div class="svc-modal-box">
            <button class="svc-modal-close" data-close aria-label="Đóng">&times;</button>
            <div class="svc-modal-media" id="svcModalMedia"></div>
            <div class="svc-modal-body">
                <div class="svc-meta">
                    <span class="dur"><i class="far fa-clock"></i> <span id="svcModalDur"></span>′</span>
                </div>
                <h3 id="svcModalName"></h3>
                <p id="svcModalDesc"></p>
                <div class="svc-modal-foot">
                    <div class="price-box"><small>Chỉ từ</small><span class="price" id="svcModalPrice"></span></div>
                    <a href="contact.php" class="order-btn"><i class="fas fa-calendar-check"></i> Đặt lịch dịch vụ này</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    /* ---- Slider so sánh Trước/Sau ---- */
    (function () {
        var wrap = document.getElementById('baWrap');
        if (!wrap) return;
        var before = document.getElementById('baBefore'),
            handle = document.getElementById('baHandle'), dragging = false;
        function setPos(clientX) {
            var r = wrap.getBoundingClientRect();
            var p = Math.min(Math.max((clientX - r.left) / r.width, 0), 1) * 100;
            before.style.clipPath = 'inset(0 ' + (100 - p) + '% 0 0)';
            handle.style.left = p + '%';
        }
        wrap.addEventListener('pointerdown', function (e) { dragging = true; wrap.setPointerCapture(e.pointerId); setPos(e.clientX); });
        wrap.addEventListener('pointermove', function (e) { if (dragging) setPos(e.clientX); });
        wrap.addEventListener('pointerup', function () { dragging = false; });
        wrap.addEventListener('pointercancel', function () { dragging = false; });
    })();

    /* ---- Modal xem nhanh dịch vụ ---- */
    (function () {
        var modal = document.getElementById('svcModal');
        if (!modal) return;
        var media = document.getElementById('svcModalMedia');
        function setText(id, v) { var el = document.getElementById(id); if (el) el.textContent = v || ''; }
        function open(card) {
            setText('svcModalName', card.dataset.name);
            setText('svcModalDesc', card.dataset.desc);
            setText('svcModalPrice', card.dataset.price);
            setText('svcModalDur', card.dataset.dur);
            media.innerHTML = card.dataset.img
                ? '<img src="' + card.dataset.img + '" alt="">'
                : '<div class="thumb-fallback" style="background:' + card.dataset.grad + '"><i class="fas ' + card.dataset.icon + '"></i></div>';
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function close() { modal.classList.remove('open'); document.body.style.overflow = ''; }
        document.querySelectorAll('.service-card').forEach(function (card) {
            card.addEventListener('click', function (e) {
                if (e.target.closest('.order-btn')) return;   // vẫn cho nút "Đặt lịch" hoạt động
                open(card);
            });
        });
        modal.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();

    /* ---- FAQ accordion ---- */
    (function () {
        document.querySelectorAll('.faq-item').forEach(function (item) {
            var btn = item.querySelector('.faq-q'), ans = item.querySelector('.faq-a');
            if (!btn || !ans) return;
            btn.addEventListener('click', function () {
                var isOpen = item.classList.contains('open');
                // Đóng tất cả (chỉ mở 1 câu tại một thời điểm)
                document.querySelectorAll('.faq-item.open').forEach(function (o) {
                    o.classList.remove('open');
                    o.querySelector('.faq-a').style.maxHeight = null;
                });
                if (!isOpen) {
                    item.classList.add('open');
                    ans.style.maxHeight = ans.scrollHeight + 'px';
                }
            });
        });
    })();

    /* ---- Carousel cảm nhận khách hàng ---- */
    (function () {
        var vp = document.getElementById('rvViewport');
        if (!vp) return;
        var prev = document.getElementById('rvPrev'), next = document.getElementById('rvNext'), timer;
        function step() { var c = vp.querySelector('.review-card'); return c ? c.offsetWidth + 24 : vp.clientWidth; }
        function go(dir) {
            var max = vp.scrollWidth - vp.clientWidth - 2;
            if (dir > 0 && vp.scrollLeft >= max) vp.scrollTo({ left: 0, behavior: 'smooth' });
            else if (dir < 0 && vp.scrollLeft <= 2) vp.scrollTo({ left: max, behavior: 'smooth' });
            else vp.scrollBy({ left: dir * step(), behavior: 'smooth' });
        }
        if (next) next.addEventListener('click', function () { go(1); });
        if (prev) prev.addEventListener('click', function () { go(-1); });
        function play() { timer = setInterval(function () { go(1); }, 4500); }
        play();
        vp.addEventListener('mouseenter', function () { clearInterval(timer); });
        vp.addEventListener('mouseleave', play);
    })();
    </script>

    <script src="script.js"></script>
    <?php renderFooter(); ?>
</body>
</html>
