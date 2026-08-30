"""
main.py — Blueprint trang công khai: trang chủ, dịch vụ (tìm kiếm + phân trang),
chi tiết dịch vụ, đội ngũ bác sĩ, giới thiệu, liên hệ.
"""
from flask import (Blueprint, Response, current_app, flash, redirect, render_template,
                   request, url_for)
from xml.sax.saxutils import escape
from sqlalchemy import or_

from .cache import cached
from .extensions import db
from .forms import FeedbackForm
from .notify import notify_feedback
from .models import BlogPost, CaseStudy, FAQ, Feedback, Product, Staff

main_bp = Blueprint("main", __name__)


@main_bp.route("/healthz")
def healthz():
    """Health check cho Render: app sống là 200 (không đụng DB để DB sập không kéo deploy fail)."""
    return Response('{"ok": true}', mimetype="application/json")


@main_bp.route("/robots.txt")
def robots():
    body = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nSitemap: " + url_for("main.sitemap", _external=True) + "\n"
    return Response(body, mimetype="text/plain")


@main_bp.route("/sitemap.xml")
def sitemap():
    paths = [url_for("main.index", _external=True), url_for("main.services", _external=True),
             url_for("main.about", _external=True), url_for("main.doctors", _external=True),
             url_for("main.contact", _external=True)]
    paths.extend(url_for("main.service_detail", pid=p.id, _external=True)
                 for p in Product.query.filter_by(is_active=1).all())
    paths.append(url_for("main.knowledge", _external=True))
    paths.extend(url_for("main.knowledge_detail", slug=b.slug, _external=True)
                 for b in BlogPost.query.filter_by(status="published").all())
    paths.append(url_for("main.cases", _external=True))
    rows = "".join(f"<url><loc>{escape(path)}</loc></url>" for path in paths)
    return Response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' + rows + '</urlset>', mimetype="application/xml")


@main_bp.route("/")
def index():
    """Trang chủ: hero + dịch vụ nổi bật + thống kê + cảm nhận + FAQ."""
    def _load():
        # 5 truy vấn tới DB cloud ở xa -> gom lại và cache 60s (admin sửa thì cache tự xoá)
        return {
            "services": Product.query.filter_by(is_active=1).limit(6).all(),
            "approved_faqs": [(f.question, f.answer) for f in FAQ.query.filter_by(is_active=1).order_by(FAQ.sort_order, FAQ.id).all()],
            "approved_feedback": (Feedback.query.filter_by(status="approved")
                                  .order_by(Feedback.created_at.desc(), Feedback.id.desc()).limit(6).all()),
            "latest_posts": (BlogPost.query.filter_by(status="published")
                             .order_by(BlogPost.published_at.desc(), BlogPost.id.desc()).limit(3).all()),
            # Đếm toàn bộ dịch vụ đang bật, không chỉ 6 thẻ nổi bật trên trang chủ.
            "service_groups": (db.session.query(Product.target_group)
                               .filter(Product.is_active == 1, Product.target_group.isnot(None))
                               .distinct().count()),
        }
    data = cached("home:index", 60, _load)
    return render_template("main/index.html", **data)


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
    pagination = cached(f"home:services:{q}:{page}", 60,
                        lambda: query.paginate(page=page, per_page=current_app.config["PER_PAGE"], error_out=False))
    return render_template("main/services.html", pagination=pagination, services=pagination.items, q=q)


@main_bp.route("/dich-vu/<int:pid>")
def service_detail(pid):
    product = Product.query.get_or_404(pid)
    related = cached(f"home:related:{pid}", 60, lambda: (Product.query.filter(Product.id != pid, Product.is_active == 1)
                                                           .order_by(Product.id.desc()).limit(3).all()))
    cols = (CaseStudy.id, CaseStudy.title, CaseStudy.duration_text, CaseStudy.is_demo)
    cases = cached(f"cases:svc:{pid}", 60, lambda: (db.session.query(*cols)
                                                   .filter(CaseStudy.product_id == pid, CaseStudy.is_active.is_(True))
                                                   .order_by(CaseStudy.sort_order, CaseStudy.id.desc()).limit(3).all()))
    return render_template("main/service_detail.html", p=product, related=related, cases=cases)


# ---------- Thư viện ca điều trị trước/sau ----------
@main_bp.route("/ket-qua")
def cases():
    """Kết quả thực tế: ảnh trước/sau theo dịch vụ, có thanh trượt so sánh."""
    pid = request.args.get("dv", type=int)

    def _load():
        # Không kéo cột ảnh (BLOB) khi liệt kê -> nhẹ hơn nhiều; ảnh tải riêng qua /ket-qua/anh
        cols = (CaseStudy.id, CaseStudy.title, CaseStudy.product_id, CaseStudy.duration_text,
                CaseStudy.description, CaseStudy.is_demo, CaseStudy.sort_order, CaseStudy.updated_at)
        q = db.session.query(*cols).filter(CaseStudy.is_active.is_(True))
        if pid:
            q = q.filter(CaseStudy.product_id == pid)
        items = q.order_by(CaseStudy.sort_order, CaseStudy.id.desc()).all()
        used_ids = {r[0] for r in db.session.query(CaseStudy.product_id)
                    .filter(CaseStudy.is_active.is_(True), CaseStudy.product_id.isnot(None)).distinct().all()}
        filters = Product.query.filter(Product.id.in_(used_ids)).order_by(Product.name).all() if used_ids else []
        products = {p.id: p for p in Product.query.all()}
        return {"items": items, "filters": filters, "products": products}
    data = cached(f"cases:list:{pid or 0}", 60, _load)
    return render_template("main/cases.html", pid=pid, **data)


@main_bp.route("/ket-qua/anh/<int:cid>/<kind>")
def case_image(cid, kind):
    """Trả ảnh trước/sau từ DB (JPEG đã nén). Cache 30 ngày vì ảnh đổi thì updated_at đổi -> URL có ?v=."""
    if kind not in ("truoc", "sau"):
        return "", 404
    col = CaseStudy.before_image if kind == "truoc" else CaseStudy.after_image
    ver = request.args.get("v", "")
    data = cached(f"cases:img:{cid}:{kind}:{ver}", 600,
                  lambda: db.session.query(col).filter(CaseStudy.id == cid).scalar())
    if not data:
        return "", 404
    resp = Response(data, mimetype="image/jpeg")
    resp.headers["Cache-Control"] = "public, max-age=2592000"
    return resp


@main_bp.route("/bac-si")
def doctors():
    docs = cached("home:doctors", 300, lambda: Staff.query.filter_by(role="doctor").all())
    return render_template("main/doctors.html", doctors=docs)


@main_bp.route("/kien-thuc")
def knowledge():
    posts = cached("home:posts", 120, lambda: (BlogPost.query.filter_by(status="published")
                                              .order_by(BlogPost.published_at.desc(), BlogPost.id.desc()).all()))
    return render_template("main/knowledge.html", posts=posts)


@main_bp.route("/kien-thuc/<slug>")
def knowledge_detail(slug):
    post = BlogPost.query.filter_by(slug=slug, status="published").first_or_404()
    return render_template("main/knowledge_detail.html", post=post)


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
        notify_feedback(fb)
        flash("Cảm ơn phản hồi của bạn! NALI sẽ xem xét và cải thiện. 💙", "success")
        return redirect(url_for("main.contact"))
    return render_template("main/contact.html", form=form)
