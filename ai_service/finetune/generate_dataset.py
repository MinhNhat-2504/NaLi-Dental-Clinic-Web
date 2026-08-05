"""
generate_dataset.py — Sinh bộ dữ liệu instruction để FINETUNE Qwen2.5-3B.

Dữ liệu được tạo TỪ CHÍNH tri thức của NALI (dịch vụ, giá, giờ mở cửa, chi nhánh)
nên model sau khi finetune sẽ "thuộc" phòng khám, đúng giọng "NALI" và biết dùng
giao thức tool-call JSON để đặt lịch — khớp y hệt cách local_llm_agent chạy.

Xuất ra: finetune/data/nali_sft.jsonl  (mỗi dòng: {"messages": [...]})
Chạy:  python finetune/generate_dataset.py
"""
from __future__ import annotations

import json
import os
import sys

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

# Cho phép import các module ở thư mục ai_service/ (cha)
_HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(_HERE))

from knowledge import CLINIC_FACTS, _format_price, services_catalog  # noqa: E402
from local_llm_agent import SYSTEM_PROMPT  # noqa: E402

OUT_DIR = os.path.join(_HERE, "data")
OUT_PATH = os.path.join(OUT_DIR, "nali_sft.jsonl")

examples: list[dict] = []


def add(messages: list[dict]) -> None:
    """Thêm 1 mẫu hội thoại (đã kèm system) vào tập dữ liệu."""
    examples.append({"messages": [{"role": "system", "content": SYSTEM_PROMPT}] + messages})


def ctx(text: str) -> str:
    """Bọc dữ liệu nội bộ + câu hỏi giống hệt lúc inference."""
    return text


def user_msg(context: str, question: str) -> dict:
    return {"role": "user",
            "content": f"[DỮ LIỆU NALI]\n{context}\n[HẾT DỮ LIỆU]\n\nKhách hỏi: {question}"}


# ---------------------------------------------------------------------------
# 1) Hỏi–đáp về thông tin phòng khám (giờ, chi nhánh, hotline, thanh toán...)
# ---------------------------------------------------------------------------
FACT_QUESTIONS = {
    "gio_lam_viec": ["Phòng khám mở cửa mấy giờ?", "NALI làm việc lúc nào vậy?",
                     "Chủ nhật có làm không ạ?", "Mấy giờ đóng cửa thế?"],
    "lien_he": ["Cho mình xin số hotline", "Liên hệ NALI kiểu gì?", "Số điện thoại phòng khám là gì?"],
    "chi_nhanh": ["NALI có mấy chi nhánh?", "Địa chỉ phòng khám ở đâu?", "Chi nhánh Quận 1 ở đâu ạ?"],
    "thanh_toan": ["Có trả góp không ạ?", "Thanh toán bằng thẻ được không?", "NALI nhận chuyển khoản chứ?"],
    "doi_tuong": ["Trẻ em khám được không?", "Người già có dịch vụ riêng không?"],
    "dat_lich": ["Đặt lịch cần gì?", "Quy trình đặt lịch thế nào?"],
}

_facts_by_topic = {d.meta.get("topic"): d for d in CLINIC_FACTS}
for topic, questions in FACT_QUESTIONS.items():
    doc = _facts_by_topic.get(topic)
    if not doc:
        continue
    for q in questions:
        add([
            user_msg(f"- {doc.title}: {doc.content}", q),
            {"role": "assistant",
             "content": f"Dạ {doc.content} Anh/chị cần NALI hỗ trợ gì thêm không ạ?"},
        ])


# ---------------------------------------------------------------------------
# 2) Hỏi–đáp về từng dịch vụ (mô tả, giá, thời gian)
# ---------------------------------------------------------------------------
catalog = services_catalog()
PRICE_Q = ["{ten} giá bao nhiêu?", "Chi phí {ten} thế nào ạ?", "Làm {ten} hết bao nhiêu tiền?",
           "Bảng giá {ten}?", "{ten} khoảng bao nhiêu vậy?"]
INFO_Q = ["{ten} là gì vậy?", "Tư vấn giúp em về {ten}", "{ten} có đau không ạ?", "Cho hỏi về dịch vụ {ten}"]

