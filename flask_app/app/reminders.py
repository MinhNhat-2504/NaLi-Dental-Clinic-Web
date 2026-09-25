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
from .models import Appointment, MedicalRecord, Patient, Product

_WEEKDAYS = ["Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy", "Chủ Nhật"]


def _hotline() -> str:
    from .clinic import get_clinic
    return get_clinic()["hotline"]


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
    from .clinic import get_clinic
    clinic = get_clinic()
    subject = f"[NALI Dental] Nhắc lịch hẹn {when_label} {t} — {d.strftime('%d/%m/%Y')}"
    body = (
        f"Xin chào {appt.customer_name},\n\n"
        f"NALI Dental xin nhắc anh/chị có lịch hẹn {when_label}:\n"
        f"  • Thời gian: {t}, {_WEEKDAYS[d.weekday()]} {d.strftime('%d/%m/%Y')}\n"
        f"  • Dịch vụ: {_service_names(appt.product_ids)}\n"
        f"  • Mã lịch hẹn: #{appt.id}\n"
        f"  • Chi nhánh chính: {clinic['main_address']} (chi nhánh khác: {site}/lien-he)\n\n"
        f"Lưu ý nhỏ: vui lòng đến trước 10 phút; nếu đang dùng thuốc hoặc có bệnh nền, "
        f"hãy báo bác sĩ khi khám.\n\n"
        f"Cần đổi/huỷ lịch? Xem lịch của anh/chị tại {site}/lich-hen-cua-toi "
        f"hoặc gọi hotline {clinic['hotline']}.\n\n"
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


# ---------- Nhắc TÁI KHÁM (theo hồ sơ bác sĩ ghi) ----------
def find_due_revisits(now: datetime | None = None) -> list[tuple[MedicalRecord, Patient]]:
    """Hồ sơ có next_visit_date đúng N ngày nữa (mặc định 3), bệnh nhân có email, chưa nhắc."""
    now = now or _now_local()
    target = now.date() + timedelta(days=int(current_app.config.get("REVISIT_REMIND_DAYS", 3)))
    rows = (db.session.query(MedicalRecord, Patient)
            .join(Patient, Patient.id == MedicalRecord.patient_id)
            .filter(MedicalRecord.next_visit_date == target,
                    MedicalRecord.revisit_reminder_sent_at.is_(None),
                    Patient.email.isnot(None), Patient.email != "")
            .order_by(MedicalRecord.id).all())
    return rows


def send_revisit_reminders(now: datetime | None = None) -> dict:
    now = now or _now_local()
    due = find_due_revisits(now)
    site = current_app.config.get("SITE_URL", "")
    sent = failed = 0
    for rec, p in due:
        d = rec.next_visit_date
        subject = f"[NALI Dental] Sắp đến hẹn tái khám — {d.strftime('%d/%m/%Y')}"
        body = (f"Xin chào {p.full_name},\n\n"
                f"Bác sĩ NALI có hẹn anh/chị tái khám vào {_WEEKDAYS[d.weekday()]} {d.strftime('%d/%m/%Y')} "
                f"(sau lần khám ngày {rec.visit_date.strftime('%d/%m/%Y')}).\n"
                + (f"Dặn dò lần trước: {rec.prescription}\n" if rec.prescription else "")
                + f"\nAnh/chị đặt lịch tái khám tại {site}/dat-lich hoặc gọi hotline {_hotline()} để chọn giờ phù hợp.\n\n"
                f"Hẹn gặp anh/chị tại NALI!\n— NALI Dental Clinic")
        if send_email(subject, p.email, body):
            rec.revisit_reminder_sent_at = now
            sent += 1
        else:
            failed += 1
    if sent:
        db.session.commit()
    current_app.logger.info("Nhắc tái khám: %d đến hạn, %d đã gửi, %d lỗi", len(due), sent, failed)
    return {"due": len(due), "sent": sent, "failed": failed}


def run_all_reminders(now: datetime | None = None) -> dict:
    """Cron gọi một lần mỗi sáng: nhắc lịch ngày mai + nhắc tái khám."""
    a = send_due_reminders(now)
    r = send_revisit_reminders(now)
    return {**a, "revisit": r}
