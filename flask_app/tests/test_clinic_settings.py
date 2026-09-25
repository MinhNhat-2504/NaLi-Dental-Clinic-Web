"""Cài đặt phòng khám: admin sửa hotline/chi nhánh -> footer, trang liên hệ và email đọc theo; DB trống dùng mặc định."""
from app.clinic import DEFAULTS, get_clinic, parse_branches_text


def test_defaults_when_table_empty(app, client):
    with app.app_context():
        c = get_clinic()
        assert c["hotline"] == DEFAULTS["hotline"] and len(c["branches"]) == 3
    html = client.get("/").get_data(as_text=True)
    assert "0945 457 512" in html and "Đặng Thùy Trâm" in html


def test_parse_branches_text():
    out = parse_branches_text("A | 1 Đường X\nkhông có dấu gạch\n B | 2 Đường Y ")
    assert out == [{"name": "A", "address": "1 Đường X"}, {"name": "B", "address": "2 Đường Y"}]


def test_admin_saves_settings_and_pages_follow(app, client):
    assert client.get("/admin/cai-dat").status_code in (302, 403)      # chưa đăng nhập
    client.post("/dang-nhap", data={"email": "admin", "password": "admin123"})
    assert client.get("/admin/cai-dat").status_code == 200
    r = client.post("/admin/cai-dat", data={
        "name": "NALI Dental Clinic", "hotline": "0911 222 333", "email": "hello@nali.vn",
        "hours_text": "T2 - T7: 09:00 - 18:00", "open_hour": 9, "close_hour": 18,
        "branches_text": "Thủ Đức | 1 Võ Văn Ngân, TP. Thủ Đức\nQuận 7 | 2 Nguyễn Thị Thập, Quận 7",
    }, follow_redirects=True)
    assert r.status_code == 200 and "Đã lưu" in r.get_data(as_text=True)
    with app.app_context():
        c = get_clinic()
        assert c["hotline"] == "0911 222 333" and c["branches"][0]["name"] == "Thủ Đức" and c["main_address"].startswith("1 Võ Văn Ngân")
    client.get("/dang-xuat")
    home = client.get("/").get_data(as_text=True)
    assert "0911 222 333" in home and "Võ Văn Ngân" in home and "Đặng Thùy Trâm" not in home
    contact = client.get("/lien-he").get_data(as_text=True)
    assert "Quận 7" in contact and "hello@nali.vn" in contact
