"""test_app.py — Kiểm thử tự động cho ứng dụng Flask NALI (pytest).
Chạy:  cd flask_app && pytest -q
"""
from datetime import date, time, timedelta

from app.extensions import db
from app.models import Appointment, BlogPost, FAQ


def test_home_ok(client):
    r = client.get("/")
    assert r.status_code == 200


def test_home_counts_all_active_service_groups(client):
    r = client.get("/")
    body = r.get_data(as_text=True)
    assert "Nhóm chăm sóc" in body
    assert ">3<" in body


def test_services_search(client):
    r = client.get("/dich-vu?q=Implant")
    assert r.status_code == 200
    assert "Implant" in r.get_data(as_text=True)


def test_services_pagination(client):
    r = client.get("/dich-vu?page=2")
    assert r.status_code == 200  # có >6 dịch vụ nên trang 2 tồn tại


def test_admin_requires_login(client):
    r = client.get("/admin/")
    assert r.status_code == 302  # chưa đăng nhập -> chuyển hướng


def test_booking_requires_login(client):
    r = client.get("/dat-lich")
    assert r.status_code == 302


def test_login_admin_then_dashboard(client):
    r = client.post("/dang-nhap", data={"email": "admin", "password": "admin123"},
                    follow_redirects=True)
    assert r.status_code == 200
    assert "Tổng quan" in r.get_data(as_text=True) or "Quản trị" in r.get_data(as_text=True)


def test_register_creates_account(client):
    r = client.post("/dang-ky", data={
        "full_name": "Người Mới", "email": "moi@test.com", "phone": "0912345678",
        "password": "matkhau123", "confirm": "matkhau123"}, follow_redirects=True)
    assert r.status_code == 200


def test_patient_login_and_book(client):
    client.post("/dang-nhap", data={"email": "khach@test.com", "password": "matkhau123"})
    r = client.get("/dat-lich")
    assert r.status_code == 200  # đã đăng nhập -> vào được trang đặt lịch


def test_api_services_json(client):
    r = client.get("/api/services")
    assert r.status_code == 200
    data = r.get_json()
    assert data["success"] and data["count"] >= 8


def test_404_page(client):
    r = client.get("/khong-ton-tai-abc")
    assert r.status_code == 404


def test_robots_and_sitemap(client):
    robots = client.get("/robots.txt")
    sitemap = client.get("/sitemap.xml")
    assert robots.status_code == 200 and "Sitemap:" in robots.get_data(as_text=True)
    assert sitemap.status_code == 200 and "urlset" in sitemap.get_data(as_text=True)


def test_knowledge_empty_state_is_friendly(client):
    r = client.get("/kien-thuc")
    assert r.status_code == 200
    assert "Chưa có bài viết" in r.get_data(as_text=True)


def test_content_seed_adds_information_but_not_fake_reviews(app):
    from app.content_seed import seed_information_content
    with app.app_context():
        faq_added, post_added = seed_information_content()
        assert faq_added == 6 and post_added == 4
        assert FAQ.query.count() == 6 and BlogPost.query.filter_by(status="published").count() == 4


def test_booking_slots_and_duplicate_guard(client):
    client.post("/dang-nhap", data={"email": "khach@test.com", "password": "matkhau123"})
    target = date.today() + timedelta(days=1)
    slots = client.get(f"/dat-lich/khung-gio?date={target.isoformat()}")
    assert slots.status_code == 200 and "09:00" in slots.get_json()["slots"]
    data = {"customer_name": "Khách Test", "customer_phone": "0900000000", "customer_email": "khach@test.com",
            "product_id": "1", "appointment_date": target.isoformat(), "appointment_time": "09:00", "notes": "Test"}
    first = client.post("/dat-lich", data=data)
    assert first.status_code == 302
    second = client.post("/dat-lich", data=data)
    assert second.status_code == 200
    assert "Khung giờ này vừa có người đặt" in second.get_data(as_text=True)


