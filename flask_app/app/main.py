"""
main.py — Blueprint trang công khai: trang chủ, dịch vụ (tìm kiếm + phân trang),
chi tiết dịch vụ, đội ngũ bác sĩ, giới thiệu, liên hệ.
"""
from flask import (Blueprint, current_app, flash, redirect, render_template,
                   request, url_for)
from sqlalchemy import or_

from .extensions import db
from .forms import FeedbackForm
from .models import Feedback, Product, Staff

main_bp = Blueprint("main", __name__)


@main_bp.route("/")
def index():
    """Trang chủ: hero + dịch vụ nổi bật + thống kê + cảm nhận + FAQ."""
    services = Product.query.filter_by(is_active=1).limit(6).all()
    return render_template("main/index.html", services=services)


@main_bp.route("/dich-vu")
def services():
    """Danh sách dịch vụ có TÌM KIẾM + PHÂN TRANG (yêu cầu bắt buộc)."""
    q = (request.args.get("q") or "").strip()
    page = request.args.get("page", 1, type=int)
    query = Product.query.filter_by(is_active=1)
    if q:
        like = f"%{q}%"
        query = query.filter(or_(Product.name.like(like), Product.description.like(like)))
    query = query.order_by(Product.id.desc())
    pagination = query.paginate(page=page, per_page=current_app.config["PER_PAGE"], error_out=False)
    return render_template("main/services.html", pagination=pagination, services=pagination.items, q=q)


@main_bp.route("/dich-vu/<int:pid>")
def service_detail(pid):
    product = Product.query.get_or_404(pid)
    related = (Product.query.filter(Product.id != pid, Product.is_active == 1)
               .order_by(Product.id.desc()).limit(3).all())
    return render_template("main/service_detail.html", p=product, related=related)


@main_bp.route("/bac-si")
def doctors():
    docs = Staff.query.filter_by(role="doctor").all()
    return render_template("main/doctors.html", doctors=docs)


@main_bp.route("/gioi-thieu")
def about():
    return render_template("main/about.html")


@main_bp.route("/lien-he", methods=["GET", "POST"])
def contact():
    form = FeedbackForm()
    if form.validate_on_submit():
        fb = Feedback(
            name=form.name.data.strip(),
            phone=(form.phone.data or "").strip(),
            email=(form.email.data or "").strip(),
            rating=int(form.rating.data),
            type=form.type.data,
            message=form.message.data.strip(),
        )
        db.session.add(fb)
        db.session.commit()
        flash("Cảm ơn phản hồi của bạn! NALI sẽ xem xét và cải thiện. 💙", "success")
        return redirect(url_for("main.contact"))
    return render_template("main/contact.html", form=form)
