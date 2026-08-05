"""test_app.py — Kiểm thử tự động cho ứng dụng Flask NALI (pytest).
Chạy:  cd flask_app && pytest -q
"""


def test_home_ok(client):
    r = client.get("/")
    assert r.status_code == 200


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