def test_patient_can_cancel_future_appointment(client):
    client.post("/dang-nhap", data={"email": "khach@test.com", "password": "matkhau123"})
    with client.application.app_context():
        appt = Appointment(user_id=1, customer_name="Khách Test", customer_phone="0900000000",
                           customer_email="khach@test.com", appointment_date=date.today() + timedelta(days=2),
                           appointment_time=time(10, 0), status="pending")
        db.session.add(appt)
        db.session.commit()
        appointment_id = appt.id
    response = client.post(f"/lich-hen-cua-toi/{appointment_id}/huy")
    assert response.status_code == 302
    with client.application.app_context():
        assert db.session.get(Appointment, appointment_id).status == "cancelled"


def test_chat_log_written_and_admin_dashboard(client):
    """Mỗi lượt chat được ghi vào chat_logs; admin xem được dashboard chất lượng AI."""
    from app.models import ChatLog
    # AI service không chạy trong test -> proxy trả fallback offline nhưng VẪN phải ghi log
    r = client.post("/api/chat", json={"session_id": "t1", "message": "giá tẩy trắng?"})
    assert r.status_code == 200
    with client.application.app_context():
        assert ChatLog.query.count() >= 1
        assert bool(ChatLog.query.first().unanswered)  # offline fallback -> đánh dấu chưa trả lời được
    client.post("/dang-nhap", data={"email": "admin", "password": "admin123"})
    r = client.get("/admin/ai-chat?show=all")
    assert r.status_code == 200 and "Chất lượng AI" in r.get_data(as_text=True)


def test_reminders_send_for_tomorrow_only_once(app):
    """Email nhắc lịch: chọn đúng lịch ngày mai có email, gửi 1 lần, đánh dấu reminder_sent_at."""
    from datetime import date, datetime, time, timedelta
    from app.extensions import mail
    from app.models import Appointment
    from app.reminders import send_due_reminders

    now = datetime(2026, 8, 20, 8, 0)
    tomorrow = now.date() + timedelta(days=1)
    with app.app_context():
        db.session.add_all([
            Appointment(customer_name="A Mai", customer_phone="0900000001", customer_email="a@test.com",
                        appointment_date=tomorrow, appointment_time=time(9, 0), status="confirmed"),
            Appointment(customer_name="B Kia", customer_phone="0900000002", customer_email="b@test.com",
                        appointment_date=tomorrow + timedelta(days=1), appointment_time=time(9, 0), status="pending"),
            Appointment(customer_name="C Huy", customer_phone="0900000003", customer_email="c@test.com",
                        appointment_date=tomorrow, appointment_time=time(9, 0), status="cancelled"),
            Appointment(customer_name="D KhongMail", customer_phone="0900000004", customer_email="",
                        appointment_date=tomorrow, appointment_time=time(9, 0), status="confirmed"),
        ])
        db.session.commit()
        with mail.record_messages() as outbox:
            stats = send_due_reminders(now)
            assert stats["sent"] == 1 and stats["due"] == 1
            assert len(outbox) == 1
            assert outbox[0].recipients == ["a@test.com"]
            assert "ngày mai" in outbox[0].subject and "#" in outbox[0].body
        a = Appointment.query.filter_by(customer_email="a@test.com").first()
        assert a.reminder_sent_at is not None
        # Chạy lại -> không gửi trùng
        assert send_due_reminders(now)["sent"] == 0


def test_cron_reminders_endpoint_requires_token(app, client):
    app.config["CRON_TOKEN"] = "secret-token"
    assert client.post("/api/cron/reminders").status_code == 401
    assert client.post("/api/cron/reminders", headers={"X-Cron-Token": "sai"}).status_code == 401
    r = client.post("/api/cron/reminders", headers={"X-Cron-Token": "secret-token"})
    assert r.status_code == 200 and r.get_json()["ok"] is True
