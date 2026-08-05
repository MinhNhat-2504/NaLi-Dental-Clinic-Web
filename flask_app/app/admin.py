"""
admin.py — Blueprint quản trị (chỉ dành cho nhân sự role='admin').
Gồm: dashboard thống kê, CRUD dịch vụ, quản lý trạng thái lịch hẹn.
"""
from functools import wraps

from flask import (Blueprint, abort, flash, redirect, render_template, request,
                   url_for)
from flask_login import current_user, login_required
from sqlalchemy import or_

from .extensions import db
from .forms import AdminAppointmentForm, ProductForm
from .mailer import send_email
from .models import Appointment, Feedback, Patient, Product, Staff

admin_bp = Blueprint("admin", __name__, url_prefix="/admin")


def admin_required(f):
    """Chỉ cho phép nhân sự có role='admin'."""
    @wraps(f)
    @login_required
    def wrapper(*args, **kwargs):
        if not isinstance(current_user, Staff) or current_user.role != "admin":
            abort(403)
        return f(*args, **kwargs)
    return wrapper


@admin_bp.route("/")
@admin_required
def dashboard():
    stats = {
        "pending": Appointment.query.filter_by(status="pending").count(),
        "appointments": Appointment.query.count(),
        "patients": Patient.query.count(),
        "products": Product.query.filter_by(is_active=1).count(),
        "doctors": Staff.query.filter_by(role="doctor").count(),
    }
    pending = (Appointment.query.filter_by(status="pending")
               .order_by(Appointment.appointment_date).limit(10).all())
    # Dữ liệu cho biểu đồ trạng thái
    status_counts = {s: Appointment.query.filter_by(status=s).count()
                     for s in ["pending", "confirmed", "completed", "cancelled"]}
    # Dữ liệu biểu đồ cột: số lịch hẹn 7 ngày tới
    from datetime import date, timedelta
    today = date.today()
    week_labels, week_counts = [], []
    for i in range(7):
        d = today + timedelta(days=i)
        week_labels.append(d.strftime("%d/%m"))
        week_counts.append(Appointment.query.filter_by(appointment_date=d).count())
    return render_template("admin/dashboard.html", stats=stats, pending=pending,
                           status_counts=status_counts,
                           week_labels=week_labels, week_counts=week_counts)


# ---------- CRUD Dịch vụ ----------
@admin_bp.route("/dich-vu")
@admin_required
def products():
    q = (request.args.get("q") or "").strip()
    page = request.args.get("page", 1, type=int)
    query = Product.query
    if q:
        query = query.filter(Product.name.like(f"%{q}%"))
    pagination = query.order_by(Product.id.desc()).paginate(page=page, per_page=8, error_out=False)
    return render_template("admin/products.html", pagination=pagination, items=pagination.items, q=q)


@admin_bp.route("/dich-vu/them", methods=["GET", "POST"])
@admin_required
def product_add():
    form = ProductForm()
    if form.validate_on_submit():
        p = Product(name=form.name.data, description=form.description.data,
                    price=form.price.data or 0, duration=form.duration.data or 30,
                    target_group=form.target_group.data, image=form.image.data,
                    is_active=1 if form.is_active.data else 0)
        db.session.add(p)
        db.session.commit()
        flash("Đã thêm dịch vụ mới.", "success")
        return redirect(url_for("admin.products"))
    return render_template("admin/product_form.html", form=form, title="Thêm dịch vụ")


@admin_bp.route("/dich-vu/sua/<int:pid>", methods=["GET", "POST"])
@admin_required
def product_edit(pid):
    p = Product.query.get_or_404(pid)
    form = ProductForm(obj=p)
    if form.validate_on_submit():
        p.name = form.name.data
        p.description = form.description.data
        p.price = form.price.data or 0
        p.duration = form.duration.data or 30
        p.target_group = form.target_group.data
        p.image = form.image.data
        p.is_active = 1 if form.is_active.data else 0
        db.session.commit()
        flash("Đã cập nhật dịch vụ.", "success")
        return redirect(url_for("admin.products"))
    form.is_active.data = bool(p.is_active)
    return render_template("admin/product_form.html", form=form, title="Sửa dịch vụ")


