"""
tools.py — Các "công cụ" (tools) nghiệp vụ mà AI Agent được phép gọi.

Đây là phần "hành động thật" của trợ lý: không chỉ nói mà còn tra cứu và ghi
dữ liệu. Cả Agent online (Gemini function-calling) lẫn fallback offline đều dùng
chung các hàm này, nên logic đặt lịch chỉ viết một nơi duy nhất.

Ba công cụ chính:
  * tim_dich_vu(tu_khoa)            -> gợi ý dịch vụ phù hợp + giá.
  * kiem_tra_lich_trong(ngay)      -> liệt kê khung giờ còn trống trong ngày.
  * dat_lich_hen(...)              -> ghi lịch hẹn vào DB, trả mã xác nhận.
"""
from __future__ import annotations

import re
import unicodedata
from datetime import date, datetime, timedelta

from config import settings
from database import DatabaseUnavailable, fetch_booked_times, insert_appointment
from knowledge import _format_price, services_catalog


# ---------------------------------------------------------------------------
# Tiện ích xử lý ngày/giờ tiếng Việt
# ---------------------------------------------------------------------------
def _strip_accents(text: str) -> str:
    """Bỏ dấu tiếng Việt để so khớp linh hoạt (vd 'đặt lịch' ~ 'dat lich').

    Lưu ý: chữ 'đ/Đ' KHÔNG bị NFD tách dấu, phải thay tay thành 'd/D',
    nếu không 'đặt' -> 'đat' và không khớp từ khoá 'dat'.
    """
    text = text.replace("đ", "d").replace("Đ", "D")
    nfkd = unicodedata.normalize("NFD", text)
    return "".join(c for c in nfkd if unicodedata.category(c) != "Mn").lower()


def parse_date(text: str, today: date | None = None) -> str | None:
    """Chuyển mô tả ngày (tiếng Việt hoặc số) thành 'YYYY-MM-DD'.

    Hỗ trợ: 'hôm nay', 'ngày mai', 'ngày mốt', 'DD/MM', 'DD-MM',
    'DD/MM/YYYY', và sẵn định dạng ISO 'YYYY-MM-DD'.
    """
    if not text:
        return None
    today = today or date.today()
    raw = text.strip()
    t = _strip_accents(raw)

    if "hom nay" in t or t == "nay":
        return today.isoformat()
    if "ngay mai" in t or t == "mai":
        return (today + timedelta(days=1)).isoformat()
    if "ngay mot" in t or "ngay kia" in t:
        return (today + timedelta(days=2)).isoformat()

    # ISO sẵn: YYYY-MM-DD
    m = re.search(r"(\d{4})-(\d{1,2})-(\d{1,2})", raw)
    if m:
        y, mo, d = map(int, m.groups())
        try:
            return date(y, mo, d).isoformat()
        except ValueError:
            return None

    # DD/MM hoặc DD/MM/YYYY (chấp nhận cả dấu '-' hoặc '.')
    m = re.search(r"(\d{1,2})[/\-.](\d{1,2})(?:[/\-.](\d{2,4}))?", raw)
    if m:
        d, mo = int(m.group(1)), int(m.group(2))
        y = int(m.group(3)) if m.group(3) else today.year
        if y < 100:
            y += 2000
        try:
            result = date(y, mo, d)
            # Nếu ngày đã qua trong năm nay -> hiểu là năm sau
            if not m.group(3) and result < today:
                result = date(y + 1, mo, d)
            return result.isoformat()
        except ValueError:
            return None
    return None


def parse_time(text: str) -> str | None:
    """Chuyển mô tả giờ thành 'HH:MM'. Hỗ trợ '9h', '9:30', '14h30', '2 giờ chiều'."""
    if not text:
        return None
    t = _strip_accents(text)
    is_pm = "chieu" in t or "toi" in t
    is_am = "sang" in t
    minute = 0

    # Ưu tiên dạng có chỉ báo giờ rõ ràng: '9h', '9:30', '14h30', '9 gio'
    m = re.search(r"(\d{1,2})\s*(?:h|:|gio)\s*(\d{1,2})?", t)
    if m:
        hour = int(m.group(1))
        if m.group(2):
            minute = int(m.group(2))
    else:
        # Số trần (vd '3') CHỈ hiểu là giờ khi có 'sáng/chiều/tối' đi kèm,
        # để không nhầm ngày '15/07' thành 15:00.
        if not (is_pm or is_am):
            return None
        m2 = re.search(r"\b(\d{1,2})\b", t)
        if not m2:
            return None
        hour = int(m2.group(1))

    if is_pm and hour < 12:
        hour += 12
    if is_am and hour == 12:
        hour = 0
    if not (0 <= hour <= 23 and 0 <= minute <= 59):
        return None
    return f"{hour:02d}:{minute:02d}"


