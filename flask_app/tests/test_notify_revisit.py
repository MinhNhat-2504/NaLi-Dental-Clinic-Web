"""Nhắc tái khám trước N ngày + báo Telegram cho lễ tân."""
from datetime import date, datetime, time, timedelta

from app import notify
from app.extensions import db, mail
from app.models import Appointment, MedicalRecord, Patient


def test_revisit_reminder_sent_once(app):
    from app.reminders import run_all_reminders, send_revisit_reminders
    now = datetime(2026, 9, 1, 8, 0)
    with app.app_context():
        p = Patient.query.filter_by(email="khach@test.com").first()
        db.session.add_all([
            MedicalRecord(patient_id=p.id, visit_date=date(2026, 8, 20), diagnosis="Sâu răng 36",
                          prescription="Tránh đồ cứng", next_visit_date=now.date() + timedelta(days=3)),   # đúng hạn
            MedicalRecord(patient_id=p.id, visit_date=date(2026, 8, 20), diagnosis="Khác",
                          next_visit_date=now.date() + timedelta(days=10)),                                # chưa tới
            MedicalRecord(patient_id=None, visit_date=date(2026, 8, 20), diagnosis="Không tài khoản",
                          next_visit_date=now.date() + timedelta(days=3)),                                 # không email
        ])
        db.session.commit()
        with mail.record_messages() as outbox:
            stats = send_revisit_reminders(now)
            assert stats == {"due": 1, "sent": 1, "failed": 0}
            assert "tái khám" in outbox[0].subject and "Tránh đồ cứng" in outbox[0].body
        assert send_revisit_reminders(now)["sent"] == 0          # không gửi trùng
        all_stats = run_all_reminders(now)
        assert "revisit" in all_stats


def test_telegram_notify_uses_config(app, monkeypatch):
    calls = []

    def fake_post(token, chat_id, text, logger):
        calls.append((token, chat_id, text))
        return True

    monkeypatch.setattr(notify, "_post", fake_post)
    with app.app_context():
        # chưa cấu hình -> im lặng
        app.config.update(TELEGRAM_BOT_TOKEN="", TELEGRAM_CHAT_ID="")
        assert notify.send_telegram("x") is False and calls == []
        # cấu hình -> gửi (TESTING nên gửi đồng bộ)
        app.config.update(TELEGRAM_BOT_TOKEN="123:abc", TELEGRAM_CHAT_ID="42")
        appt = Appointment(id=99, customer_name="A <b>", customer_phone="0900000000",
                           appointment_date=date(2026, 9, 5), appointment_time=time(9, 30), notes="")
        notify.notify_new_appointment(appt, "Tẩy trắng", source="web")
        assert calls and calls[0][1] == "42" and "Lịch hẹn mới #99" in calls[0][2] and "&lt;b&gt;" in calls[0][2]


def test_booking_triggers_telegram(app, client, monkeypatch):
    sent = []
    monkeypatch.setattr(notify, "send_telegram", lambda text, **kw: sent.append(text) or True)
    client.post("/dang-nhap", data={"email": "khach@test.com", "password": "matkhau123"})
    d = (date.today() + timedelta(days=2)).isoformat()
    client.post("/dat-lich", data={"customer_name": "Khách Test", "customer_phone": "0900000000",
                                   "customer_email": "khach@test.com", "product_id": 1,
                                   "appointment_date": d, "appointment_time": "09:00", "notes": "", "deposit": "none"})
    assert any("Lịch hẹn mới" in s for s in sent)
