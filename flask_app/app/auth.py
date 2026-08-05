"""
auth.py — Blueprint xác thực: đăng nhập, đăng ký, đăng xuất (Flask-Login).
Đăng nhập chấp nhận cả KHÁCH HÀNG (email, bảng patients) và NHÂN SỰ (username, bảng users).
"""
from urllib.parse import urlparse

from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import login_required, login_user, logout_user

from .extensions import db
from .forms import LoginForm, RegisterForm
from .mailer import send_email
from .models import Patient, Staff

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
        # 1) Thử khách hàng theo email
        user = Patient.query.filter_by(email=ident).first()
        # 2) Nếu không có, thử nhân sự theo username
        if user is None:
            user = Staff.query.filter_by(username=ident).first()

        if user and user.check_password(form.password.data):
            login_user(user, remember=form.remember.data)
            flash(f"Chào mừng {getattr(user, 'full_name', ident)}! 👋", "success")
            # Nhân sự -> vào admin; khách -> về trang chủ
            if isinstance(user, Staff) and user.role == "admin":
                return redirect(url_for("admin.dashboard"))
            next_url = _safe_next(request.args.get("next"))
            return redirect(next_url or url_for("main.index"))
        flash("Tài khoản hoặc mật khẩu không chính xác.", "danger")
    return render_template("auth/login.html", form=form)


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