def _all_slots() -> list[str]:
    """Sinh toàn bộ khung giờ trong ngày theo giờ mở cửa & độ dài slot."""
    slots = []
    cur = datetime(2000, 1, 1, settings.clinic_open_hour, 0)
    end = datetime(2000, 1, 1, settings.clinic_close_hour, 0)
    step = timedelta(minutes=settings.slot_minutes)
    while cur < end:
        slots.append(cur.strftime("%H:%M"))
        cur += step
    return slots


# ---------------------------------------------------------------------------
# Công cụ 1: Tìm dịch vụ
# ---------------------------------------------------------------------------
def tim_dich_vu(tu_khoa: str) -> dict:
    """Gợi ý các dịch vụ khớp với từ khoá khách hàng nêu.

    Trả về {'ket_qua': [ {id, ten, gia, gia_text, mo_ta, thoi_luong}, ... ]}.
    """
    catalog = services_catalog()
    key = _strip_accents(tu_khoa)
    key_tokens = set(key.split())

    scored: list[tuple[int, dict]] = []
    for svc in catalog:
        # So khớp theo TỪ nguyên vẹn (word-boundary) để tránh 'khon' dính 'khong',
        # 'mai' dính 'mái'... Ưu tiên trùng ở TÊN dịch vụ (x2) hơn ở mô tả.
        name_words = set(_strip_accents(svc.get("name", "")).split())
        desc_words = set(_strip_accents(svc.get("description", "")).split())
        score = sum(2 for tok in key_tokens if tok in name_words)
        score += sum(1 for tok in key_tokens if tok in desc_words and tok not in name_words)
        if key and key in _strip_accents(svc.get("name", "")):
            score += 3  # trùng cả cụm tên -> ưu tiên cao
        if score > 0:
            scored.append((score, svc))

    scored.sort(key=lambda x: x[0], reverse=True)
    chosen = [svc for _, svc in scored[:5]] or catalog[:5]

    return {
        "ket_qua": [
            {
                "id": s.get("id"),
                "ten": s.get("name", ""),
                "gia": float(s.get("price", 0) or 0),
                "gia_text": _format_price(s.get("price", 0)),
                "mo_ta": s.get("description", ""),
                "thoi_luong": s.get("duration", 30),
            }
            for s in chosen
        ]
    }


def _resolve_service(ten_dich_vu: str) -> dict | None:
    """Tìm 1 dịch vụ khớp nhất theo tên (để gắn vào lịch hẹn)."""
    if not ten_dich_vu:
        return None
    res = tim_dich_vu(ten_dich_vu)["ket_qua"]
    return res[0] if res else None


# ---------------------------------------------------------------------------
# Công cụ 2: Kiểm tra lịch trống
# ---------------------------------------------------------------------------
def kiem_tra_lich_trong(ngay: str) -> dict:
    """Liệt kê các khung giờ còn trống trong ngày `ngay`.

    `ngay` có thể là mô tả tiếng Việt ('ngày mai') hoặc 'YYYY-MM-DD'.
    """
    iso = parse_date(ngay)
    if not iso:
        return {"thanh_cong": False, "loi": "Không hiểu ngày. Vui lòng ghi dạng NGÀY/THÁNG."}

    all_slots = _all_slots()
    try:
        booked = fetch_booked_times(iso)
        db_ok = True
    except DatabaseUnavailable:
        booked, db_ok = set(), False  # DB chưa bật -> coi như còn trống hết

    free = [s for s in all_slots if s not in booked]
    return {
        "thanh_cong": True,
        "ngay": iso,
        "gio_trong": free,
        "ghi_chu_db": None if db_ok else "Chưa kết nối được DB, hiển thị toàn bộ khung giờ.",
    }