@admin_bp.route("/dich-vu/xoa/<int:pid>", methods=["POST"])
@admin_required
def product_delete(pid):
    p = Product.query.get_or_404(pid)
    db.session.delete(p)
    db.session.commit()
    flash("Đã xóa dịch vụ.", "success")
    return redirect(url_for("admin.products"))


# ---------- Quản lý lịch hẹn (đủ CRUD) ----------
@admin_bp.route("/lich-hen")
@admin_required
def appointments():
    status = request.args.get("status", "")
    q = (request.args.get("q") or "").strip()
    page = request.args.get("page", 1, type=int)
    query = Appointment.query
    if status:
        query = query.filter_by(status=status)
    if q:
        like = f"%{q}%"
        query = query.filter(or_(Appointment.customer_name.like(like),
                                 Appointment.customer_phone.like(like)))
    query = query.order_by(Appointment.appointment_date.desc(), Appointment.appointment_time.desc())
    pagination = query.paginate(page=page, per_page=10, error_out=False)
    return render_template("admin/appointments.html", pagination=pagination,
                           items=pagination.items, status=status, q=q)


@admin_bp.route("/lich-hen/trang-thai/<int:aid>", methods=["POST"])
@admin_required
def appointment_status(aid):
    appt = Appointment.query.get_or_404(aid)
    new_status = request.form.get("status")
    if new_status in ("pending", "confirmed", "completed", "cancelled"):
        appt.status = new_status
        db.session.commit()
        if new_status == "confirmed":
            send_email("Lịch hẹn NALI đã được xác nhận", appt.customer_email,
                       f"Xin chào {appt.customer_name},\n\nLịch hẹn #{appt.id} của bạn vào "
                       f"{appt.appointment_time} ngày {appt.appointment_date} đã được XÁC NHẬN. "
                       f"Hẹn gặp bạn tại NALI Dental!")
        flash(f"Đã cập nhật lịch hẹn #{aid} → {new_status}.", "success")
    return redirect(request.referrer or url_for("admin.appointments"))


@admin_bp.route("/lich-hen/sua/<int:aid>", methods=["GET", "POST"])
@admin_required
def appointment_edit(aid):
    appt = Appointment.query.get_or_404(aid)
    form = AdminAppointmentForm(obj=appt)
    if form.validate_on_submit():
        appt.appointment_date = form.appointment_date.data
        appt.appointment_time = form.appointment_time.data
        appt.status = form.status.data
        appt.admin_notes = form.admin_notes.data
        db.session.commit()
        flash(f"Đã cập nhật lịch hẹn #{aid}.", "success")
        return redirect(url_for("admin.appointments"))
    return render_template("admin/appointment_form.html", form=form, appt=appt)


@admin_bp.route("/lich-hen/xoa/<int:aid>", methods=["POST"])
@admin_required
def appointment_delete(aid):
    appt = Appointment.query.get_or_404(aid)
    db.session.delete(appt)
    db.session.commit()
    flash(f"Đã xóa lịch hẹn #{aid}.", "success")
    return redirect(request.referrer or url_for("admin.appointments"))


# ---------- Quản lý bệnh nhân ----------
@admin_bp.route("/benh-nhan")
@admin_required
def patients():
    q = (request.args.get("q") or "").strip()
    page = request.args.get("page", 1, type=int)
    query = Patient.query
    if q:
        like = f"%{q}%"
        query = query.filter(or_(Patient.full_name.like(like),
                                 Patient.email.like(like), Patient.phone.like(like)))
    pagination = query.order_by(Patient.id.desc()).paginate(page=page, per_page=10, error_out=False)
    return render_template("admin/patients.html", pagination=pagination, items=pagination.items, q=q)


# ---------- Xem phản hồi khách hàng ----------
@admin_bp.route("/phan-hoi")
@admin_required
def feedback():
    page = request.args.get("page", 1, type=int)
    pagination = Feedback.query.order_by(Feedback.id.desc()).paginate(page=page, per_page=10, error_out=False)
    return render_template("admin/feedback.html", pagination=pagination, items=pagination.items)
