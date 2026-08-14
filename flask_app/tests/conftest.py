"""conftest.py — Fixtures pytest: app dùng SQLite in-memory, tách khỏi MySQL thật."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app import create_app  # noqa: E402
from app.extensions import db  # noqa: E402
from app.models import Patient, Product, Staff  # noqa: E402
from config import Config  # noqa: E402


class TestConfig(Config):
    TESTING = True
    SQLALCHEMY_DATABASE_URI = "sqlite:///:memory:"
    WTF_CSRF_ENABLED = False       # tắt CSRF để test form dễ dàng
    MAIL_SUPPRESS_SEND = True      # không gửi email thật khi test
    SECRET_KEY = "test-secret"


@pytest.fixture()
def app():
    app = create_app(TestConfig)
    with app.app_context():
        db.create_all()
        # Seed dữ liệu mẫu
        for i in range(8):  # >6 để test phân trang
            db.session.add(Product(name=f"Dịch vụ {i} Implant", description="mô tả",
                                   price=1000000 + i, target_group=("adults", "children", "elderly")[i % 3], duration=30, is_active=1))
        db.session.add(Staff(username="admin", password=Patient.make_password("admin123"),
                             full_name="Admin", role="admin"))
        db.session.add(Patient(full_name="Khách Test", email="khach@test.com",
                               phone="0900000000", password=Patient.make_password("matkhau123")))
        db.session.commit()
        yield app
        db.session.remove()
        db.drop_all()


@pytest.fixture()
def client(app):
    return app.test_client()
