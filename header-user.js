// Header User Management - Kiểm tra user và hiển thị link admin
(function() {
    const user = localStorage.getItem('user');
    if (user) {
        try {
            const userData = JSON.parse(user);
            const loginBtn = document.getElementById('loginBtn');
            const logoutBtn = document.getElementById('logoutBtn');
            const adminLink = document.getElementById('adminLink');
            
            if (loginBtn) loginBtn.style.display = 'none';
            if (logoutBtn) logoutBtn.style.display = 'inline-block';
            
            // Chỉ hiển thị link admin nếu role là admin
            if (adminLink && userData.role === 'admin') {
                adminLink.style.display = 'inline-block';
            }
        } catch (e) {
            console.error('Error parsing user data:', e);
        }
    }
    
    // ========== HIGHLIGHT ACTIVE NAV LINK ==========
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('nav a');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        // Check exact match or if it's a hash link on index
        if (href === currentPage || 
            (currentPage === 'index.html' && href === 'index.html#menu') ||
            (currentPage === '' && href === 'index.html#menu')) {
            link.classList.add('active');
        }
    });
})();

// Hàm đăng xuất
async function logout() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Bạn chắc chắn muốn đăng xuất?',
            text: 'Hẹn gặp lại bạn lần sau nhé!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Vâng, đăng xuất!',
            cancelButtonText: 'Huỷ bỏ'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    await fetch('logout.php');
                    localStorage.clear();
                    window.location.href = 'index.php';
                } catch (error) {
                    console.error('Logout error:', error);
                    localStorage.clear();
                    window.location.href = 'index.php';
                }
            }
        });
    } else {
        // Fallback: no SweetAlert2 loaded
        try {
            await fetch('logout.php');
            localStorage.clear();
            window.location.href = 'index.php';
        } catch (error) {
            console.error('Logout error:', error);
            localStorage.clear();
            window.location.href = 'index.php';
        }
    }
}
