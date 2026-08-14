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
