"""
auth.py — Blueprint xác thực: đăng nhập, đăng ký, đăng xuất (Flask-Login).
Đăng nhập chấp nhận cả KHÁCH HÀNG (email, bảng patients) và NHÂN SỰ (username, bảng users).
"""
from urllib.parse import urlparse

from flask import Blueprint, current_app, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required, login_user, logout_user
from itsdangerous import BadSignature, SignatureExpired, URLSafeTimedSerializer

from . import ratelimit
from .extensions import db
from .forms import (ChangePasswordForm, ForgotPasswordForm, LoginForm, RegisterForm,
                    ResetPasswordForm)
from .mailer import send_email
from .models import Patient, Staff

LOGIN_MAX_FAILURES = 5      # sai 5 lần trong 15 phút -> khoá 15 phút
LOGIN_WINDOW = 900
LOGIN_LOCK = 900
RESET_TOKEN_MAX_AGE = 1800  # link đặt lại mật khẩu sống 30 phút

auth_bp = Blueprint("auth", __name__)


def _safe_next(target):
    """Chỉ chấp nhận URL nội bộ (chống open-redirect CWE-601)."""
    if not target:
        return None
    parsed = urlparse(target)
    if parsed.scheme == "" and parsed.netloc == "" and target.startswith("/") and not target.startswith("//"):
        return target
    return None


@auth_bp.route("/dang-nhap", methods=["GET", "POST"])
def login():
    form = LoginForm()
    if form.validate_on_submit():
        ident = form.email.data.strip()
        # Chống dò mật khẩu: khoá theo tài khoản và theo IP khi sai quá nhiều lần
        ip = request.headers.get("X-Forwarded-For", request.remote_addr or "?").split(",")[0].strip()
        keys = (f"login:{ident.lower()}", f"login-ip:{ip}")
        wait = max(ratelimit.locked_seconds(k) for k in keys)
        if wait:
            flash(f"Đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {wait // 60 + 1} phút.", "danger")
            return render_template("auth/login.html", form=form), 429
        # 1) Thử khách hàng theo email
        user = Patient.query.filter_by(email=ident).first()
        # 2) Nếu không có, thử nhân sự theo username
        if user is None:
            user = Staff.query.filter_by(username=ident).first()

        if user and user.check_password(form.password.data):
            for k in keys:
                ratelimit.clear(k)
            login_user(user, remember=form.remember.data)
            flash(f"Chào mừng {getattr(user, 'full_name', ident)}! 👋", "success")
            # Nhân sự -> vào admin; khách -> về trang chủ
            if isinstance(user, Staff) and user.role == "admin":
                return redirect(url_for("admin.dashboard"))
            next_url = _safe_next(request.args.get("next"))
            return redirect(next_url or url_for("main.index"))
        left = min(ratelimit.record_failure(k, LOGIN_MAX_FAILURES, LOGIN_WINDOW, LOGIN_LOCK) for k in keys)
        if left == 0:
            flash("Đăng nhập sai quá nhiều lần. Tài khoản tạm khoá 15 phút.", "danger")
        else:
            flash(f"Tài khoản hoặc mật khẩu không chính xác. Còn {left} lần thử.", "danger")
    return render_template("auth/login.html", form=form)


# ---------- Đổi / quên / đặt lại mật khẩu ----------
def _reset_serializer():
    return URLSafeTimedSerializer(current_app.config["SECRET_KEY"], salt="nali-password-reset")


def _reset_token(p: Patient) -> str:
    # Kèm 12 ký tự cuối của hash hiện tại: đổi mật khẩu xong thì link cũ tự vô hiệu
    return _reset_serializer().dumps({"id": p.id, "h": (p.password or "")[-12:]})


def _load_reset_token(token: str):
    try:
        data = _reset_serializer().loads(token, max_age=RESET_TOKEN_MAX_AGE)
    except (BadSignature, SignatureExpired):
        return None
    p = db.session.get(Patient, data.get("id"))
    if not p or (p.password or "")[-12:] != data.get("h"):
        return None
    return p


