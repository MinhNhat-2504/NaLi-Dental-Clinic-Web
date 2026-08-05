/* script.js - Phiên bản Hoàn chỉnh: Hiện sản phẩm & Xem Lịch Hẹn */

// 1. Dữ liệu Dịch vụ: Lấy động từ API
let products = [];

// Biến lưu danh sách đã chọn
let cart = []; 
// Biến tạm để lưu dịch vụ đang xem
let currentSelectedProduct = null;

// Hàm format tiền tệ thống nhất
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
}

// Hàm hiển thị lỗi validation
function showError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    // Thêm border đỏ
    input.style.border = '2px solid #ff6b6b';
    
    // Tạo thông báo lỗi
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.cssText = 'color:#ff6b6b; font-size:13px; margin-top:5px; font-weight:500;';
    errorDiv.textContent = '⚠️ ' + message;
    
    // Thêm sau input
    input.parentElement.appendChild(errorDiv);
    
    // Xóa lỗi khi người dùng nhập
    input.addEventListener('input', function() {
        input.style.border = '1px solid #ddd';
        const error = input.parentElement.querySelector('.error-message');
        if (error) error.remove();
    }, { once: true });
}

// Clinic hours (24h) - adjust as needed
const CLINIC_OPEN_HOUR = 8;   // e.g., 8 => 08:00
const CLINIC_CLOSE_HOUR = 17; // e.g., 17 => 17:00 (last slot)

function populateAppointmentTimeOptions() {
    const sel = document.getElementById('appointmentTime');
    if (!sel) return;
    // avoid duplicating options
    if (sel.options.length > 0) return;
    for (let h = CLINIC_OPEN_HOUR; h <= CLINIC_CLOSE_HOUR; h++) {
        const hh = String(h).padStart(2, '0');
        const val = hh + ':00';
        const opt = document.createElement('option');
        opt.value = val;
        opt.innerText = val;
        sel.appendChild(opt);
    }
}

// ---------- Quản lý Lịch hẹn (appointments) - GLOBAL ----------
let appointments = [];

function loadAppointments() {
    try{
        appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
    }catch(e){ appointments = []; }
    renderAppointmentsCount();
}

function saveAppointments(){
    localStorage.setItem('appointments', JSON.stringify(appointments));
}

function renderAppointmentsCount(){
    const countEl = document.getElementById('cartCount');
    if(countEl) countEl.innerText = cart.length;
}

function renderAppointmentsList(){
    const container = document.getElementById('appointmentsList');
    if(!container) return;
    if(appointments.length === 0){
        container.innerHTML = '<p>Chưa có lịch hẹn.</p>';
        return;
    }
    container.innerHTML = '';
    appointments.forEach(app => {
        const item = document.createElement('div');
        item.className = 'appointment-item';
        item.innerHTML = `
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <img src="${app.image}" onerror="this.src='https://images.unsplash.com/photo-1606811971618-4486d14f3f99?w=200'" style="width:70px;height:50px;object-fit:cover;border-radius:6px; flex-shrink:0;">
                <div style="flex:1;">
                    <div style="font-weight:700;color:#007bff">${app.name}</div>
                    <div style="font-size:0.85rem;color:#666; margin-top:4px;">
                        <div>Dịch vụ: <strong>${app.name}</strong></div>
                        <div>Khách: <strong>${app.customerName || 'N/A'}</strong> | ${app.customerPhone || 'N/A'}</div>
                        <div>${app.datetime}</div>
                    </div>
                </div>
                <div style="text-align:right; flex-shrink:0;">
                    <div style="color:#007bff;font-weight:700">${app.price.toLocaleString('vi-VN')} đ</div>
                    <div style="font-size:0.8rem; color:#999; margin-top:4px;">${app.paymentMethod === 'cash' ? 'Tại quầy' : 'Chuyển khoản'}</div>
                    <button onclick="cancelAppointment(${app.id})" style="margin-top:6px;background:#ff6b6b;color:#fff;border:none;padding:6px 8px;border-radius:6px; cursor:pointer;">Hủy</button>
                </div>
            </div>
            <hr />
        `;
        container.appendChild(item);
    });
}

function openAppointments(){
    loadAppointments();
    renderAppointmentsList();
    const ov = document.getElementById('appointmentsOverlay');
    if(ov) ov.style.display = 'flex';
}