for svc in catalog:
    name = svc["name"]
    price_txt = _format_price(svc.get("price", 0))
    dur = svc.get("duration", 30)
    desc = svc.get("description", "")
    context = f"- Dịch vụ: {name}. {desc} Giá tham khảo: {price_txt}. Thời gian khoảng {dur} phút."
    # Câu hỏi về giá
    for tmpl in PRICE_Q[:3]:
        add([
            user_msg(context, tmpl.format(ten=name)),
            {"role": "assistant",
             "content": f"Dạ dịch vụ **{name}** ở NALI có giá tham khảo **{price_txt}** "
                        f"(khoảng {dur} phút) ạ. Anh/chị muốn NALI đặt lịch luôn không ạ?"},
        ])
    # Câu hỏi về thông tin
    for tmpl in INFO_Q[:2]:
        add([
            user_msg(context, tmpl.format(ten=name)),
            {"role": "assistant",
             "content": f"Dạ {desc} Chi phí tham khảo **{price_txt}**, thời gian khoảng {dur} phút ạ. "
                        f"Anh/chị cần tư vấn thêm không ạ?"},
        ])


# ---------------------------------------------------------------------------
# 3) Triệu chứng -> gợi ý dịch vụ phù hợp
# ---------------------------------------------------------------------------
SYMPTOMS = [
    ("Răng khôn của em mọc lệch đau quá", "Nhổ răng khôn"),
    ("Răng em bị sâu lỗ to", "Trám răng thẩm mỹ"),
    ("Em bị chảy máu chân răng, hôi miệng", "Lấy cao răng"),
    ("Răng em ố vàng muốn trắng hơn", "Tẩy trắng răng Laser"),
    ("Em mất một cái răng muốn trồng lại", "Cấy ghép Implant"),
    ("Răng em hô, muốn niềng cho đều", "Niềng răng Invisalign"),
    ("Răng đau nhức lên tận đầu", "Điều trị tủy răng"),
]
_by_name = {s["name"]: s for s in catalog}
for symptom, svc_name in SYMPTOMS:
    svc = _by_name.get(svc_name)
    if not svc:
        continue
    price_txt = _format_price(svc.get("price", 0))
    context = f"- Dịch vụ: {svc_name}. {svc.get('description','')} Giá tham khảo: {price_txt}."
    add([
        user_msg(context, symptom),
        {"role": "assistant",
         "content": f"Dạ với tình trạng này NALI khuyên anh/chị nên dùng dịch vụ **{svc_name}** "
                    f"(tham khảo {price_txt}). Tuy nhiên NALI không thay thế bác sĩ — anh/chị nên đến khám "
                    f"để được chẩn đoán chính xác ạ. Anh/chị muốn đặt lịch không ạ?"},
    ])


# ---------------------------------------------------------------------------
# 4) Dùng CÔNG CỤ tra dịch vụ (dạy model phát ra JSON tool-call)
# ---------------------------------------------------------------------------
TOOL_LOOKUP = [("Implant", "Cấy ghép Implant"), ("niềng răng", "Niềng răng Invisalign"),
               ("tẩy trắng", "Tẩy trắng răng Laser"), ("nhổ răng khôn", "Nhổ răng khôn")]
for kw, svc_name in TOOL_LOOKUP:
    svc = _by_name.get(svc_name)
    if not svc:
        continue
    price_txt = _format_price(svc.get("price", 0))
    tool_result = {"ket_qua": [{"ten": svc_name, "gia_text": price_txt,
                                "mo_ta": svc.get("description", ""), "thoi_luong": svc.get("duration", 30)}]}
    add([
        {"role": "user", "content": f"Cho mình xem giá dịch vụ {kw} với"},
        {"role": "assistant", "content": json.dumps({"tool": "tim_dich_vu", "args": {"tu_khoa": kw}}, ensure_ascii=False)},
        {"role": "user", "content": f"[KẾT QUẢ CÔNG CỤ tim_dich_vu]\n{json.dumps(tool_result, ensure_ascii=False)}\n"
                                     f"Hãy trả lời khách bằng tiếng Việt tự nhiên dựa trên kết quả này."},
        {"role": "assistant", "content": f"Dạ NALI có dịch vụ **{svc_name}**, giá tham khảo **{price_txt}** ạ. "
                                          f"Anh/chị muốn đặt lịch không ạ?"},
    ])