# ---------------------------------------------------------------------------
# Công cụ 3: Đặt lịch hẹn (hành động ghi dữ liệu)
# ---------------------------------------------------------------------------
_PHONE_RE = re.compile(r"^0\d{9,10}$")


def dat_lich_hen(
    ho_ten: str,
    so_dien_thoai: str,
    ngay: str,
    gio: str,
    dich_vu: str = "",
    ghi_chu: str = "",
) -> dict:
    """Ghi lịch hẹn vào DB sau khi kiểm tra hợp lệ. Trả kết quả xác nhận.

    Ràng buộc: tên & SĐT bắt buộc; SĐT dạng 0xxxxxxxxx; ngày/giờ hợp lệ
    và nằm trong giờ làm việc; khung giờ chưa có người đặt.
    """
    # 1) Validate thông tin cơ bản
    ho_ten = (ho_ten or "").strip()
    sdt = re.sub(r"[^\d]", "", so_dien_thoai or "")
    if len(ho_ten) < 2:
        return {"thanh_cong": False, "loi": "Thiếu họ tên khách hàng."}
    if not _PHONE_RE.match(sdt):
        return {"thanh_cong": False, "loi": "Số điện thoại không hợp lệ (cần dạng 0xxxxxxxxx)."}

    iso_date = parse_date(ngay)
    iso_time = parse_time(gio)
    if not iso_date:
        return {"thanh_cong": False, "loi": "Ngày hẹn không hợp lệ."}
    if not iso_time:
        return {"thanh_cong": False, "loi": "Giờ hẹn không hợp lệ."}

    # 2) Giờ phải trong khung làm việc
    hh = int(iso_time.split(":")[0])
    if not (settings.clinic_open_hour <= hh < settings.clinic_close_hour):
        return {
            "thanh_cong": False,
            "loi": f"Phòng khám chỉ nhận lịch từ {settings.clinic_open_hour}:00 "
                   f"đến {settings.clinic_close_hour}:00.",
        }

    # 3) Không đặt vào quá khứ
    try:
        when = datetime.strptime(f"{iso_date} {iso_time}", "%Y-%m-%d %H:%M")
        if when < datetime.now():
            return {"thanh_cong": False, "loi": "Thời gian hẹn đã ở quá khứ, vui lòng chọn lại."}
    except ValueError:
        return {"thanh_cong": False, "loi": "Định dạng ngày/giờ không hợp lệ."}

    # 4) Gắn dịch vụ (nếu có) để lưu product_ids + giá
    svc = _resolve_service(dich_vu)
    product_ids = str(svc["id"]) if svc and svc.get("id") else ""
    total_price = float(svc["gia"]) if svc else 0.0
    ten_dich_vu = svc["ten"] if svc else (dich_vu or "Tư vấn tổng quát")

    # 5) Kiểm tra trùng khung giờ + ghi DB
    try:
        booked = fetch_booked_times(iso_date)
        if iso_time in booked:
            info = kiem_tra_lich_trong(iso_date)
            return {
                "thanh_cong": False,
                "loi": f"Khung {iso_time} ngày {iso_date} đã có người đặt.",
                "gio_trong": info.get("gio_trong", []),
            }
        new_id = insert_appointment(
            customer_name=ho_ten,
            customer_phone=sdt,
            appointment_date=iso_date,
            appointment_time=iso_time,
            notes=ghi_chu or f"Đặt qua Trợ lý AI. Dịch vụ: {ten_dich_vu}",
            product_ids=product_ids,
            total_price=total_price,
        )
    except DatabaseUnavailable as exc:
        return {
            "thanh_cong": False,
            "loi": "Chưa kết nối được cơ sở dữ liệu (hãy bật MySQL trong XAMPP).",
            "chi_tiet": str(exc),
        }

    return {
        "thanh_cong": True,
        "ma_lich_hen": new_id,
        "ho_ten": ho_ten,
        "so_dien_thoai": sdt,
        "ngay": iso_date,
        "gio": iso_time,
        "dich_vu": ten_dich_vu,
        "gia_text": _format_price(total_price) if total_price else "sẽ tư vấn khi khám",
        "thong_bao": (
            f"Đã đặt lịch #{new_id} cho {ho_ten} vào {iso_time} ngày {iso_date} "
            f"({ten_dich_vu}). Lễ tân sẽ gọi {sdt} để xác nhận."
        ),
    }