function closeAppointments(){
    const ov = document.getElementById('appointmentsOverlay');
    if(ov) ov.style.display = 'none';
}

function cancelAppointment(id){
    appointments = appointments.filter(a => a.id !== id);
    saveAppointments();
    renderAppointmentsList();
    renderAppointmentsCount();
}

// 2. Hàm hiển thị sản phẩm ra màn hình (dữ liệu động)
async function renderProducts() {
    const container = document.getElementById('productList');
    if (!container) return;
    const fallbackImage = "https://images.unsplash.com/photo-1606811971618-4486d14f3f99?w=500";
    container.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i> Đang tải...</div>';

    try {
        const res = await fetch('api/products.php');
        const data = await res.json();
        if (data.success && data.products.length > 0) {
            products = data.products;
            const html = products.map(product => {
                return `
                <div class="product-card" style="min-height:220px;">
                    <img src="${product.image || fallbackImage}" alt="${product.name}" onerror="this.src='${fallbackImage}'">
                    <h3>${product.name}</h3>
                    <div style="font-size:0.95rem; color:#555; min-height:36px;">${product.description || ''}</div>
                    <div style="margin-top:8px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                      <span class="price">${formatCurrency(product.price)}</span>
                      <button class="btn-add" onclick="addToCartById(${product.id})">Đặt Lịch</button>
                    </div>
                </div>
                `;
            }).join('');
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="text-align:center;color:#888;padding:40px;">Chưa có dịch vụ nào.</div>';
        }
    } catch (err) {
        console.error('Lỗi tải sản phẩm:', err);
        container.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:40px;">Lỗi tải dịch vụ. Vui lòng thử lại.</div>';
    }
// End of renderProducts

// Add item to cart directly when user clicks "Đặt Lịch" (popup removed)
function addToCartById(id) {
    const product = products.find(p => p.id === id);
    if (!product) return;

    const newItem = {
        id: Date.now(),
        name: product.name,
        price: product.price,
        img: product.image
    };

    cart.push(newItem);
    const countEl = document.getElementById('cartCount');
    if (countEl) countEl.innerText = cart.length;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Đã thêm!',
            text: '✅ Đã thêm: ' + newItem.name + ' vào dịch vụ đã chọn!',
            timer: 1500,
            showConfirmButton: false
        });
    } else {
        alert('✅ Đã thêm: ' + newItem.name + ' vào dịch vụ đã chọn!');
    }
}

// Xóa item khỏi giỏ hàng
function removeFromCart(index) {
    if (index < 0 || index >= cart.length) return;
    
    const removedItem = cart[index];
    cart.splice(index, 1);
    
    // Cập nhật số lượng giỏ hàng
    const countEl = document.getElementById('cartCount');
    if (countEl) countEl.innerText = cart.length;
    
    // Nếu giỏ hàng rỗng, đóng modal
    if (cart.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Giỏ hàng trống',
                text: '🛒 Giỏ hàng trống. Vui lòng chọn dịch vụ!',
                timer: 1800,
                showConfirmButton: false
            });
        } else {
            alert('🛒 Giỏ hàng trống. Vui lòng chọn dịch vụ!');
        }
        const overlay = document.getElementById('paymentOverlay');
        if (overlay) overlay.style.display = 'none';
        return;
    }
    
    // Cập nhật lại modal thanh toán
    proceedToPayment();
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Đã xóa!',
            text: '🗑️ Đã xóa: ' + removedItem.name,
            timer: 1200,
            showConfirmButton: false
        });
    } else {
        alert('🗑️ Đã xóa: ' + removedItem.name);
    }
}