@auth_bp.route("/doi-mat-khau", methods=["GET", "POST"])
@login_required
def change_password():
    """Cả khách (Patient) lẫn nhân viên/admin (Staff) đều đổi được."""
    form = ChangePasswordForm()
    if form.validate_on_submit():
        user = current_user._get_current_object()
        if not user.check_password(form.current.data):
            flash("Mật khẩu hiện tại không đúng.", "danger")
        elif form.current.data == form.new.data:
            flash("Mật khẩu mới phải khác mật khẩu hiện tại.", "warning")
        else:
            user.password = Patient.make_password(form.new.data)
            db.session.commit()
            flash("Đã đổi mật khẩu.", "success")
            return redirect(url_for("admin.dashboard") if getattr(user, "role", "") == "admin" else url_for("main.index"))
    return render_template("auth/change_password.html", form=form)


@auth_bp.route("/quen-mat-khau", methods=["GET", "POST"])
def forgot_password():
    form = ForgotPasswordForm()
    if form.validate_on_submit():
        email = form.email.data.strip()
        ip = request.headers.get("X-Forwarded-For", request.remote_addr or "?").split(",")[0].strip()
        if not ratelimit.allow(f"forgot:{ip}", 5, 3600):
            flash("Bạn đã yêu cầu quá nhiều lần. Thử lại sau 1 giờ.", "danger")
            return render_template("auth/forgot_password.html", form=form), 429
        p = Patient.query.filter_by(email=email).first()
        if p:
            link = url_for("auth.reset_password", token=_reset_token(p), _external=True)
            send_email("Đặt lại mật khẩu NALI Dental", p.email,
                       f"Xin chào {p.full_name},\n\nBấm vào link sau để đặt lại mật khẩu (hiệu lực 30 phút):\n{link}\n\n"
                       f"Nếu bạn không yêu cầu, bỏ qua email này.\n\n— NALI Dental Clinic")
        # Luôn báo giống nhau để không lộ email nào đã đăng ký
        flash("Nếu email đã đăng ký, NALI vừa gửi link đặt lại mật khẩu. Kiểm tra cả mục Spam nhé.", "success")
        return redirect(url_for("auth.login"))
    return render_template("auth/forgot_password.html", form=form)


@auth_bp.route("/dat-lai-mat-khau/<token>", methods=["GET", "POST"])
def reset_password(token):
    p = _load_reset_token(token)
    if p is None:
        flash("Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Hãy yêu cầu lại.", "danger")
        return redirect(url_for("auth.forgot_password"))
    form = ResetPasswordForm()
    if form.validate_on_submit():
        p.password = Patient.make_password(form.new.data)
        db.session.commit()
        ratelimit.clear(f"login:{p.email.lower()}")
        flash("Đã đặt lại mật khẩu. Mời bạn đăng nhập.", "success")
        return redirect(url_for("auth.login"))
    return render_template("auth/reset_password.html", form=form, email=p.email)


@auth_bp.route("/dang-ky", methods=["GET", "POST"])
def register():
    form = RegisterForm()
    if form.validate_on_submit():
        if Patient.query.filter_by(email=form.email.data.strip()).first():
            flash("Email này đã được đăng ký.", "warning")
        else:
            p = Patient(
                full_name=form.full_name.data.strip(),
                email=form.email.data.strip(),
                phone=form.phone.data.strip(),
                password=Patient.make_password(form.password.data),
            )
            db.session.add(p)
            db.session.commit()
            send_email("Chào mừng đến NALI Dental", p.email,
                       f"Xin chào {p.full_name},\n\nCảm ơn bạn đã đăng ký tài khoản tại NALI Dental! "
                       f"Bạn có thể đăng nhập để đặt lịch và theo dõi lịch hẹn.\n\n— NALI Dental Clinic")
            login_user(p)
            flash("Đăng ký thành công! Chào mừng bạn đến với NALI 💙", "success")
            return redirect(url_for("main.index"))
    return render_template("auth/register.html", form=form)


@auth_bp.route("/dang-xuat")
@login_required
def logout():
    logout_user()
    flash("Đã đăng xuất. Hẹn gặp lại!", "success")
    return redirect(url_for("main.index"))
