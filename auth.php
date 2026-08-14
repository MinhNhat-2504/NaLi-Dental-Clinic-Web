<?php
require_once 'includes/components.php';

// Nếu đã đăng nhập thì redirect
if ($isLoggedIn) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập / Đăng Ký - NALI Dental Clinic</title>
    <?php renderSeo('Tài khoản NALI Dental', 'Đăng nhập hoặc đăng ký tài khoản để sử dụng các tiện ích đặt lịch của NALI Dental.'); ?>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="favicon.png">
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container { 
            flex: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 60px 20px;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e8f4ff 100%);
        }
        
        .form-wrapper { 
            background: white; 
            padding: 50px 40px; 
            border-radius: 20px; 
            box-shadow: 0 15px 50px rgba(0,0,0,0.15); 
            width: 100%; 
            max-width: 450px; 
            animation: slideUp 0.4s ease-out; 
        }
        
        .form-header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        
        .form-header .logo-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .form-header h2 { 
            color: var(--primary); 
            font-size: 1.8rem; 
            margin: 0 0 10px 0; 
        }
        
        .form-header p { 
            color: var(--text-light); 
            font-size: 0.95rem; 
            margin: 0; 
        }
        
        .form-tabs { 
            display: flex; 
            gap: 0; 
            margin-bottom: 30px; 
            border-bottom: 2px solid var(--border-color); 
        }
        
        .form-tabs button { 
            flex: 1; 
            padding: 15px 0; 
            background: none; 
            border: none; 
            color: var(--text-muted); 
            font-size: 1rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s; 
            border-bottom: 3px solid transparent; 
            margin-bottom: -2px; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .form-tabs button.active { 
            color: var(--primary); 
            border-bottom-color: var(--primary); 
        }
        
        .form-tabs button:hover { 
            color: var(--primary); 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: var(--text-dark); 
            font-weight: 600; 
            font-size: 0.95rem; 
        }
        
        .form-group input { 
            width: 100%; 
            padding: 14px 16px; 
            border: 2px solid var(--border-color); 
            border-radius: 10px; 
            font-size: 1rem; 
            transition: all 0.3s; 
            box-sizing: border-box; 
        }
        
        .form-group input:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(77, 166, 255, 0.15); 
        }
        
        .btn-submit { 
            width: 100%; 
            padding: 16px; 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
            color: white; 
            border: none; 
            border-radius: 10px; 
            font-size: 1.1rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s; 
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(77, 166, 255, 0.4); 
        }
        
        .form-footer { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            font-size: 0.9rem; 
        }
        
        .form-footer a { 
            color: var(--primary); 
        }
        
        .form-footer a:hover { 
            text-decoration: underline; 
        }
        
        .form-checkbox { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        
        .form-checkbox input { 
            width: auto; 
            margin: 0; 
        }
        
        .form-section { 
            display: none; 
        }
        
        .form-section.active { 
            display: block; 
            animation: fadeIn 0.3s ease-out; 
        }
        
        .message { 
            padding: 14px 18px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            text-align: center; 
            display: none; 
            font-weight: 500; 
        }
        
        .message.success { 
            background-color: #d4edda; 
            color: #155724; 
            display: block; 
        }
        
        .message.error { 
            background-color: #f8d7da; 
            color: #721c24; 
            display: block; 
        }
        
        .user-profile { 
            display: none; 
            text-align: center; 
        }
        
        .user-profile.active { 
            display: block; 
        }
        
        .user-avatar { 
            width: 100px; 
            height: 100px; 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 20px; 
            color: white; 
            font-size: 3rem; 
        }
        
        .user-profile h2 { 
            color: var(--text-dark); 
            margin: 15px 0 5px 0; 
        }
        
        .user-profile p { 
            color: var(--text-light); 
            margin: 5px 0 25px 0; 
        }
        
        .btn-logout { 
            width: 100%; 
            padding: 14px; 
            background-color: #dc3545; 
            color: white; 
            border: none; 
            border-radius: 10px; 
            font-size: 1rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s; 
        }
        
        .btn-logout:hover { 
            background-color: #c82333; 
            transform: translateY(-2px); 
        }
        
        .form-group small {
            display: block;
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .login-container {
                padding: 20px 16px;
                min-height: calc(100vh - 60px);
            }
            
            .form-wrapper { 
                padding: 25px 20px; 
                margin: 0;
                border-radius: 15px;
            }
            
            .form-header h2 { 
                font-size: 1.4rem; 
            }
            
            .form-header p {
                font-size: 0.9rem;
            }
            
            .form-tabs { 
                gap: 8px;
                margin-bottom: 25px;
            }
            
            .form-tabs button { 
                font-size: 0.9rem;
                padding: 14px 16px;
                border-radius: 10px;
            }
            
            .form-control {
                padding: 14px 16px;
                font-size: 16px; /* Prevent zoom on iOS */
                border-radius: 10px;
            }
            
            .btn-submit {
                padding: 16px;
                font-size: 1rem;
                min-height: 52px;
                border-radius: 10px;
            }
            
            .btn-submit:active {
                transform: scale(0.98);
            }
        }
        
        @media (max-width: 375px) { 
            .form-wrapper { 
                padding: 20px 16px; 
            } 
            .form-header h2 { 
                font-size: 1.3rem; 
            } 
            .form-tabs button { 
                font-size: 0.85rem;
                padding: 12px 12px;
            } 
        }
    </style>
</head>
<body>

<?php renderHeader(''); ?>

<div class="login-container">
    <div class="form-wrapper">
        <div class="user-profile" id="userProfile">
            <div class="user-avatar"><i class="fas fa-user"></i></div>
            <h2 id="userNameDisplay">Người dùng</h2>
            <p id="userEmailDisplay">email@example.com</p>
            <button class="btn-logout" onclick="handleLogout()">
                <i class="fas fa-sign-out-alt"></i> Đăng Xuất
            </button>
        </div>
        
        <div id="authForms">
            <div class="form-header">
                <div class="logo-icon">🦷</div>
                <h2>Chào Mừng Đến NALI</h2>
                <p>Đăng nhập để quản lý lịch hẹn của bạn</p>
            </div>
            
            <div class="form-tabs">
                <button class="tab-button active" data-tab="login" onclick="switchTab('login')">
                    <i class="fas fa-sign-in-alt"></i> Đăng Nhập
                </button>
                <button class="tab-button" data-tab="signup" onclick="switchTab('signup')">
                    <i class="fas fa-user-plus"></i> Đăng Ký
                </button>
            </div>
            
            <!-- Login Form -->
            <div class="form-section active" id="loginForm">
                <div id="loginMessage" class="message"></div>
                <form onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label for="loginEmail">Email hoặc Số điện thoại</label>
                        <input type="text" id="loginEmail" placeholder="example@email.com hoặc 0901234567" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Mật khẩu</label>
                        <input type="password" id="loginPassword" placeholder="Nhập mật khẩu của bạn" required>
                    </div>
                    <div class="form-footer">
                        <label class="form-checkbox">
                            <input type="checkbox" id="rememberMe"> Ghi nhớ đăng nhập
                        </label>
                        <a href="#">Quên mật khẩu?</a>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-lock"></i> Đăng Nhập
                    </button>
                </form>
            </div>
            
            <!-- Signup Form -->
            <div class="form-section" id="signupForm">
                <div id="signupMessage" class="message"></div>
                <form onsubmit="handleSignup(event)">
                    <div class="form-group">
                        <label for="signupName">Họ và Tên</label>
                        <input type="text" id="signupName" placeholder="Nguyễn Văn A" required>
                    </div>
                    <div class="form-group">
                        <label for="signupEmail">Email</label>
                        <input type="email" id="signupEmail" placeholder="example@email.com" required>
                    </div>
                    <div class="form-group">
                        <label for="signupPhone">Số điện thoại</label>
                        <input type="tel" id="signupPhone" placeholder="0901234567" pattern="0[0-9]{9}" minlength="10" maxlength="10" required>
                        <small>* Bắt đầu bằng 0 và có đủ 10 chữ số</small>
                    </div>
                    <div class="form-group">
                        <label for="signupPassword">Mật khẩu</label>
                        <input type="password" id="signupPassword" placeholder="Tối thiểu 6 ký tự" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="signupConfirmPassword">Xác nhận mật khẩu</label>
                        <input type="password" id="signupConfirmPassword" placeholder="Nhập lại mật khẩu" required minlength="6">
                    </div>
                    <div class="form-footer">
                        <label class="form-checkbox">
                            <input type="checkbox" id="agreeTerms" required> Tôi đồng ý với Điều khoản dịch vụ
                        </label>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Đăng Ký
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.tab-button[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
    document.getElementById(tab === 'login' ? 'loginForm' : 'signupForm').classList.add('active');
}

async function handleLogin(e) {
    e.preventDefault();
    const loginMessage = document.getElementById('loginMessage');
    loginMessage.className = 'message';
    loginMessage.style.display = 'none';
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    
    try {
        const response = await fetch('login.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ email, password }) 
        });
        const result = await response.json();
        
        if (result.success) {
            loginMessage.textContent = 'Đăng nhập thành công!';
            loginMessage.className = 'message success';
            loginMessage.style.display = 'block';
            
            document.getElementById('authForms').style.display = 'none';
            document.getElementById('userProfile').classList.add('active');
            
            if (result.user) {
                document.getElementById('userNameDisplay').textContent = result.user.name + ' (' + result.user.role + ')';
                document.getElementById('userEmailDisplay').textContent = result.user.username;
            } else if (result.patient) {
                document.getElementById('userNameDisplay').textContent = result.patient.name || 'Người dùng';
                document.getElementById('userEmailDisplay').textContent = result.patient.email || email;
            }
            
            setTimeout(() => {
                if (result.user && result.user.role === 'admin') {
                    window.location.href = 'admin_panel.php';
                } else {
                    window.location.href = 'index.php';
                }
            }, 1200);
        } else {
            loginMessage.textContent = result.message || 'Đăng nhập thất bại';
            loginMessage.className = 'message error';
            loginMessage.style.display = 'block';
        }
    } catch (err) {
        loginMessage.textContent = 'Không thể kết nối máy chủ';
        loginMessage.className = 'message error';
        loginMessage.style.display = 'block';
    }
}