// 5. XỬ LÝ: THANH TOÁN TẤT CẢ DỊC VỤ ĐÃ CHỌN
function proceedToPayment() {
    // Kiểm tra đăng nhập trước
    const user = localStorage.getItem('user');
    if (!user) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Vui lòng đăng nhập!',
                text: '⚠️ Vui lòng đăng nhập để đặt lịch hẹn!',
                timer: 1800,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'auth.php';
            });
        } else {
            alert('⚠️ Vui lòng đăng nhập để đặt lịch hẹn!');
            window.location.href = 'auth.php';
        }
        return;
    }
    
    // Empty State cho giỏ hàng trống
    if (cart.length === 0) {
        const emptyStateHTML = `
            <div style="text-align:center; padding:60px 20px;">
                <div style="font-size:80px; color:#ddd; margin-bottom:20px;">🛒</div>
                <h3 style="color:#666; margin-bottom:15px;">Giỏ hàng trống</h3>
                <p style="color:#999; margin-bottom:25px;">Bạn chưa chọn dịch vụ nào. Hãy khám phá các dịch vụ của chúng tôi!</p>
                <button id="viewServicesBtn" style="display:inline-block; background:#4da6ff; color:white; padding:12px 30px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:15px;">Xem dịch vụ</button>
            </div>
        `;
        const overlay = document.getElementById('paymentOverlay');
        const modal = document.getElementById('paymentModal');
        if (modal) {
            modal.innerHTML = emptyStateHTML;
            
            // Đóng modal và scroll về menu khi bấm "Xem dịch vụ"
            document.getElementById('viewServicesBtn').onclick = function() {
                if(overlay) overlay.style.display = 'none';
                // Scroll về menu
                setTimeout(() => {
                    const menuSection = document.getElementById('menu');
                    if (menuSection) menuSection.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            };
        }
        if (overlay) overlay.style.display = 'flex';
        return;
    }

    // Tính tổng tiền từ tất cả dịch vụ trong cart
    let totalPrice = 0;
    let billHtml = '';
    
    cart.forEach((item, index) => {
        totalPrice += item.price;
        billHtml += `
            <div style="margin:8px 0; display:flex; justify-content:space-between; align-items:center; padding:8px; background:#f8f9fa; border-radius:5px;">
                <strong style="flex:1;">${item.name}</strong> 
                <span style="color:#007bff; font-weight:600; margin-right:10px;">${formatCurrency(item.price)}</span>
                <button onclick="removeFromCart(${index})" style="background:#ff6b6b; color:white; border:none; padding:6px 10px; border-radius:5px; cursor:pointer; font-size:14px;" title="Xóa dịch vụ">
                    🗑️
                </button>
            </div>
        `;
    });

    // Lưu thông tin đơn hàng vào pending
    window.pendingCart = {
        items: [...cart],
        totalPrice: totalPrice,
        discountCode: null,
        discountAmount: 0
    };

    // Điền thông tin vào payment modal
    const bill = document.getElementById('modalBillItems');
    const total = document.getElementById('modalTotal');
    if (bill) bill.innerHTML = billHtml;
    if (total) total.innerText = formatCurrency(totalPrice);
    
    // Hide discount row initially
    const discountRow = document.getElementById('discountRow');
    const modalDiscount = document.getElementById('modalDiscount');
    if (discountRow) discountRow.style.display = 'none';
    if (modalDiscount) modalDiscount.innerText = '';
    
    // Clear discount input and message
    const discountCodeInput = document.getElementById('discountCode');
    const discountMessage = document.getElementById('discountMessage');
    if (discountCodeInput) discountCodeInput.value = '';
    if (discountMessage) {
        discountMessage.style.display = 'none';
        discountMessage.innerText = '';
    }
    
    // Set default date and time
    const ngayHenInput = document.getElementById('modalNgayHen');
    const gioHenInput = document.getElementById('modalGioHen');
    if (ngayHenInput) {
        // Đặt ngày tối thiểu là ngày mai
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const minDate = tomorrow.toISOString().split('T')[0];
        ngayHenInput.setAttribute('min', minDate);
        
        // Set giá trị mặc định là ngày mai nếu chưa có
        if (!ngayHenInput.value) {
            ngayHenInput.value = minDate;
        }
    }
    if (gioHenInput && !gioHenInput.value) {
        gioHenInput.value = '09:00';
    }

    // Mở modal thanh toán
    const overlay = document.getElementById('paymentOverlay');
    if (overlay) overlay.style.display = 'flex';
}

// Áp dụng mã giảm giá

// 2. Phân loại và hiển thị sản phẩm vào 4 nhóm
async function renderProducts() {
    const fallbackImage = "https://images.unsplash.com/photo-1606811971618-4486d14f3f99?w=500";
    // 4 container cho từng nhóm
    const containers = {
        children: document.getElementById('children-services'),
        adults: document.getElementById('adults-services'),
        elderly: document.getElementById('elderly-services'),
        chronic: document.getElementById('chronic-services')
    };
    Object.values(containers).forEach(c => { if (c) c.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i> Đang tải...</div>'; });

    // Fetch products and split into groups
    try {
        const res = await fetch('api/products.php');
        const data = await res.json();
        if (data.success && data.products.length > 0) {
            products = data.products;
            // Group products
            const groupMap = {
                children: [],
                adults: [],
                elderly: [],
                chronic: []
            };
            products.forEach(product => {
                const group = (product.target_group || '').toLowerCase();
                if (group.includes('trẻ') || group.includes('children')) groupMap.children.push(product);
                else if (group.includes('người lớn') || group.includes('adults')) groupMap.adults.push(product);
                else if (group.includes('cao tuổi') || group.includes('elderly')) groupMap.elderly.push(product);
                else if (group.includes('mãn tính') || group.includes('chronic')) groupMap.chronic.push(product);
            });
            // Render each group
            Object.entries(groupMap).forEach(([key, list]) => {
                const container = containers[key];
                if (!container) return;
                if (list.length === 0) {
                    container.innerHTML = '<div style="text-align:center;color:#888;padding:40px;">Chưa có dịch vụ nào cho nhóm này.</div>';
                } else {
                    container.innerHTML = list.map(product => `
                        <div class="product-card" style="min-height:220px;">
                            <img src="${product.image || fallbackImage}" alt="${product.name}" onerror="this.src='${fallbackImage}'">
                            <h3>${product.name}</h3>
                            <div style="font-size:0.95rem; color:#555; min-height:36px;">${product.description || ''}</div>
                            <div style="margin-top:8px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                <span class="price">${formatCurrency(product.price)}</span>
                                <button class="btn-add" onclick="addToCartById(${product.id})">Đặt Lịch</button>
                            </div>
                        </div>
                    `).join('');
                }
            });
        } else {
            Object.values(containers).forEach(c => { if (c) c.innerHTML = '<div style="text-align:center;color:#888;padding:40px;">Chưa có dịch vụ nào.</div>'; });
        }
    } catch (err) {
        console.error('Lỗi tải sản phẩm:', err);
        Object.values(containers).forEach(c => { if (c) c.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:40px;">Lỗi tải dịch vụ. Vui lòng thử lại.</div>'; });
    }

}
}


// Khi người dùng xác nhận trong modal thanh toán
async function finalizePayment() {
    // Lấy thông tin khách hàng từ input - ĐỌC ĐÚNG ID TỪ MODAL
    const customerName = document.getElementById('modalHoTen')?.value.trim() || '';
    const customerPhone = document.getElementById('modalSdt')?.value.trim() || '';
    const customerEmail = document.getElementById('modalEmail')?.value.trim() || '';
    const customerNote = document.getElementById('modalGhiChu')?.value.trim() || '';
    const appointmentDate = document.getElementById('modalNgayHen')?.value || '';
    const appointmentTime = document.getElementById('modalGioHen')?.value || '';

    // Xóa thông báo lỗi cũ
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    
    let hasError = false;
    
    // Kiểm tra họ tên
    if (!customerName) {
        showError('modalHoTen', 'Vui lòng nhập họ tên');
        hasError = true;
    }
    
    // Kiểm tra số điện thoại
    if (!customerPhone) {
        showError('modalSdt', 'Vui lòng nhập số điện thoại');
        hasError = true;
    } else if (!/^[0-9]{10,11}$/.test(customerPhone)) {
        showError('modalSdt', 'Số điện thoại không hợp lệ (10-11 số)');
        hasError = true;
    }
    
    // Kiểm tra email
    if (!customerEmail) {
        showError('modalEmail', 'Vui lòng nhập email');
        hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customerEmail)) {
        showError('modalEmail', 'Email không hợp lệ');
        hasError = true;
    }
    
    // Kiểm tra ngày hẹn
    if (!appointmentDate) {
        showError('modalNgayHen', 'Vui lòng chọn ngày hẹn');
        hasError = true;
    }
    
    // Kiểm tra giờ hẹn
    if (!appointmentTime) {
        showError('modalGioHen', 'Vui lòng chọn giờ hẹn');
        hasError = true;
    }
    
    if (hasError) return;

    const method = document.querySelector('input[name="paymentMethod"]:checked')?.value || 'cash';
    const pending = window.pendingCart;
    if (!pending || pending.items.length === 0) return;

    // Disable nút để tránh click nhiều lần
    const confirmBtn = document.getElementById('confirmOrderBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Đang xử lý...';
    }

    // GỬI LÊN DATABASE
    try {
        const productIds = pending.items.map(item => item.id).join(',');
        const totalPrice = (pending.totalPrice || 0) - (pending.discountAmount || 0);
        
        const response = await fetch('book_appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: customerName,
                phone: customerPhone,
                email: customerEmail,
                date: appointmentDate,
                time: appointmentTime,
                service: productIds,
                category: 'cart_checkout',
                notes: customerNote,
                payment_method: method,
                discount_code: pending.discountCode || '',
                discount_amount: pending.discountAmount || 0,
                total_price: totalPrice
            })
        });

        const result = await response.json();
        
        if (!result.success) {
            // THANH TOÁN THẤT BẠI
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Thanh Toán';
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Thanh toán thất bại!',
                    text: '❌ THANH TOÁN THẤT BẠI!\n' + result.message
                });
            } else {
                alert('❌ THANH TOÁN THẤT BẠI!\n\n' + result.message);
            }
            return;
        }
        
        // THANH TOÁN THÀNH CÔNG
        console.log('✅ Đã lưu vào database:', result);
        
        // LƯU THÔNG TIN ĐẶT LỊCH VÀO LOCALSTORAGE
        const bookingData = {
            appointment_id: result.appointment_id,
            name: customerName,
            phone: customerPhone,
            email: customerEmail,
            date: appointmentDate,
            time: appointmentTime,
            total_price: totalPrice,
            payment_method: method,
            discount_code: pending.discountCode || '',
            discount_amount: pending.discountAmount || 0
        };
        localStorage.setItem('lastBooking', JSON.stringify(bookingData));
        
        // Xóa giỏ hàng trước khi redirect
        cart = [];
        localStorage.removeItem('cart');
        
        // CHUYỂN HƯỚNG ĐẾN TRANG CẢM ƠN
        window.location.href = 'success.html';
        
    } catch (error) {
        // LỖI KẾT NỐI
        console.error('❌ Lỗi kết nối database:', error);
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Thanh Toán';
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Thanh toán thất bại!',
                html: '❌ THANH TOÁN THẤT BẠI!<br>Không thể kết nối đến server.<br>Vui lòng kiểm tra:<br>1. XAMPP đã bật chưa?<br>2. Kết nối internet<br>3. File book_appointment.php có tồn tại?<br><br>Lỗi: ' + error.message
            });
        } else {
            alert(
                '❌ THANH TOÁN THẤT BẠI!\n\n' +
                'Không thể kết nối đến server.\n' +
                'Vui lòng kiểm tra:\n' +
                '1. XAMPP đã bật chưa?\n' +
                '2. Kết nối internet\n' +
                '3. File book_appointment.php có tồn tại?\n\n' +
                'Lỗi: ' + error.message
            );
        }
        return;
    }
}

// Gắn sự kiện cho nút hoàn tất thanh toán và đóng modal khi script load
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('confirmOrderBtn');
    if (btn) btn.onclick = finalizePayment;
    const closeBtn = document.getElementById('closeModalBtn');
    const overlay = document.getElementById('paymentOverlay');
    if (closeBtn) closeBtn.onclick = function(){ if(overlay) overlay.style.display = 'none'; };
    if (overlay) overlay.onclick = function(e){ if (e.target === overlay) overlay.style.display = 'none'; };
});

// Khởi tạo khi trang tải
document.addEventListener('DOMContentLoaded', function(){ loadAppointments(); });

// Note: cart popup and inline list removed per user request; cart count still tracks selected items.

// 6. GẮN SỰ KIỆN VÀO ICON GIỎ HÀNG VÀ HIỂN THỊ SẢN PHẨM ĐỘNG
document.addEventListener('DOMContentLoaded', () => {
    renderProducts();
    // Tìm icon giỏ hàng và gắn sự kiện click
    const cartIcon = document.getElementById('cartIcon');
    if (cartIcon) {
        cartIcon.onclick = proceedToPayment; // Khi bấm vào icon thì mở modal thanh toán
    }
});