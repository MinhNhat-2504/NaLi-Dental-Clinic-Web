"""
booking.py — Blueprint đặt lịch hẹn + gửi email xác nhận (Flask-Mail).
Yêu cầu đăng nhập (giống bản PHP). Ghi vào bảng appointments dùng chung.
"""
from flask import Blueprint, flash, redirect, render_template, url_for
from flask_login import current_user, login_required

from .extensions import db
from .forms import AppointmentForm
from .mailer import send_email
from .models import Appointment, Patient, Product

booking_bp = Blueprint("booking", __name__)


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
    # Nạp danh sách dịch vụ vào dropdown
    products = Product.query.filter_by(is_active=1).order_by(Product.name).all()
    form.product_id.choices = [(0, "-- Chọn dịch vụ --")] + [(p.id, p.name) for p in products]

    # Điền sẵn thông tin nếu là khách hàng
    if form.customer_name.data is None and isinstance(current_user, Patient):
        form.customer_name.data = current_user.full_name
        form.customer_phone.data = current_user.phone
        form.customer_email.data = current_user.email

    if form.validate_on_submit():
        product = Product.query.get(form.product_id.data) if form.product_id.data else None
        appt = Appointment(
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
        db.session.add(appt)
        db.session.commit()
        _send_confirmation_email(appt, product)
        flash(f"Đặt lịch thành công! Mã lịch hẹn #{appt.id}. NALI sẽ gọi xác nhận.", "success")
        return redirect(url_for("booking.my_appointments"))

    return render_template("booking/book.html", form=form, products=products)


@booking_bp.route("/lich-hen-cua-toi")
@login_required
def my_appointments():
    appts = []
    if isinstance(current_user, Patient):
        appts = (Appointment.query
                 .filter((Appointment.user_id == current_user.id) |
                         (Appointment.customer_email == current_user.email))
                 .order_by(Appointment.appointment_date.desc()).all())
    return render_template("booking/my_appointments.html", appts=appts)