# ---------------------------------------------------------------------------
# 5) Đặt lịch nhiều lượt -> phát JSON dat_lich_hen khi ĐỦ thông tin
# ---------------------------------------------------------------------------
BOOKINGS = [
    ("Trần Thị Hoa", "0912345678", "ngày mai", "9h", "tẩy trắng răng"),
    ("Lê Văn Bình", "0987654321", "20/07", "14h", "nhổ răng khôn"),
    ("Phạm Thu Hà", "0901122334", "thứ 3 tuần sau", "10h", "niềng răng"),
]
for name, phone, day, hour, svc in BOOKINGS:
    add([
        {"role": "user", "content": "Tôi muốn đặt lịch khám"},
        {"role": "assistant", "content": "Dạ anh/chị cho NALI xin **họ tên** để đặt lịch ạ?"},
        {"role": "user", "content": name},
        {"role": "assistant", "content": "Dạ anh/chị cho xin **số điện thoại** (dạng 0xxxxxxxxx) nhé ạ?"},
        {"role": "user", "content": phone},
        {"role": "assistant", "content": "Anh/chị muốn khám dịch vụ gì và vào **ngày – giờ** nào ạ?"},
        {"role": "user", "content": f"Khám {svc}, {day} lúc {hour}"},
        {"role": "assistant", "content": json.dumps(
            {"tool": "dat_lich_hen", "args": {"ho_ten": name, "so_dien_thoai": phone,
                                              "ngay": day, "gio": hour, "dich_vu": svc}},
            ensure_ascii=False)},
        {"role": "user", "content": "[KẾT QUẢ CÔNG CỤ dat_lich_hen]\n"
                                     + json.dumps({"thanh_cong": True, "ma_lich_hen": 123,
                                                   "thong_bao": "Đã đặt lịch thành công"}, ensure_ascii=False)
                                     + "\nHãy trả lời khách bằng tiếng Việt tự nhiên dựa trên kết quả này."},
        {"role": "assistant", "content": f"✅ NALI đã đặt lịch thành công cho anh/chị **{name}** rồi ạ! "
                                          f"Lễ tân sẽ gọi số {phone} để xác nhận trước buổi hẹn. Cảm ơn anh/chị 💙"},
    ])

# Thiếu thông tin -> KHÔNG gọi tool, phải HỎI
add([
    {"role": "user", "content": "Đặt lịch nhổ răng khôn cho tôi ngày mai"},
    {"role": "assistant", "content": "Dạ để đặt lịch, anh/chị cho NALI xin **họ tên** và **số điện thoại** ạ?"},
])


# ---------------------------------------------------------------------------
# 6) Giọng điệu / ngoài phạm vi
# ---------------------------------------------------------------------------
add([{"role": "user", "content": "xin chào"},
     {"role": "assistant", "content": "Dạ NALI xin chào anh/chị 👋 Em có thể tư vấn dịch vụ, báo giá và "
                                       "đặt lịch giúp anh/chị ạ. Anh/chị cần hỗ trợ gì hôm nay?"}])
add([{"role": "user", "content": "cảm ơn nhé"},
     {"role": "assistant", "content": "Dạ không có gì ạ! Chúc anh/chị nhiều sức khỏe, hẹn gặp tại NALI 💙"}])
add([user_msg("- (không có dữ liệu liên quan)", "NALI có bán bảo hiểm nhân thọ không?"),
     {"role": "assistant", "content": "Dạ NALI là phòng khám nha khoa nên không có dịch vụ này ạ. "
                                       "Anh/chị cần tư vấn về răng miệng thì NALI luôn sẵn sàng nhé!"}])


# ---------------------------------------------------------------------------
if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)
    with open(OUT_PATH, "w", encoding="utf-8") as f:
        for ex in examples:
            f.write(json.dumps(ex, ensure_ascii=False) + "\n")
    print(f"✅ Đã tạo {len(examples)} mẫu -> {OUT_PATH}")
    # Thống kê nhanh
    n_tool = sum(1 for e in examples if any(
        m["role"] == "assistant" and m["content"].lstrip().startswith('{"tool"')
        for m in e["messages"]))
    print(f"   Trong đó {n_tool} mẫu dạy model PHÁT tool-call JSON (đặt lịch / tra cứu).")
