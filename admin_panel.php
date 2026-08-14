<?php
session_start();

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['auth']) || !isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

$admin_name = $_SESSION['auth_user']['name'] ?? 'Admin';
$admin_username = $_SESSION['auth_user']['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - NALI Dental</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        
        /* Sidebar */
        .sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, #1a237e 0%, #283593 100%); color: white; z-index: 1000; transition: all 0.3s; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 1.5rem; }
        .sidebar-header span { font-size: 0.85rem; opacity: 0.8; }
        
        .sidebar-menu { padding: 20px 0; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; cursor: pointer; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #4fc3f7; }
        .menu-item i { width: 30px; font-size: 1.1rem; }
        .menu-item span { font-size: 0.95rem; }
        
        .sidebar-footer { position: absolute; bottom: 0; width: 100%; padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .admin-profile { display: flex; align-items: center; gap: 10px; }
        .admin-avatar { width: 40px; height: 40px; background: #4fc3f7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .admin-info-text { flex: 1; }
        .admin-info-text h4 { font-size: 0.9rem; margin-bottom: 2px; }
        .admin-info-text p { font-size: 0.75rem; opacity: 0.7; }
        
        /* Main Content */
        .main-content { margin-left: 260px; min-height: 100vh; }
        
        /* Top Bar */
        .topbar { background: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .topbar h1 { font-size: 1.5rem; color: #333; }
        .topbar-actions { display: flex; gap: 15px; align-items: center; }
        .topbar-actions a { color: #666; text-decoration: none; padding: 8px 15px; border-radius: 5px; transition: all 0.3s; }
        .topbar-actions a:hover { background: #f0f0f0; }
        .btn-logout { background: #e53935 !important; color: white !important; }
        .btn-logout:hover { background: #c62828 !important; }
        
        /* Content Area */
        .content { padding: 30px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .charts-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 20px; margin-bottom: 30px; }
        @media (max-width: 1000px) { .charts-grid { grid-template-columns: 1fr; } }
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .stat-icon.blue { background: linear-gradient(135deg, #4fc3f7, #29b6f6); }
        .stat-icon.green { background: linear-gradient(135deg, #66bb6a, #43a047); }
        .stat-icon.orange { background: linear-gradient(135deg, #ffa726, #fb8c00); }
        .stat-icon.purple { background: linear-gradient(135deg, #ab47bc, #8e24aa); }
        .stat-icon.red { background: linear-gradient(135deg, #ef5350, #e53935); }
        .stat-info h3 { font-size: 1.8rem; color: #333; margin-bottom: 5px; }
        .stat-info p { color: #888; font-size: 0.9rem; }
        
        /* Tab Content */
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Card */
        .card { background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1.2rem; color: #333; }
        .card-body { padding: 25px; }
        
        /* Action Bar */
        .action-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .search-box { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; min-width: 250px; }
        .search-box:focus { outline: none; border-color: #4fc3f7; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; min-width: 180px; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #4fc3f7, #29b6f6); color: white; }
        .btn-primary:hover { background: linear-gradient(135deg, #29b6f6, #039be5); transform: translateY(-2px); }
        .btn-success { background: #43a047; color: white; }
        .btn-success:hover { background: #388e3c; }
        .btn-warning { background: #fb8c00; color: white; }
        .btn-warning:hover { background: #f57c00; }
        .btn-danger { background: #e53935; color: white; }
        .btn-danger:hover { background: #c62828; }
        .btn-secondary { background: #757575; color: white; }
        .btn-secondary:hover { background: #616161; }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
        
        /* Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #555; border-bottom: 2px solid #eee; }
        .data-table td { padding: 15px; border-bottom: 1px solid #eee; color: #666; }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table .actions { display: flex; gap: 8px; }
        
        /* Status Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .badge-confirmed { background: #e3f2fd; color: #1565c0; }
        .badge-completed { background: #e8f5e9; color: #2e7d32; }
        .badge-cancelled { background: #ffebee; color: #c62828; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 1.3rem; color: #333; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        
        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #4fc3f7; box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.1); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 50px; color: #888; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #ddd; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2, .sidebar-header span, .menu-item span, .admin-info-text { display: none; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
    <link rel="stylesheet" href="clinic-ui.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🦷 NALI Dental</h2>
            <span>Hệ thống quản trị</span>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-item active" data-tab="dashboard">
                <i class="fas fa-chart-pie"></i>
                <span>Tổng quan</span>
            </div>
            <div class="menu-item" data-tab="appointments">
                <i class="fas fa-calendar-check"></i>
                <span>Lịch hẹn</span>
            </div>
            <div class="menu-item" data-tab="products">
                <i class="fas fa-tooth"></i>
                <span>Dịch vụ</span>
            </div>
            <div class="menu-item" data-tab="patients">
                <i class="fas fa-users"></i>
                <span>Khách hàng</span>
            </div>
            <div class="menu-item" data-tab="doctors">
                <i class="fas fa-user-md"></i>
                <span>Bác sĩ</span>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                <div class="admin-info-text">
                    <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                    <p><?php echo htmlspecialchars($admin_username); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <h1 id="pageTitle">📊 Tổng quan</h1>
            <div class="topbar-actions">
                <a href="services.php"><i class="fas fa-tooth"></i> Dịch vụ</a>
                <a href="content_admin.php"><i class="fas fa-pen-to-square"></i> Nội dung</a>
                <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
            </div>
        </div>
        
        <div class="content">
            <!-- Dashboard Tab -->
            <div class="tab-content active" id="dashboard-tab">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                        <div class="stat-info">
                            <h3 id="statPending">0</h3>
                            <p>Lịch hẹn chờ xác nhận</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
                        <div class="stat-info">
                            <h3 id="statToday">0</h3>
                            <p>Lịch hẹn hôm nay</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h3 id="statPatients">0</h3>
                            <p>Tổng khách hàng</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-tooth"></i></div>
                        <div class="stat-info">
                            <h3 id="statProducts">0</h3>
                            <p>Dịch vụ đang hoạt động</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-user-md"></i></div>
                        <div class="stat-info">
                            <h3 id="statDoctors">0</h3>
                            <p>Bác sĩ</p>
                        </div>
                    </div>
                </div>

                <!-- ===== BIỂU ĐỒ THỐNG KÊ ===== -->
                <div class="charts-grid">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-column"></i> Lịch hẹn 7 ngày tới</h2>
                        </div>
                        <div class="card-body"><canvas id="chartWeek" height="120"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-pie"></i> Trạng thái lịch hẹn</h2>
                        </div>
                        <div class="card-body"><canvas id="chartStatus" height="120"></canvas></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-clock"></i> Lịch hẹn chờ xác nhận</h2>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Khách hàng</th>
                                    <th>Điện thoại</th>
                                    <th>Ngày hẹn</th>
                                    <th>Giờ</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="pendingAppointmentsTable">
                                <tr><td colspan="5" class="empty-state">Đang tải...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Appointments Tab -->
            <div class="tab-content" id="appointments-tab">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-check"></i> Quản lý Lịch hẹn</h2>
                        <button class="btn btn-primary" onclick="openAppointmentModal()"><i class="fas fa-plus"></i> Thêm lịch hẹn</button>
                    </div>
                    <div class="card-body">
                        <div class="action-bar">
                            <input type="text" class="search-box" id="searchAppointment" placeholder="🔍 Tìm kiếm khách hàng, SĐT...">
                            <select class="filter-select" id="filterStatus">
                                <option value="">Tất cả trạng thái</option>
                                <option value="pending">Chờ xác nhận</option>
                                <option value="confirmed">Đã xác nhận</option>
                                <option value="completed">Hoàn thành</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                            <input type="date" class="search-box" id="filterDate" style="min-width: 180px;">
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Khách hàng</th>
                                    <th>Điện thoại</th>
                                    <th>Email</th>
                                    <th>Ngày hẹn</th>
                                    <th>Giờ</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="appointmentsTable">
                                <tr><td colspan="9" class="empty-state">Đang tải...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Products Tab -->
            <div class="tab-content" id="products-tab">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-tooth"></i> Quản lý Dịch vụ</h2>
                        <button class="btn btn-primary" onclick="openProductModal()"><i class="fas fa-plus"></i> Thêm dịch vụ</button>
                    </div>
                    <div class="card-body">
                        <div class="action-bar">
                            <input type="text" class="search-box" id="searchProduct" placeholder="🔍 Tìm kiếm dịch vụ...">
                            <select class="filter-select" id="filterTargetGroup">
                                <option value="">Tất cả đối tượng</option>
                                <option value="children">Trẻ em</option>
                                <option value="adults">Người lớn</option>
                                <option value="elderly">Người cao tuổi</option>
                                <option value="chronic">Bệnh lý nền</option>
                            </select>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên dịch vụ</th>
                                    <th>Giá</th>
                                    <th>Đối tượng</th>
                                    <th>Thời gian (phút)</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="productsTable">
                                <tr><td colspan="6" class="empty-state">Đang tải...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Patients Tab -->
            <div class="tab-content" id="patients-tab">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> Quản lý Khách hàng</h2>
                    </div>
                    <div class="card-body">
                        <div class="action-bar">
                            <input type="text" class="search-box" id="searchPatient" placeholder="🔍 Tìm kiếm khách hàng...">
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Họ tên</th>
                                    <th>Email</th>
                                    <th>Điện thoại</th>
                                    <th>Giới tính</th>
                                    <th>Số lịch hẹn</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="patientsTable">
                                <tr><td colspan="7" class="empty-state">Đang tải...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Doctors Tab -->
            <div class="tab-content" id="doctors-tab">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-md"></i> Quản lý Bác sĩ</h2>
                        <button class="btn btn-primary" onclick="openDoctorModal()"><i class="fas fa-plus"></i> Thêm bác sĩ</button>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Họ tên</th>
                                    <th>Username</th>
                                    <th>Chuyên khoa</th>
                                    <th>Điện thoại</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="doctorsTable">
                                <tr><td colspan="6" class="empty-state">Đang tải...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="productModalTitle">Thêm dịch vụ mới</h2>
                <button class="modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="productId">
                <div class="form-group">
                    <label>Tên dịch vụ *</label>
                    <input type="text" class="form-control" id="productName" required>
                </div>
                <div class="form-group">
                    <label>Mô tả</label>
                    <textarea class="form-control" id="productDescription"></textarea>
                </div>
                <div class="form-group">
                    <label>Giá (VNĐ) *</label>
                    <input type="number" class="form-control" id="productPrice" required>
                </div>
                <div class="form-group">
                    <label>Đối tượng</label>
                    <select class="form-control" id="productTargetGroup">
                        <option value="adults">Người lớn</option>
                        <option value="children">Trẻ em</option>
                        <option value="elderly">Người cao tuổi</option>
                        <option value="chronic">Bệnh lý nền</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Thời gian (phút)</label>
                    <input type="number" class="form-control" id="productDuration" value="30">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeProductModal()">Hủy</button>
                <button class="btn btn-primary" onclick="saveProduct()">Lưu</button>
            </div>
        </div>
    </div>
    
    <!-- Doctor Modal -->
    <div class="modal" id="doctorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="doctorModalTitle">Thêm bác sĩ mới</h2>
                <button class="modal-close" onclick="closeDoctorModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="doctorId">
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" class="form-control" id="doctorName" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" class="form-control" id="doctorUsername" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu (để trống nếu không đổi)</label>
                    <input type="password" class="form-control" id="doctorPassword">
                </div>
                <div class="form-group">
                    <label>Chuyên khoa</label>
                    <input type="text" class="form-control" id="doctorSpecialty">
                </div>
                <div class="form-group">
                    <label>Điện thoại</label>
                    <input type="text" class="form-control" id="doctorPhone">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeDoctorModal()">Hủy</button>
                <button class="btn btn-primary" onclick="saveDoctor()">Lưu</button>
            </div>
        </div>
    </div>
    
    <!-- Appointment Modal -->
    <div class="modal" id="appointmentModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 id="appointmentModalTitle">Thêm lịch hẹn mới</h2>
                <button class="modal-close" onclick="closeAppointmentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="appointmentId">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Họ tên khách hàng *</label>
                        <input type="text" class="form-control" id="appointmentName" required>
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại *</label>
                        <input type="text" class="form-control" id="appointmentPhone" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" id="appointmentEmail">
                    </div>
                    <div class="form-group">
                        <label>Ngày hẹn *</label>
                        <input type="date" class="form-control" id="appointmentDate" required>
                    </div>
                    <div class="form-group">
                        <label>Giờ hẹn *</label>
                        <input type="time" class="form-control" id="appointmentTime" required>
                    </div>
                    <div class="form-group">
                        <label>Phương thức thanh toán</label>
                        <select class="form-control" id="appointmentPayment">
                            <option value="cash">Tiền mặt</option>
                            <option value="bank">Chuyển khoản</option>
                            <option value="momo">MoMo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tổng tiền (VNĐ)</label>
                        <input type="number" class="form-control" id="appointmentPrice" value="0">
                    </div>
                    <div class="form-group">
                        <label>Trạng thái</label>
                        <select class="form-control" id="appointmentStatus">
                            <option value="pending">Chờ xác nhận</option>
                            <option value="confirmed">Đã xác nhận</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ghi chú</label>
                    <textarea class="form-control" id="appointmentNotes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAppointmentModal()">Hủy</button>
                <button class="btn btn-primary" onclick="saveAppointment()">Lưu</button>
            </div>
        </div>
    </div>
    
    <!-- Appointment Detail Modal -->
    <div class="modal" id="appointmentDetailModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Chi tiết Lịch hẹn</h2>
                <button class="modal-close" onclick="closeAppointmentDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="appointmentDetailBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAppointmentDetailModal()">Đóng</button>
            </div>
        </div>
    </div>

<script>
// ========== Tab Navigation ==========
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        // Update active menu
        document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
        this.classList.add('active');
        
        // Update active tab content
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.getElementById(tab + '-tab').classList.add('active');
        
        // Update page title
        const titles = {
            'dashboard': '📊 Tổng quan',
            'appointments': '📅 Quản lý Lịch hẹn',
            'products': '🦷 Quản lý Dịch vụ',
            'patients': '👥 Quản lý Khách hàng',
            'doctors': '👨‍⚕️ Quản lý Bác sĩ'
        };
        document.getElementById('pageTitle').textContent = titles[tab];
        
        // Load data for tab
        if (tab === 'appointments') loadAppointments();
        if (tab === 'products') loadProducts();
        if (tab === 'patients') loadPatients();
        if (tab === 'doctors') loadDoctors();
    });
});

// ========== Biểu đồ thống kê (Chart.js) ==========
let _chartWeek = null, _chartStatus = null;

async function renderCharts() {
    if (typeof Chart === 'undefined') return;   // CDN chưa tải -> bỏ qua, không vỡ trang
    let appts = [];
    try {
        const res = await fetch('api/appointments.php');
        const data = await res.json();
        appts = data.success ? (data.appointments || []) : [];
    } catch (e) { console.error('Chart data error:', e); return; }

    // --- Biểu đồ cột: lịch hẹn 7 ngày tới (hôm nay -> +6) ---
    const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const labels = [], counts = [];
    for (let i = 0; i <= 6; i++) {
        const d = new Date(); d.setDate(d.getDate() + i);
        const key = ymd(d);
        labels.push(d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }));
        counts.push(appts.filter(a => String(a.appointment_date || '').slice(0, 10) === key).length);
    }
    const ctxW = document.getElementById('chartWeek');
    if (ctxW) {
        if (_chartWeek) _chartWeek.destroy();
        _chartWeek = new Chart(ctxW, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Lịch hẹn', data: counts,
                    backgroundColor: 'rgba(77,166,255,.75)', borderRadius: 8, maxBarThickness: 44 }] },
            options: { responsive: true, plugins: { legend: { display: false } },
                       scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    // --- Biểu đồ tròn: trạng thái lịch hẹn ---
    const map = { pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', completed: 'Hoàn thành', cancelled: 'Đã huỷ' };
    const keys = Object.keys(map);
    const vals = keys.map(k => appts.filter(a => (a.status || 'pending') === k).length);
    const ctxS = document.getElementById('chartStatus');
    if (ctxS) {
        if (_chartStatus) _chartStatus.destroy();
        _chartStatus = new Chart(ctxS, {
            type: 'doughnut',
            data: { labels: keys.map(k => map[k]),
                    datasets: [{ data: vals, backgroundColor: ['#ffa726', '#4da6ff', '#26de81', '#ef5350'], borderWidth: 0 }] },
            options: { responsive: true, cutout: '62%', plugins: { legend: { position: 'bottom' } } }
        });
    }
}

// ========== Load Dashboard Stats ==========
async function loadDashboard() {
    try {
        const res = await fetch('api/dashboard.php');
        const data = await res.json();
        if (data.success) {
            document.getElementById('statPending').textContent = data.stats.pending_appointments || 0;
            document.getElementById('statToday').textContent = data.stats.today_appointments || 0;
            document.getElementById('statPatients').textContent = data.stats.total_patients || 0;
            document.getElementById('statProducts').textContent = data.stats.total_products || 0;
            document.getElementById('statDoctors').textContent = data.stats.total_doctors || 0;
        }
    } catch (e) { console.error('Dashboard error:', e); }

    // Vẽ biểu đồ thống kê
    renderCharts();

    // Load pending appointments
    loadPendingAppointments();
}

async function loadPendingAppointments() {
    try {
        const res = await fetch('api/appointments.php?status=pending');
        const data = await res.json();
        const tbody = document.getElementById('pendingAppointmentsTable');
        
        if (data.success && data.appointments.length > 0) {
            tbody.innerHTML = data.appointments.slice(0, 5).map(a => `
                <tr>
                    <td>${a.customer_name}</td>
                    <td>${a.customer_phone}</td>
                    <td>${a.appointment_date}</td>
                    <td>${a.appointment_time}</td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="updateAppointmentStatus(${a.id}, 'confirmed')"><i class="fas fa-check"></i> Xác nhận</button>
                        <button class="btn btn-danger btn-sm" onclick="updateAppointmentStatus(${a.id}, 'cancelled')"><i class="fas fa-times"></i> Hủy</button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-check-circle"></i><br>Không có lịch hẹn chờ xác nhận</td></tr>';
        }
    } catch (e) { console.error('Pending appointments error:', e); }
}

// ========== Appointments ==========
async function loadAppointments() {
    const status = document.getElementById('filterStatus').value;
    const search = document.getElementById('searchAppointment').value;
    const date = document.getElementById('filterDate').value;
    
    try {
        let url = 'api/appointments.php?';
        if (status) url += 'status=' + status + '&';
        if (search) url += 'search=' + encodeURIComponent(search) + '&';
        if (date) url += 'date=' + date;
        
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('appointmentsTable');
        
        if (data.success && data.appointments.length > 0) {
            tbody.innerHTML = data.appointments.map(a => `
                <tr>
                    <td>${a.id}</td>
                    <td>${a.customer_name}</td>
                    <td>${a.customer_phone}</td>
                    <td>${a.customer_email || '-'}</td>
                    <td>${a.appointment_date}</td>
                    <td>${a.appointment_time}</td>
                    <td>${parseInt(a.total_price).toLocaleString('vi-VN')}đ</td>
                    <td><span class="badge badge-${a.status}">${getStatusText(a.status)}</span></td>
                    <td class="actions">
                        <button class="btn btn-primary btn-sm" onclick="viewAppointment(${a.id})" title="Xem chi tiết"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-warning btn-sm" onclick="editAppointment(${a.id})" title="Sửa"><i class="fas fa-edit"></i></button>
                        ${a.status === 'pending' ? `<button class="btn btn-success btn-sm" onclick="updateAppointmentStatus(${a.id}, 'confirmed')" title="Xác nhận"><i class="fas fa-check"></i></button>` : ''}
                        ${a.status === 'confirmed' ? `<button class="btn btn-success btn-sm" onclick="updateAppointmentStatus(${a.id}, 'completed')" title="Hoàn thành"><i class="fas fa-check-double"></i></button>` : ''}
                        ${a.status !== 'cancelled' && a.status !== 'completed' ? `<button class="btn btn-secondary btn-sm" onclick="updateAppointmentStatus(${a.id}, 'cancelled')" title="Hủy"><i class="fas fa-ban"></i></button>` : ''}
                        <button class="btn btn-danger btn-sm" onclick="deleteAppointment(${a.id})" title="Xóa"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="empty-state"><i class="fas fa-calendar-times"></i><br>Không có lịch hẹn</td></tr>';
        }
    } catch (e) { console.error('Appointments error:', e); }
}

function getStatusText(status) {
    const texts = { 'pending': 'Chờ xác nhận', 'confirmed': 'Đã xác nhận', 'completed': 'Hoàn thành', 'cancelled': 'Đã hủy' };
    return texts[status] || status;
}

async function updateAppointmentStatus(id, status) {
    if (!confirm('Xác nhận thay đổi trạng thái?')) return;
    
    try {
        const res = await fetch('api/appointments.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status })
        });
        const data = await res.json();
        if (data.success) {
            alert('Cập nhật thành công!');
            loadAppointments();
            loadDashboard();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Lỗi kết nối!'); }
}

async function deleteAppointment(id) {
    if (!confirm('Xác nhận xóa lịch hẹn này?')) return;
    
    try {
        const res = await fetch('api/appointments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            alert('Xóa thành công!');
            loadAppointments();
            loadDashboard();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Lỗi kết nối!'); }
}

// Appointment Modal Functions
let currentAppointmentId = null;

function openAppointmentModal(id = null) {
    currentAppointmentId = id;
    document.getElementById('appointmentModalTitle').textContent = id ? 'Sửa Lịch hẹn' : 'Thêm Lịch hẹn mới';
    
    // Reset form
    document.getElementById('appointmentName').value = '';
    document.getElementById('appointmentPhone').value = '';
    document.getElementById('appointmentEmail').value = '';
    document.getElementById('appointmentDate').value = '';
    document.getElementById('appointmentTime').value = '';
    document.getElementById('appointmentPrice').value = '';
    document.getElementById('appointmentStatus').value = 'pending';
    document.getElementById('appointmentNotes').value = '';
    
    if (id) {
        loadAppointmentData(id);
    }
    
    document.getElementById('appointmentModal').style.display = 'flex';
}

function closeAppointmentModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    currentAppointmentId = null;
}

async function loadAppointmentData(id) {
    try {
        const res = await fetch('api/appointments.php?id=' + id);
        const data = await res.json();
        if (data.success && data.appointment) {
            const a = data.appointment;
            document.getElementById('appointmentName').value = a.customer_name || '';
            document.getElementById('appointmentPhone').value = a.customer_phone || '';
            document.getElementById('appointmentEmail').value = a.customer_email || '';
            document.getElementById('appointmentDate').value = a.appointment_date || '';
            document.getElementById('appointmentTime').value = a.appointment_time || '';
            document.getElementById('appointmentPrice').value = a.total_price || '';
            document.getElementById('appointmentStatus').value = a.status || 'pending';
            document.getElementById('appointmentNotes').value = a.notes || '';
        }
    } catch (e) { console.error('Load appointment error:', e); }
}

async function saveAppointment() {
    const data = {
        customer_name: document.getElementById('appointmentName').value.trim(),
        customer_phone: document.getElementById('appointmentPhone').value.trim(),
        customer_email: document.getElementById('appointmentEmail').value.trim(),
        appointment_date: document.getElementById('appointmentDate').value,
        appointment_time: document.getElementById('appointmentTime').value,
        total_price: document.getElementById('appointmentPrice').value,
        status: document.getElementById('appointmentStatus').value,
        notes: document.getElementById('appointmentNotes').value.trim()
    };
    
    if (!data.customer_name || !data.customer_phone || !data.appointment_date || !data.appointment_time) {
        alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
        return;
    }
    
    try {
        let res;
        if (currentAppointmentId) {
            data.id = currentAppointmentId;
            res = await fetch('api/appointments.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            res = await fetch('api/appointments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }
        
        const result = await res.json();
        if (result.success) {
            alert(currentAppointmentId ? 'Cập nhật thành công!' : 'Thêm lịch hẹn thành công!');
            closeAppointmentModal();
            loadAppointments();
            loadDashboard();
        } else {
            alert(result.message || 'Có lỗi xảy ra!');
        }
    } catch (e) {
        console.error('Save appointment error:', e);
        alert('Lỗi kết nối!');
    }
}

function editAppointment(id) {
    openAppointmentModal(id);
}

async function viewAppointment(id) {
    try {
        const res = await fetch('api/appointments.php?id=' + id);
        const data = await res.json();
        if (data.success && data.appointment) {
            const a = data.appointment;
            document.getElementById('appointmentDetailBody').innerHTML = `
                <div style="line-height: 2;">
                    <p><strong><i class="fas fa-hashtag"></i> ID:</strong> ${a.id}</p>
                    <p><strong><i class="fas fa-user"></i> Khách hàng:</strong> ${a.customer_name}</p>
                    <p><strong><i class="fas fa-phone"></i> Điện thoại:</strong> ${a.customer_phone}</p>
                    <p><strong><i class="fas fa-envelope"></i> Email:</strong> ${a.customer_email || 'Chưa có'}</p>
                    <p><strong><i class="fas fa-calendar"></i> Ngày hẹn:</strong> ${a.appointment_date}</p>
                    <p><strong><i class="fas fa-clock"></i> Giờ hẹn:</strong> ${a.appointment_time}</p>
                    <p><strong><i class="fas fa-money-bill"></i> Tổng tiền:</strong> ${parseInt(a.total_price).toLocaleString('vi-VN')}đ</p>
                    <p><strong><i class="fas fa-info-circle"></i> Trạng thái:</strong> <span class="badge badge-${a.status}">${getStatusText(a.status)}</span></p>
                    <p><strong><i class="fas fa-sticky-note"></i> Ghi chú:</strong> ${a.notes || 'Không có'}</p>
                    <p><strong><i class="fas fa-calendar-plus"></i> Ngày tạo:</strong> ${a.created_at || 'N/A'}</p>
                </div>
            `;
            document.getElementById('appointmentDetailModal').style.display = 'flex';
        } else {
            alert('Không tìm thấy lịch hẹn!');
        }
    } catch (e) {
        console.error('View appointment error:', e);
        alert('Lỗi kết nối!');
    }
}

function closeAppointmentDetailModal() {
    document.getElementById('appointmentDetailModal').style.display = 'none';
}

// ========== Products ==========
async function loadProducts() {
    const search = document.getElementById('searchProduct').value;
    const group = document.getElementById('filterTargetGroup').value;
    
    try {
        let url = 'api/products.php?';
        if (search) url += 'search=' + encodeURIComponent(search) + '&';
        if (group) url += 'target_group=' + group;
        
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('productsTable');
        
        if (data.success && data.products.length > 0) {
            tbody.innerHTML = data.products.map(p => `
                <tr>
                    <td>${p.id}</td>
                    <td>${p.name}</td>
                    <td>${parseInt(p.price).toLocaleString('vi-VN')}đ</td>
                    <td>${getTargetGroupText(p.target_group)}</td>
                    <td>${p.duration} phút</td>
                    <td class="actions">
                        <button class="btn btn-warning btn-sm" onclick="editProduct(${p.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(${p.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-tooth"></i><br>Không có dịch vụ</td></tr>';
        }
    } catch (e) { console.error('Products error:', e); }
}

function getTargetGroupText(group) {
    const texts = { 'children': 'Trẻ em', 'adults': 'Người lớn', 'elderly': 'Người cao tuổi', 'chronic': 'Bệnh lý nền' };
    return texts[group] || group;
}

function openProductModal(product = null) {
    document.getElementById('productId').value = product?.id || '';
    document.getElementById('productName').value = product?.name || '';
    document.getElementById('productDescription').value = product?.description || '';
    document.getElementById('productPrice').value = product?.price || '';
    document.getElementById('productTargetGroup').value = product?.target_group || 'adults';
    document.getElementById('productDuration').value = product?.duration || 30;
    document.getElementById('productModalTitle').textContent = product ? 'Sửa dịch vụ' : 'Thêm dịch vụ mới';
    document.getElementById('productModal').classList.add('show');
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('show');
}

async function editProduct(id) {
    try {
        const res = await fetch('api/products.php?id=' + id);
        const data = await res.json();
        if (data.success) {
            openProductModal(data.product);
        }
    } catch (e) { alert('Lỗi tải dữ liệu!'); }
}

async function saveProduct() {
    const id = document.getElementById('productId').value;
    const product = {
        id: id || undefined,
        name: document.getElementById('productName').value,
        description: document.getElementById('productDescription').value,
        price: document.getElementById('productPrice').value,
        target_group: document.getElementById('productTargetGroup').value,
        duration: document.getElementById('productDuration').value
    };
    
    if (!product.name || !product.price) {
        alert('Vui lòng nhập đầy đủ thông tin!');
        return;
    }
    
    try {
        const res = await fetch('api/products.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(product)
        });
        const data = await res.json();
        if (data.success) {
            alert('Lưu thành công!');
            closeProductModal();
            loadProducts();
            loadDashboard();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Lỗi kết nối!'); }
}

async function deleteProduct(id) {
    if (!confirm('Xác nhận xóa dịch vụ này?')) return;
    
    try {
        const res = await fetch('api/products.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            alert('Xóa thành công!');
            loadProducts();
            loadDashboard();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Lỗi kết nối!'); }
}

// ========== Patients ==========
async function loadPatients() {
    const search = document.getElementById('searchPatient').value;
    
    try {
        let url = 'api/patients.php?';
        if (search) url += 'search=' + encodeURIComponent(search);
        
        const res = await fetch(url);
        const data = await res.json();
        const tbody = document.getElementById('patientsTable');
        
        if (data.success && data.patients.length > 0) {
            tbody.innerHTML = data.patients.map(p => `
                <tr>
                    <td>${p.id}</td>
                    <td>${p.full_name}</td>
                    <td>${p.email}</td>
                    <td>${p.phone}</td>
                    <td>${p.gender || '-'}</td>
                    <td>${p.appointment_count || 0}</td>
                    <td class="actions">
                        <button class="btn btn-primary btn-sm" onclick="viewPatient(${p.id})"><i class="fas fa-eye"></i></button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fas fa-users"></i><br>Không có khách hàng</td></tr>';
        }
    } catch (e) { console.error('Patients error:', e); }
}

async function viewPatient(id) {
    try {
        const res = await fetch('api/patients.php?id=' + id);
        const data = await res.json();
        if (data.success) {
            const p = data.patient;
            alert(`Khách hàng: ${p.full_name}\nEmail: ${p.email}\nSĐT: ${p.phone}\nGiới tính: ${p.gender || '-'}\nSố lịch hẹn: ${p.appointments?.length || 0}`);
        }
    } catch (e) { alert('Lỗi tải dữ liệu!'); }
}

// ========== Doctors ==========
async function loadDoctors() {
    try {
        const res = await fetch('api/doctors.php');
        const data = await res.json();
        const tbody = document.getElementById('doctorsTable');
        
        if (data.success && data.doctors.length > 0) {
            tbody.innerHTML = data.doctors.map(d => `
                <tr>
                    <td>${d.id}</td>
                    <td>${d.full_name}</td>
                    <td>${d.username}</td>
                    <td>${d.specialty || '-'}</td>
                    <td>${d.phone || '-'}</td>
                    <td class="actions">
                        <button class="btn btn-warning btn-sm" onclick="editDoctor(${d.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteDoctor(${d.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-user-md"></i><br>Không có bác sĩ</td></tr>';
        }
    } catch (e) { console.error('Doctors error:', e); }
}

function openDoctorModal(doctor = null) {
    document.getElementById('doctorId').value = doctor?.id || '';
    document.getElementById('doctorName').value = doctor?.full_name || '';
    document.getElementById('doctorUsername').value = doctor?.username || '';
    document.getElementById('doctorPassword').value = '';
    document.getElementById('doctorSpecialty').value = doctor?.specialty || '';
    document.getElementById('doctorPhone').value = doctor?.phone || '';
    document.getElementById('doctorModalTitle').textContent = doctor ? 'Sửa thông tin bác sĩ' : 'Thêm bác sĩ mới';
    document.getElementById('doctorModal').classList.add('show');
}

function closeDoctorModal() {
    document.getElementById('doctorModal').classList.remove('show');
}

async function editDoctor(id) {
    try {
        const res = await fetch('api/doctors.php?id=' + id);
        const data = await res.json();
        if (data.success) {
            openDoctorModal(data.doctor);
        }
    } catch (e) { alert('Lỗi tải dữ liệu!'); }
}

async function saveDoctor() {
    const id = document.getElementById('doctorId').value;
    const doctor = {
        id: id || undefined,
        full_name: document.getElementById('doctorName').value,
        username: document.getElementById('doctorUsername').value,
        password: document.getElementById('doctorPassword').value,
        specialty: document.getElementById('doctorSpecialty').value,
        phone: document.getElementById('doctorPhone').value
    };
    
    if (!doctor.full_name || !doctor.username) {
        alert('Vui lòng nhập đầy đủ thông tin!');
        return;
    }
    
    try {
        const res = await fetch('api/doctors.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(doctor)
        });
        const data = await res.json();
        if (data.success) {
            alert('Lưu thành công!');
            closeDoctorModal();
            loadDoctors();
            loadDashboard();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Lỗi kết nối!'); }
}

async function deleteDoctor(id) {
    if (!confirm('Xác nhận xóa bác sĩ này?')) return;
    
    try {
        const res = await fetch('api/doctors.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            alert('Xóa thành công!');
            loadDoctors();
            loadDashboard();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Lỗi kết nối!'); }
}

// ========== Event Listeners ==========
document.getElementById('filterStatus').addEventListener('change', loadAppointments);
document.getElementById('filterDate').addEventListener('change', loadAppointments);
document.getElementById('searchAppointment').addEventListener('input', debounce(loadAppointments, 300));
document.getElementById('filterTargetGroup').addEventListener('change', loadProducts);
document.getElementById('searchProduct').addEventListener('input', debounce(loadProducts, 300));
document.getElementById('searchPatient').addEventListener('input', debounce(loadPatients, 300));

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ========== Init ==========
loadDashboard();
</script>
</body>
</html>
