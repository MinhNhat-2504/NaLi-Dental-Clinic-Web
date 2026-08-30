"""Đổi mật khẩu, quên/đặt lại mật khẩu qua email, khoá đăng nhập sai nhiều lần, rate-limit API."""
import re

from app import ratelimit
from app.extensions import db, mail
from app.models import Patient


def _login(client, email="khach@test.com", password="matkhau123"):
    return client.post("/dang-nhap", data={"email": email, "password": password}, follow_redirects=True)


def test_change_password_patient_and_admin(client):
    _login(client)
    # sai mật khẩu hiện tại -> không đổi
    r = client.post("/doi-mat-khau", data={"current": "sai", "new": "matkhaumoi1", "confirm": "matkhaumoi1"}, follow_redirects=True)
    assert "không đúng" in r.get_data(as_text=True)
    # đúng -> đổi được, đăng nhập lại bằng mật khẩu mới
    r = client.post("/doi-mat-khau", data={"current": "matkhau123", "new": "matkhaumoi1", "confirm": "matkhaumoi1"}, follow_redirects=True)
    assert "Đã đổi mật khẩu" in r.get_data(as_text=True)
    client.get("/dang-xuat")
    assert "Chào mừng" in _login(client, password="matkhaumoi1").get_data(as_text=True)
    client.get("/dang-xuat")
    # admin cũng đổi được
    _login(client, "admin", "admin123")
    r = client.post("/doi-mat-khau", data={"current": "admin123", "new": "adminmoi123", "confirm": "adminmoi123"}, follow_redirects=True)
    assert "Đã đổi mật khẩu" in r.get_data(as_text=True)


def test_forgot_and_reset_password_flow(app, client):
    with mail.record_messages() as outbox:
        r = client.post("/quen-mat-khau", data={"email": "khach@test.com"}, follow_redirects=True)
        assert "vừa gửi link" in r.get_data(as_text=True)
        assert len(outbox) == 1
        link = re.search(r"https?://\S+/dat-lai-mat-khau/\S+", outbox[0].body).group(0)
        # email không tồn tại -> vẫn báo giống nhau, không gửi mail
        client.post("/quen-mat-khau", data={"email": "ai-do@test.com"}, follow_redirects=True)
        assert len(outbox) == 1
    path = "/" + link.split("/", 3)[3]
    assert client.get(path).status_code == 200
    r = client.post(path, data={"new": "matkhau-reset1", "confirm": "matkhau-reset1"}, follow_redirects=True)
    assert "Đã đặt lại mật khẩu" in r.get_data(as_text=True)
    assert "Chào mừng" in _login(client, password="matkhau-reset1").get_data(as_text=True)
    # link cũ dùng lại -> vô hiệu (hash đã đổi)
    r = client.get(path, follow_redirects=True)
    assert "không hợp lệ" in r.get_data(as_text=True)


def test_login_lockout_after_5_failures(client):
    ratelimit.reset_all()
    for i in range(4):
        r = client.post("/dang-nhap", data={"email": "khach@test.com", "password": "sai"})
        assert "Còn" in r.get_data(as_text=True)
    r = client.post("/dang-nhap", data={"email": "khach@test.com", "password": "sai"})
    assert "tạm khoá" in r.get_data(as_text=True)
    # đúng mật khẩu cũng bị chặn khi đang khoá
    r = client.post("/dang-nhap", data={"email": "khach@test.com", "password": "matkhau123"})
    assert r.status_code == 429
    ratelimit.reset_all()
    assert "Chào mừng" in _login(client).get_data(as_text=True)


def test_chat_api_rate_limit(app, client):
    ratelimit.reset_all()
    app.config["CHAT_RATE_LIMIT"] = 3
    codes = [client.post("/api/chat", json={"session_id": "rl", "message": "hi"}).status_code for _ in range(4)]
    assert codes[-1] == 429 and 429 not in codes[:3]
    ratelimit.reset_all()
