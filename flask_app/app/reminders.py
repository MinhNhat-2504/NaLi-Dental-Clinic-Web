"""
reminders.py — Email nhắc lịch hẹn trước ~24 giờ (bản 0đ, không cần Celery/Redis).

Cách chạy: một "cron" bên ngoài (GitHub Actions, cron-job.org, hoặc `flask send-reminders`
trên máy) gọi mỗi sáng. Hàm chọn các lịch hẹn:
  - trạng thái pending/confirmed, có email khách,
  - diễn ra vào NGÀY MAI (giờ Việt Nam) hoặc hôm nay nhưng chưa tới giờ,
  - chưa nhắc (reminder_sent_at IS NULL)
rồi gửi email và đánh dấu đã nhắc. Gửi lỗi -> KHÔNG đánh dấu, lần chạy sau thử lại.
"""
from __future__ import annotations

from datetime import date, datetime, timedelta
from zoneinfo import ZoneInfo

from flask import current_app
from sqlalchemy import or_

from .extensions import db
from .mailer import send_email
from .models import Appointment, Product

_WEEKDAYS = ["Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy", "Chủ Nhật"]


def _now_local() -> datetime:
    tz = ZoneInfo(current_app.config.get("TIMEZONE", "Asia/Ho_Chi_Minh"))
    return datetime.now(tz).replace(tzinfo=None)


def _service_names(product_ids: str | None) -> str:
    ids = [int(x) for x in (product_ids or "").split(",") if x.strip().isdigit()]
    if not ids:
        return "Khám & tư vấn"
    names = [p.name for p in Product.query.filter(Product.id.in_(ids)).all()]
    return ", ".join(names) or "Khám & tư vấn"


def build_reminder_email(appt: Appointment, when_label: str) -> tuple[str, str]:
    """Trả về (subject, body) của email nhắc lịch."""
    d = appt.appointment_date
    t = appt.appointment_time.strftime("%H:%M") if appt.appointment_time else ""
    site = current_app.config.get("SITE_URL", "")
    subject = f"[NALI Dental] Nhắc lịch hẹn {when_label} {t} — {d.strftime('%d/%m/%Y')}"
    body = (
        f"Xin chào {appt.customer_name},\n\n"
        f"NALI Dental xin nhắc anh/chị có lịch hẹn {when_label}:\n"
        f"  • Thời gian: {t}, {_WEEKDAYS[d.weekday()]} {d.strftime('%d/%m/%Y')}\n"
        f"  • Dịch vụ: {_service_names(appt.product_ids)}\n"
        f"  • Mã lịch hẹn: #{appt.id}\n"
        f"  • Chi nhánh chính: 69/68 Đặng Thùy Trâm, Bình Thạnh, TP.HCM (chi nhánh khác: {site}/lien-he)\n\n"
        f"Lưu ý nhỏ: vui lòng đến trước 10 phút; nếu đang dùng thuốc hoặc có bệnh nền, "
        f"hãy báo bác sĩ khi khám.\n\n"
        f"Cần đổi/huỷ lịch? Xem lịch của anh/chị tại {site}/lich-hen-cua-toi "
        f"hoặc gọi hotline 0945 457 512.\n\n"
        f"Hẹn gặp anh/chị tại NALI! 🦷\n"
        f"— NALI Dental Clinic"
    )
    return subject, body


def find_due_appointments(now: datetime | None = None) -> list[Appointment]:
    """Lịch hẹn cần nhắc tính theo thời điểm `now` (giờ VN, naive)."""
    now = now or _now_local()
    today: date = now.date()
    tomorrow = today + timedelta(days=1)
    q = (Appointment.query
         .filter(Appointment.status.in_(("pending", "confirmed")))
         .filter(Appointment.customer_email.isnot(None), Appointment.customer_email != "")
         .filter(Appointment.reminder_sent_at.is_(None))
         .filter(or_(Appointment.appointment_date == tomorrow,
                     Appointment.appointment_date == today))
         .order_by(Appointment.appointment_date, Appointment.appointment_time))
    out = []
    for a in q.all():
        # Hôm nay thì chỉ nhắc lịch còn ở phía trước (đã qua giờ thì thôi)
        if a.appointment_date == today and a.appointment_time and a.appointment_time <= now.time():
            continue
        out.append(a)
    return out


def send_due_reminders(now: datetime | None = None) -> dict:
    """Gửi email cho các lịch đến hạn. Trả về thống kê để log/hiển thị."""
    now = now or _now_local()
    due = find_due_appointments(now)
    sent = failed = 0
    for a in due:
        label = "ngày mai" if a.appointment_date > now.date() else "hôm nay"
        subject, body = build_reminder_email(a, label)
        if send_email(subject, a.customer_email, body):
            a.reminder_sent_at = now
            sent += 1
        else:
            failed += 1
    if sent:
        db.session.commit()
    current_app.logger.info("Nhắc lịch: %d đến hạn, %d đã gửi, %d lỗi", len(due), sent, failed)
    return {"due": len(due), "sent": sent, "failed": failed, "at": now.isoformat(timespec="minutes")}
