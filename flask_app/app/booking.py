"""
booking.py — Blueprint đặt lịch hẹn + gửi email xác nhận (Flask-Mail).
Yêu cầu đăng nhập (giống bản PHP). Ghi vào bảng appointments dùng chung.
"""
from datetime import date, datetime, time, timedelta

from flask import Blueprint, abort, flash, jsonify, redirect, render_template, request, url_for
from flask_login import current_user, login_required

from .extensions import db
from .forms import AppointmentForm
from .mailer import send_email
from .models import Appointment, Patient, Product

booking_bp = Blueprint("booking", __name__)

# Khung giờ 30 phút, từ 08:00 đến trước 20:00.
SLOT_TIMES = [time(hour, minute) for hour in range(8, 20) for minute in (0, 30)]


def _owns_appointment(appt):
    return isinstance(current_user, Patient) and (
        appt.user_id == current_user.id or appt.customer_email == current_user.email
    )


def _can_change(appt):
    if appt.status not in ("pending", "confirmed") or not appt.appointment_date or not appt.appointment_time:
        return False
    return datetime.combine(appt.appointment_date, appt.appointment_time) - datetime.now() >= timedelta(hours=4)


def _booked_times(appointment_date, exclude_id=None):
    query = Appointment.query.filter(
        Appointment.appointment_date == appointment_date,
        Appointment.status.in_(("pending", "confirmed")),
    )
    if exclude_id:
        query = query.filter(Appointment.id != exclude_id)
    return {item.appointment_time for item in query.all()}


def _send_confirmation_email(appt, product):
    """Gửi email xác nhận đã nhận lịch hẹn."""
    svc = product.name if product else "Tư vấn tổng quát"
    send_email(
        "Đã nhận lịch hẹn tại NALI Dental",
        appt.customer_email,
        f"Xin chào {appt.customer_name},\n\n"
        f"NALI Dental đã nhận được lịch hẹn của bạn:\n"
        f"- Dịch vụ: {svc}\n"
        f"- Ngày: {appt.appointment_date}  Giờ: {appt.appointment_time}\n"
        f"- Mã lịch hẹn: #{appt.id}\n\n"
        f"Lễ tân sẽ gọi số {appt.customer_phone} để xác nhận. Cảm ơn bạn!\n"
        f"— NALI Dental Clinic",
    )


@booking_bp.route("/dat-lich", methods=["GET", "POST"])
@login_required
def book():
    form = AppointmentForm()
    reschedule_id = request.args.get("reschedule", type=int)
    existing = db.session.get(Appointment, reschedule_id) if reschedule_id else None
    if existing and (not _owns_appointment(existing) or not _can_change(existing)):
        abort(403)
    # Nạp danh sách dịch vụ vào dropdown
    products = Product.query.filter_by(is_active=1).order_by(Product.name).all()
    form.product_id.choices = [(0, "-- Chọn dịch vụ --")] + [(p.id, p.name) for p in products]

    # Điền sẵn thông tin nếu là khách hàng
    if form.customer_name.data is None and isinstance(current_user, Patient):
        form.customer_name.data = current_user.full_name
        form.customer_phone.data = current_user.phone
        form.customer_email.data = current_user.email

    if request.method == "GET" and existing:
        form.customer_name.data = existing.customer_name
        form.customer_phone.data = existing.customer_phone
        form.customer_email.data = existing.customer_email
        form.product_id.data = int(existing.product_ids or 0)
        form.appointment_date.data = existing.appointment_date
        form.appointment_time.data = existing.appointment_time
        form.notes.data = existing.notes

    if form.validate_on_submit():
        product = db.session.get(Product, form.product_id.data) if form.product_id.data else None
        if form.appointment_time.data not in SLOT_TIMES:
            form.appointment_time.errors.append("Khung giờ không hợp lệ.")
            return render_template("booking/book.html", form=form, products=products, rescheduling=existing)
        if form.appointment_time.data in _booked_times(form.appointment_date.data, existing.id if existing else None):
            form.appointment_time.errors.append("Khung giờ này vừa có người đặt. Vui lòng chọn giờ khác.")
            return render_template("booking/book.html", form=form, products=products, rescheduling=existing)
        appt = existing or Appointment(
            user_id=current_user.id if isinstance(current_user, Patient) else None,
            product_ids=str(product.id) if product else "",
            customer_name=form.customer_name.data.strip(),
            customer_phone=form.customer_phone.data.strip(),
            customer_email=(form.customer_email.data or "").strip(),
            appointment_date=form.appointment_date.data,
            appointment_time=form.appointment_time.data,
            notes=form.notes.data,
            total_price=product.price if product else 0,
            status="pending",
        )
        appt.product_ids = str(product.id) if product else ""
        appt.customer_name = form.customer_name.data.strip()
        appt.customer_phone = form.customer_phone.data.strip()
        appt.customer_email = (form.customer_email.data or "").strip()
        appt.appointment_date = form.appointment_date.data
        appt.appointment_time = form.appointment_time.data
        appt.notes = form.notes.data
        appt.total_price = product.price if product else 0
        appt.status = "pending"
        db.session.add(appt)
        db.session.commit()
        _send_confirmation_email(appt, product)
        flash((f"Đã đổi lịch hẹn #{appt.id}." if existing else f"Đặt lịch thành công! Mã lịch hẹn #{appt.id}.") + " NALI sẽ gọi xác nhận.", "success")
        return redirect(url_for("booking.my_appointments"))

    return render_template("booking/book.html", form=form, products=products, rescheduling=existing)


@booking_bp.route("/dat-lich/khung-gio")
@login_required
def available_slots():
    raw_date = request.args.get("date", "")
    try:
        selected_date = date.fromisoformat(raw_date)
    except ValueError:
        return jsonify({"success": False, "message": "Ngày không hợp lệ."}), 400
    if selected_date < date.today():
        return jsonify({"success": False, "message": "Không thể đặt ngày quá khứ."}), 400
    exclude_id = request.args.get("reschedule", type=int)
    if exclude_id:
        appt = db.session.get(Appointment, exclude_id)
        if not appt or not _owns_appointment(appt):
            abort(403)
    occupied = _booked_times(selected_date, exclude_id)
    now = datetime.now()
    slots = [slot.strftime("%H:%M") for slot in SLOT_TIMES
             if slot not in occupied and datetime.combine(selected_date, slot) > now]
    return jsonify({"success": True, "slots": slots})


@booking_bp.route("/lich-hen-cua-toi")
@login_required
def my_appointments():
    appts = []
    if isinstance(current_user, Patient):
        appts = (Appointment.query
                 .filter((Appointment.user_id == current_user.id) |
                         (Appointment.customer_email == current_user.email))
                 .order_by(Appointment.appointment_date.desc()).all())
    return render_template("booking/my_appointments.html", appts=appts, can_change=_can_change)


@booking_bp.route("/lich-hen-cua-toi/<int:appointment_id>/huy", methods=["POST"])
@login_required
def cancel_appointment(appointment_id):
    appt = db.session.get(Appointment, appointment_id)
    if not appt or not _owns_appointment(appt):
        abort(403)
    if not _can_change(appt):
        flash("Lịch chỉ có thể huỷ trước giờ hẹn ít nhất 4 giờ.", "error")
        return redirect(url_for("booking.my_appointments"))
    appt.status = "cancelled"
    db.session.commit()
    flash(f"Đã huỷ lịch hẹn #{appt.id}.", "success")
    return redirect(url_for("booking.my_appointments"))
