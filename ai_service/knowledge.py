"""
knowledge.py — Xây dựng "kho tri thức" (corpus) cho RAG.

Mỗi tri thức là một `Document(title, content, meta)`. Corpus gồm:
  1. Thông tin cố định của phòng khám (giờ mở cửa, chi nhánh, hotline, thanh toán...).
  2. Danh sách dịch vụ đọc động từ bảng `products` trong MySQL.
     Nếu DB chưa sẵn sàng -> dùng bộ dịch vụ tĩnh dự phòng để chatbot vẫn hoạt động.

Corpus này được `retriever.py` mã hoá thành vector để tìm kiếm ngữ nghĩa.
"""
from __future__ import annotations

from dataclasses import dataclass, field

from database import DatabaseUnavailable, fetch_products


@dataclass
class Document:
    """Một mẩu tri thức để đưa vào RAG."""
    title: str
    content: str
    meta: dict = field(default_factory=dict)

    def as_text(self) -> str:
        """Chuỗi dùng để tính embedding / TF-IDF."""
        return f"{self.title}. {self.content}"


# --- Thông tin cố định của phòng khám (nguồn: web NALI) ---
CLINIC_FACTS: list[Document] = [
    Document(
        "Giờ làm việc",
        "NALI Dental mở cửa tất cả các ngày trong tuần, từ Thứ 2 đến Chủ Nhật, "
        "khung giờ 08:00 đến 20:00.",
        {"topic": "gio_lam_viec"},
    ),
    Document(
        "Hotline và Email",
        "Số hotline đặt lịch và tư vấn: 0945 457 512. Email: nalidental@gmail.com.",
        {"topic": "lien_he"},
    ),
    Document(
        "Hệ thống chi nhánh",
        "NALI có 3 chi nhánh: Bình Thạnh (69/68 Đặng Thùy Trâm); "
        "Quận 1 (123 Nguyễn Huệ); Gò Vấp (456 Quang Trung).",
        {"topic": "chi_nhanh"},
    ),
    Document(
        "Thanh toán và trả góp",
        "Phương thức thanh toán và chính sách hỗ trợ sẽ được nhân viên xác nhận khi khách đặt lịch. "
        "Không tự khẳng định ưu đãi hoặc điều kiện thanh toán khi chưa có xác nhận.",
        {"topic": "thanh_toan"},
    ),
    Document(
        "Đối tượng phục vụ",
        "NALI phục vụ mọi lứa tuổi: nha khoa trẻ em, người lớn, người cao tuổi "
        "và bệnh nhân có bệnh lý nền.",
        {"topic": "doi_tuong"},
    ),
    Document(
        "Quy trình đặt lịch",
        "Khách chỉ cần cung cấp họ tên, số điện thoại, chọn ngày và giờ mong muốn. "
        "Lễ tân sẽ gọi xác nhận trước buổi hẹn.",
        {"topic": "dat_lich"},
    ),
]

# --- Bộ dịch vụ tĩnh dự phòng (khi MySQL chưa sẵn sàng lúc demo) ---
FALLBACK_SERVICES: list[dict] = [
    {"id": 1, "name": "Tẩy trắng răng Laser", "description": "Công nghệ làm trắng răng bằng tia Laser an toàn", "price": 2500000, "duration": 60, "target_group": "adults"},
    {"id": 2, "name": "Bọc răng sứ Titan", "description": "Bọc răng sứ cao cấp, bền đẹp lâu dài", "price": 4500000, "duration": 90, "target_group": "adults"},
    {"id": 3, "name": "Niềng răng Invisalign", "description": "Niềng răng trong suốt không đau", "price": 65000000, "duration": 120, "target_group": "adults"},
    {"id": 4, "name": "Cấy ghép Implant", "description": "Trồng răng Implant công nghệ Hàn Quốc", "price": 18000000, "duration": 120, "target_group": "adults"},
    {"id": 5, "name": "Nhổ răng khôn", "description": "Nhổ răng khôn an toàn không đau", "price": 1500000, "duration": 45, "target_group": "adults"},
    {"id": 6, "name": "Điều trị tủy răng", "description": "Lấy tủy, điều trị viêm tủy chuyên sâu", "price": 2000000, "duration": 60, "target_group": "adults"},
    {"id": 7, "name": "Trám răng thẩm mỹ", "description": "Trám răng bằng composite cao cấp", "price": 300000, "duration": 30, "target_group": "adults"},
    {"id": 8, "name": "Lấy cao răng", "description": "Làm sạch cao răng, vệ sinh răng miệng", "price": 200000, "duration": 30, "target_group": "adults"},
]


def _format_price(price: float) -> str:
    """Định dạng giá tiền VNĐ dễ đọc, ví dụ 2500000 -> '2.500.000đ'."""
    try:
        return f"{int(round(float(price))):,}".replace(",", ".") + "đ"
    except (TypeError, ValueError):
        return "liên hệ"


def _service_to_document(svc: dict) -> Document:
    """Chuyển một bản ghi dịch vụ thành Document cho RAG."""
    price_txt = _format_price(svc.get("price", 0))
    duration = svc.get("duration") or 30
    desc = (svc.get("description") or "").strip()
    content = (
        f"{desc} Giá tham khảo: {price_txt}. "
        f"Thời gian thực hiện khoảng {duration} phút."
    )
    return Document(
        title=f"Dịch vụ: {svc.get('name', '')}",
        content=content,
        meta={
            "topic": "dich_vu",
            "service_id": svc.get("id"),
            "name": svc.get("name", ""),
            "price": float(svc.get("price", 0) or 0),
            "duration": duration,
        },
    )


def build_corpus() -> tuple[list[Document], bool]:
    """Dựng toàn bộ corpus RAG.

    Trả về (danh_sách_document, dùng_dữ_liệu_thật).
    `dùng_dữ_liệu_thật` = True nếu lấy được dịch vụ từ MySQL,
    False nếu phải dùng bộ dịch vụ tĩnh dự phòng.
    """
    docs: list[Document] = list(CLINIC_FACTS)
    used_live_db = False
    try:
        services = fetch_products()
        if services:
            used_live_db = True
        else:
            services = FALLBACK_SERVICES
    except DatabaseUnavailable:
        services = FALLBACK_SERVICES

    docs.extend(_service_to_document(s) for s in services)
    return docs, used_live_db


def services_catalog() -> list[dict]:
    """Danh sách dịch vụ 'thô' (dict) để AI tra cứu tên/giá khi đặt lịch."""
    try:
        services = fetch_products()
        if services:
            return services
    except DatabaseUnavailable:
        pass
    return FALLBACK_SERVICES