async function handleSignup(e) {
    e.preventDefault();
    const signupMessage = document.getElementById('signupMessage');
    signupMessage.className = 'message';
    signupMessage.style.display = 'none';
    
    const name = document.getElementById('signupName').value.trim();
    const email = document.getElementById('signupEmail').value.trim();
    const phone = document.getElementById('signupPhone').value.trim();
    const password = document.getElementById('signupPassword').value.trim();
    const confirmPassword = document.getElementById('signupConfirmPassword').value.trim();
    
    if (password !== confirmPassword) { 
        signupMessage.textContent = 'Mật khẩu xác nhận không khớp'; 
        signupMessage.className = 'message error';
        signupMessage.style.display = 'block';
        return; 
    }
    
    try {
        const response = await fetch('register.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ name, email, phone, password }) 
        });
        const result = await response.json();
        
        if (result.success) { 
            signupMessage.textContent = 'Đăng ký thành công! Vui lòng đăng nhập.'; 
            signupMessage.className = 'message success';
            signupMessage.style.display = 'block';
            setTimeout(() => { switchTab('login'); }, 1500);
        } else { 
            signupMessage.textContent = result.message || 'Đăng ký thất bại'; 
            signupMessage.className = 'message error';
            signupMessage.style.display = 'block';
        }
    } catch (err) { 
        signupMessage.textContent = 'Không thể kết nối máy chủ'; 
        signupMessage.className = 'message error';
        signupMessage.style.display = 'block';
    }
}

async function handleLogout() {
    try {
        const res = await fetch('logout.php');
        const result = await res.json();
        if (result.success) {
            window.location.href = 'index.php';
        } else {
            alert(result.message || 'Đăng xuất thất bại!');
        }
    } catch (e) {
        alert('Không thể kết nối máy chủ!');
    }
}
</script>

</body>
</html>
