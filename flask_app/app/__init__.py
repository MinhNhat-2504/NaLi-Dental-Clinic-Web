"""
app/__init__.py — App Factory theo mô hình "Large Application Structure".
create_app() khởi tạo Flask, gắn extension, đăng ký blueprint.
"""
from datetime import datetime
import os

from flask import Flask

from config import Config

from .extensions import csrf, db, login_manager, mail, migrate


def create_app(config_class=Config):
    app = Flask(__name__, static_folder="static", template_folder="templates")
    app.config.from_object(config_class)

    # Gắn extension vào app
    db.init_app(app)
    migrate.init_app(app, db)   # bật `flask db init/migrate/upgrade` (Alembic)
    login_manager.init_app(app)
    mail.init_app(app)
    csrf.init_app(app)

    # Nạp models (đăng ký user_loader của Flask-Login)
    from . import models  # noqa: F401

    # Giúp môi trường local/demo không lỗi 500 nếu vừa bổ sung model/bảng nội dung.
    # Production vẫn tắt mặc định; chạy `flask --app run.py init-db` hoặc migration trước deploy.
    if app.config.get("AUTO_CREATE_SCHEMA"):
        with app.app_context():
            try:
                db.create_all()
            except Exception as exc:  # Không che mất lỗi cấu hình DB, chỉ ghi rõ trong log.
                app.logger.warning("Không thể tự tạo schema: %s", exc)

    # Đăng ký blueprint
    from .admin import admin_bp
    from .api import api_bp
    from .auth import auth_bp
    from .booking import booking_bp
    from .main import main_bp

    app.register_blueprint(main_bp)
    app.register_blueprint(auth_bp)
    app.register_blueprint(booking_bp)
    app.register_blueprint(admin_bp)
    app.register_blueprint(api_bp)

    # Biến & hàm dùng chung cho mọi template
    _WEAK = {"tram-rang-sua.jpg", "nieng-rang-tre.jpg", "fluoride.jpg", "ham-gia.jpg",
             "nha-chu.jpg", "tu-van-cao-tuoi.jpg", "default.jpg", "", None}
    _GRAD = {"children": "linear-gradient(135deg,#ffb15e,#f39c12)",
             "adults": "linear-gradient(135deg,#4da6ff,#2c7ad1)",
             "elderly": "linear-gradient(135deg,#2bd47e,#12a85f)",
             "chronic": "linear-gradient(135deg,#b06bf0,#8854d0)"}

    def svc_rating(pid):
        return f"{4.7 + ((pid * 7) % 3) * 0.1:.1f}"

    def svc_reviews(pid):
        return 48 + (pid * 37) % 420

    def is_weak_image(img):
        return img in _WEAK

    def svc_grad(group):
        return _GRAD.get(group, _GRAD["adults"])

    def service_card_image(group, name=""):
        featured = {
            "Tẩy trắng răng Laser": "service-whitening-ai.webp",
            "Bọc răng sứ Titan": "service-veneer-ai.webp",
            "Niềng răng Invisalign": "service-aligner-ai.webp",
            "Cấy ghép Implant": "service-implant-ai.webp",
            "Nhổ răng khôn": "service-extraction-ai.webp",
            "Điều trị tủy răng": "service-root-canal-ai.webp",
        }
        return featured.get(name) or {
            "adults": "service-whitening-ai.webp",
            "children": "service-children-ai.webp",
            "elderly": "service-elderly-ai.webp",
            "chronic": "service-implant-ai.webp",
        }.get(group, "service-whitening-ai.webp")

    def svc_icon(name):
        n = (name or "").lower()
        if "niềng" in n or "trẻ" in n: return "fa-child"
        if "fluoride" in n or "ngừa" in n: return "fa-shield-heart"
        if "nha chu" in n: return "fa-notes-medical"
        if "tư vấn" in n: return "fa-user-doctor"
        if "cao răng" in n or "sạch" in n: return "fa-broom"
        return "fa-tooth"

    @app.context_processor
    def inject_globals():
        return dict(AI_SERVICE_URL=app.config["AI_SERVICE_URL"], current_year=datetime.now().year,
                    svc_rating=svc_rating, svc_reviews=svc_reviews, is_weak_image=is_weak_image,
                    svc_grad=svc_grad, svc_icon=svc_icon, service_card_image=service_card_image)

    # Trang lỗi thân thiện (không lộ traceback khi DB/dịch vụ lỗi)
    from flask import render_template

    @app.errorhandler(404)
    def not_found(e):
        return render_template("error.html", code=404,
                               msg="Trang bạn tìm không tồn tại hoặc đã được chuyển đi."), 404

    @app.errorhandler(500)
    def server_error(e):
        db.session.rollback()
        return render_template("error.html", code=500,
                               msg="Có lỗi hệ thống. Vui lòng thử lại hoặc gọi hotline 0945 457 512."), 500

    @app.errorhandler(403)
    def forbidden(e):
        return render_template("error.html", code=403,
                               msg="Bạn không có quyền truy cập trang này."), 403

    # --- Lệnh CLI: dựng & seed CSDL từ phía Flask (Flask-SQLAlchemy) ---
    @app.cli.command("init-db")
    def init_db():
        """Tạo toàn bộ bảng theo model SQLAlchemy (nếu chưa có)."""
        db.create_all()
        print("✔ Đã tạo bảng theo model (db.create_all).")

    @app.cli.command("seed-db")
    def seed_db():
        """Chèn dữ liệu mẫu nếu bảng đang trống."""
        from .models import Patient, Product, Staff
        if Product.query.count() == 0:
            samples = [
                ("Tẩy trắng răng Laser", "Làm trắng răng bằng tia Laser an toàn", 2500000, "service-whitening-ai.webp", "adults", 60),
                ("Cấy ghép Implant", "Trồng răng Implant công nghệ Hàn Quốc", 18000000, "implant.jpg", "adults", 120),
                ("Nhổ răng khôn", "Nhổ răng khôn an toàn không đau", 1500000, "nho-rang-khon.jpg", "adults", 45),
                ("Lấy cao răng", "Làm sạch cao răng, vệ sinh răng miệng", 200000, "cao-voi-rang.jpg", "adults", 30),
            ]
            for n, d, pr, im, tg, du in samples:
                db.session.add(Product(name=n, description=d, price=pr, image=im, target_group=tg, duration=du))
        if Staff.query.filter_by(username="admin").first() is None:
            initial_admin_password = os.getenv("INITIAL_ADMIN_PASSWORD")
            if not initial_admin_password:
                raise RuntimeError("Set INITIAL_ADMIN_PASSWORD before running flask seed-db.")
            db.session.add(Staff(username="admin", password=Patient.make_password(initial_admin_password),
                                 full_name="Quản Trị Viên", role="admin"))
        db.session.commit()
        print("Seed data created; the admin password comes from INITIAL_ADMIN_PASSWORD.")

    @app.cli.command("send-reminders")
    def send_reminders():
        """Gửi email nhắc các lịch hẹn ngày mai (chạy tay hoặc qua cron)."""
        from .reminders import send_due_reminders
        stats = send_due_reminders()
        print(f"Nhac lich: {stats['due']} den han, {stats['sent']} da gui, {stats['failed']} loi.")

    @app.cli.command("seed-content")
    def seed_content():
        """Chèn FAQ và bài viết thông tin, không tạo đánh giá khách hàng giả."""
        from .content_seed import seed_information_content
        faq_added, post_added = seed_information_content()
        print(f"Added {faq_added} FAQs and {post_added} knowledge posts.")

    return app
