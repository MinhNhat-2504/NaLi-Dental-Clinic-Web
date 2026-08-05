"""
models.py — Các model SQLAlchemy ánh xạ tới CSDL nali_dental (dùng chung với bản PHP).

Gồm: Patient (khách hàng), Staff (nhân sự: admin/bác sĩ/lễ tân), Product (dịch vụ),
Appointment (lịch hẹn - schema phẳng), Feedback (phản hồi).

Mật khẩu trong DB do PHP băm bằng bcrypt (tiền tố $2y$). Ta dùng thư viện bcrypt
để kiểm tra, quy đổi $2y$ -> $2b$ cho tương thích với Python.
"""
from datetime import datetime

import bcrypt
from flask_login import UserMixin

from .extensions import db, login_manager


def _check_bcrypt(password: str, hashed: str) -> bool:
    """So khớp mật khẩu với hash bcrypt (tương thích hash $2y$ của PHP)."""
    if not hashed:
        return False
    try:
        h = hashed.replace("$2y$", "$2b$", 1).encode("utf-8")
        return bcrypt.checkpw(password.encode("utf-8"), h)
    except (ValueError, TypeError):
        return False


def _hash_bcrypt(password: str) -> str:
    """Băm mật khẩu bằng bcrypt cho tài khoản đăng ký mới."""
    return bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


class Patient(UserMixin, db.Model):
    """Khách hàng (đăng nhập phía người dùng)."""
    __tablename__ = "patients"

    id = db.Column(db.Integer, primary_key=True)
    full_name = db.Column(db.String(100), nullable=False)
    email = db.Column(db.String(100), unique=True, nullable=False)
    password = db.Column(db.String(255), nullable=False)
    phone = db.Column(db.String(15), nullable=False)
    gender = db.Column(db.Enum("Nam", "Nữ", "Khác"), default="Khác")
    birthday = db.Column(db.Date, nullable=True)
    address = db.Column(db.Text, nullable=True)
    created_at = db.Column(db.TIMESTAMP, default=datetime.utcnow)

    # Flask-Login: id có tiền tố để phân biệt với Staff
    def get_id(self):
        return f"p:{self.id}"

    @property
    def role(self):
        return "patient"

    def check_password(self, password):
        return _check_bcrypt(password, self.password)

    @staticmethod
    def make_password(password):
        return _hash_bcrypt(password)


class Staff(UserMixin, db.Model):
    """Nhân sự: admin / bác sĩ / lễ tân (đăng nhập phía quản trị)."""
    __tablename__ = "users"

    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(50), unique=True, nullable=False)
    password = db.Column(db.String(255), nullable=False)
    full_name = db.Column(db.String(100), nullable=False)
    role = db.Column(db.Enum("admin", "doctor", "receptionist"), default="doctor")
    avatar = db.Column(db.String(255), nullable=True)
    phone = db.Column(db.String(15), nullable=True)
    specialty = db.Column(db.String(100), nullable=True)
    created_at = db.Column(db.TIMESTAMP, default=datetime.utcnow)

    def get_id(self):
        return f"s:{self.id}"

    def check_password(self, password):
        return _check_bcrypt(password, self.password)


class Product(db.Model):
    """Dịch vụ nha khoa."""
    __tablename__ = "products"

    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(150), nullable=False)
    description = db.Column(db.Text)
    price = db.Column(db.Numeric(12, 2), default=0)
    image = db.Column(db.String(255))
    target_group = db.Column(db.String(50), default="adults")
    duration = db.Column(db.Integer, default=30)
    is_active = db.Column(db.SmallInteger, default=1)
    created_at = db.Column(db.TIMESTAMP, default=datetime.utcnow)

    @property
    def price_text(self):
        try:
            return f"{int(self.price):,}".replace(",", ".") + "đ" if self.price else "Liên hệ"
        except (TypeError, ValueError):
            return "Liên hệ"


class Appointment(db.Model):
    """Lịch hẹn (schema phẳng, khớp bản PHP)."""
    __tablename__ = "appointments"

    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, nullable=True)
    product_ids = db.Column(db.String(255))
    customer_name = db.Column(db.String(100), nullable=False)
    customer_phone = db.Column(db.String(15), nullable=False)
    customer_email = db.Column(db.String(100))
    appointment_date = db.Column(db.Date, nullable=False)
    appointment_time = db.Column(db.Time, nullable=False)
    notes = db.Column(db.Text)
    admin_notes = db.Column(db.Text)
    status = db.Column(db.Enum("pending", "confirmed", "completed", "cancelled"), default="pending")
    payment_method = db.Column(db.Enum("cash", "transfer", "card"), default="cash")
    total_price = db.Column(db.Numeric(12, 2), default=0)
    created_at = db.Column(db.TIMESTAMP, default=datetime.utcnow)


class Feedback(db.Model):
    """Phản hồi / đánh giá của khách."""
    __tablename__ = "feedback"

    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    phone = db.Column(db.String(15))
    email = db.Column(db.String(100))
    rating = db.Column(db.SmallInteger)
    type = db.Column(db.String(50))
    message = db.Column(db.Text)
    created_at = db.Column(db.TIMESTAMP, default=datetime.utcnow)


@login_manager.user_loader
def load_user(user_id):
    """Nạp user theo id có tiền tố: 'p:' = Patient, 's:' = Staff."""
    try:
        kind, raw = user_id.split(":", 1)
        if kind == "p":
            return db.session.get(Patient, int(raw))
        if kind == "s":
            return db.session.get(Staff, int(raw))
    except (ValueError, AttributeError):
        return None
    return None
