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
from .models import Appointment, ChatLog, Feedback, MedicalRecord, Patient, Product, Staff

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


@admin_bp.route("/phan-hoi/<int:fid>/trang-thai", methods=["POST"])
@admin_required
def feedback_status(fid):
    item = Feedback.query.get_or_404(fid)
    status = request.form.get("status")
    if status in ("pending", "approved", "rejected"):
        item.status = status
        db.session.commit()
        flash("Đã cập nhật trạng thái phản hồi.", "success")
    return redirect(url_for("admin.feedback"))


# ---------- Đặt cọc: lễ tân xác nhận đã nhận tiền ----------
@admin_bp.route("/lich-hen/<int:aid>/coc", methods=["POST"])
@admin_required
def appointment_deposit(aid):
    """Đánh dấu đã nhận cọc -> lịch tự chuyển 'confirmed' + email cho khách."""
    from datetime import datetime as _dt
    appt = Appointment.query.get_or_404(aid)
    action = request.form.get("action", "paid")
    if action == "paid" and appt.deposit_status in ("pending", "reported"):
        appt.deposit_status = "paid"
        appt.deposit_paid_at = _dt.now()
        if appt.status == "pending":
            appt.status = "confirmed"
        db.session.commit()
        send_email("NALI đã nhận cọc — lịch hẹn được xác nhận", appt.customer_email,
                   f"Xin chào {appt.customer_name},\n\nNALI đã nhận được tiền cọc "
                   f"{int(appt.deposit_amount or 0):,}đ cho lịch hẹn #{appt.id} "
                   f"({appt.appointment_time} ngày {appt.appointment_date}). Lịch hẹn đã được XÁC NHẬN, "
                   f"số cọc sẽ trừ vào hoá đơn khi khám.\n\nHẹn gặp bạn tại NALI!".replace(",", "."))
        flash(f"Đã ghi nhận cọc cho lịch #{aid} và xác nhận lịch.", "success")
    elif action == "cancel_deposit" and appt.deposit_status:
        appt.deposit_status = None
        appt.deposit_amount = None
        db.session.commit()
        flash(f"Đã bỏ yêu cầu cọc của lịch #{aid}.", "success")
    return redirect(request.referrer or url_for("admin.appointments"))


# ---------- Hồ sơ bệnh nhân (medical_records) ----------
def _patient_for_appointment(appt):
    """Tìm tài khoản bệnh nhân gắn với lịch hẹn: theo user_id, rồi email, rồi SĐT."""
    if appt.user_id:
        p = db.session.get(Patient, appt.user_id)
        if p:
            return p
    if appt.customer_email:
        p = Patient.query.filter_by(email=appt.customer_email).first()
        if p:
            return p
    return Patient.query.filter_by(phone=appt.customer_phone).first()


@admin_bp.route("/lich-hen/<int:aid>/ho-so", methods=["GET", "POST"])
@admin_required
def record_for_appointment(aid):
    """Ghi/sửa hồ sơ khám cho một lịch hẹn (mỗi lịch 1 hồ sơ)."""
    from .forms import MedicalRecordForm
    appt = Appointment.query.get_or_404(aid)
    patient = _patient_for_appointment(appt)
    rec = MedicalRecord.query.filter_by(appointment_id=aid).first()
    form = MedicalRecordForm(obj=rec)
    if request.method == "GET" and rec is None:
        form.visit_date.data = appt.appointment_date
    if form.validate_on_submit():
        if rec is None:
            rec = MedicalRecord(appointment_id=aid, patient_id=patient.id if patient else None,
                                doctor_id=current_user.id)
            db.session.add(rec)
        rec.visit_date = form.visit_date.data
        rec.diagnosis = form.diagnosis.data
        rec.treatment = form.treatment.data
        rec.prescription = form.prescription.data
        rec.next_visit_date = form.next_visit_date.data
        if form.mark_completed.data and appt.status in ("pending", "confirmed"):
            appt.status = "completed"
        db.session.commit()
        flash(f"Đã lưu hồ sơ khám cho lịch #{aid}.", "success")
        return redirect(url_for("admin.appointments"))
    return render_template("admin/record_form.html", form=form, appt=appt, patient=patient, rec=rec)


@admin_bp.route("/benh-nhan/<int:pid>/ho-so")
@admin_required
def patient_records(pid):
    """Toàn bộ hồ sơ khám của một bệnh nhân."""
    patient = Patient.query.get_or_404(pid)
    records = (MedicalRecord.query.filter_by(patient_id=pid)
               .order_by(MedicalRecord.visit_date.desc(), MedicalRecord.id.desc()).all())
    doctor_ids = {r.doctor_id for r in records if r.doctor_id}
    doctors = {s.id: s for s in Staff.query.filter(Staff.id.in_(doctor_ids)).all()} if doctor_ids else {}
    return render_template("admin/patient_records.html", patient=patient, records=records, doctors=doctors)


# ---------- Chất lượng chatbot AI ----------
@admin_bp.route("/ai-chat")
@admin_required
def ai_chat_quality():
    """Dashboard đo chất lượng chatbot: tổng lượt, tỉ lệ trả lời được, độ trễ, câu chưa trả lời."""
    from sqlalchemy import func
    total = ChatLog.query.count()
    unanswered = ChatLog.query.filter_by(unanswered=True).count()
    avg_ms = db.session.query(func.avg(ChatLog.latency_ms)).scalar() or 0
    by_mode = dict(db.session.query(ChatLog.mode, func.count(ChatLog.id)).group_by(ChatLog.mode).all())
    show = request.args.get("show", "unanswered")
    page = request.args.get("page", 1, type=int)
    q = ChatLog.query
    if show == "unanswered":
        q = q.filter_by(unanswered=True)
    pagination = q.order_by(ChatLog.created_at.desc()).paginate(page=page, per_page=20, error_out=False)
    stats = {"total": total, "unanswered": unanswered,
             "answer_rate": round(100 * (total - unanswered) / total, 1) if total else 0,
             "avg_ms": int(avg_ms), "by_mode": by_mode}
    return render_template("admin/ai_chat.html", stats=stats, pagination=pagination,
                           items=pagination.items, show=show)
