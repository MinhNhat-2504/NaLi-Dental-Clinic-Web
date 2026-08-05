"""
test_agent.py — Kiểm thử phần lõi CHẠY OFFLINE (không cần server, API hay DB).

Chạy:  python test_agent.py
Kiểm tra: xử lý ngày/giờ tiếng Việt, tìm dịch vụ, retriever RAG (TF-IDF),
và luồng hội thoại của agent dự phòng. In PASS/FAIL cho từng mục.
"""
from __future__ import annotations

import sys

for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

from tools import parse_date, parse_time, tim_dich_vu
from retriever import Retriever
from fallback_agent import FallbackAgent
from local_llm_agent import extract_tool_call

_passed = 0
_failed = 0


def check(name: str, cond: bool, extra: str = "") -> None:
    global _passed, _failed
    if cond:
        _passed += 1
        print(f"  ✅ {name}")
    else:
        _failed += 1
        print(f"  ❌ {name}  {extra}")


def test_parse():
    print("\n[1] Xử lý ngày/giờ tiếng Việt")
    check("'ngày mai' ra được ngày", parse_date("ngày mai") is not None)
    check("'15/08' đúng định dạng", (parse_date("15/08") or "").endswith("-08-15"))
    check("'9h30' -> 09:30", parse_time("9h30") == "09:30")
    check("'2 giờ chiều' -> 14:00", parse_time("2 giờ chiều") == "14:00")
    check("'15h' -> 15:00", parse_time("15h") == "15:00")
    check("giờ rác -> None", parse_time("xin chào") is None)
    # Chống tái phát: chuỗi NGÀY không được hiểu nhầm/không crash thành giờ
    check("ngày '15/07' KHÔNG bị hiểu thành giờ", parse_time("ngày 15/07") is None)
    check("'15/07' -> None (không crash)", parse_time("15/07") is None)


def test_services():
    print("\n[2] Tìm dịch vụ")
    res = tim_dich_vu("niềng răng")["ket_qua"]
    check("tìm 'niềng răng' có kết quả", len(res) > 0)
    check("kết quả có giá tiền", all("gia_text" in r for r in res))
    res2 = tim_dich_vu("tẩy trắng")["ket_qua"]
    check("tìm 'tẩy trắng' khớp Laser",
          any("Tẩy trắng" in r["ten"] for r in res2),
          extra=str([r["ten"] for r in res2]))


def test_rag():
    print("\n[3] RAG retriever (TF-IDF offline)")
    r = Retriever()
    check("backend là tfidf khi không có API key", r.backend == "tfidf",
          extra=f"backend={r.backend}")
    check("corpus có tài liệu", len(r.documents) > 5)
    docs = r.search("phòng khám mở cửa mấy giờ", k=2)
    check("hỏi giờ làm việc -> truy hồi đúng chủ đề",
          any("giờ" in d.title.lower() or "làm việc" in d.title.lower() for d in docs),
          extra=str([d.title for d in docs]))
    return r


def test_fallback(r: Retriever):
    print("\n[4] Agent dự phòng - hội thoại")
    agent = FallbackAgent(r)
    ans = agent.reply("s1", "phòng khám làm việc mấy giờ?")
    check("trả lời câu hỏi giờ mở cửa", "08:00" in ans or "8" in ans, extra=ans[:60])

    # Luồng đặt lịch nhiều lượt
    r1 = agent.reply("s2", "tôi muốn đặt lịch")
    check("gõ 'đặt lịch' -> vào chế độ đặt lịch (hỏi họ tên)",
          "họ tên" in r1.lower(), extra=r1[:80])
    agent.reply("s2", "Nguyễn Văn A")
    r3 = agent.reply("s2", "0912345678")   # sau tên+sđt phải hỏi ngày
    check("sau tên & SĐT -> hỏi ngày", "ngày" in r3.lower(), extra=r3[:80])
    agent.reply("s2", "ngày mai")
    final = agent.reply("s2", "9h sáng")
    # Đã đủ 4 slot -> phải TỚI bước hành động đặt lịch, KHÔNG rơi về câu trả lời chung.
    # Chấp nhận mọi kết cục của hành động: thành công (#id), trùng giờ (đã có/trống),
    # hoặc DB chưa bật (dữ liệu/mysql) — đều chứng tỏ luồng đã gọi tool đặt lịch.
    low = final.lower()
    reached_booking = any(k in low for k in
                          ["#", "thành công", "đã có", "trống", "dữ liệu", "mysql", "hợp lệ"])
    check("đủ slot -> tới bước đặt lịch (không crash, không trả lời chung chung)",
          reached_booking, extra=final[:90])

    # Dọn dữ liệu test nếu booking đã ghi vào DB thật (giữ DB sạch, test lặp lại được)
    try:
        from database import get_connection
        with get_connection() as conn:
            cur = conn.cursor()
            cur.execute("DELETE FROM appointments WHERE customer_name = %s", ("Nguyễn Văn A",))
            conn.commit()
            cur.close()
    except Exception:
        pass


def test_tool_parsing():
    print("\n[5] Phân tích tool-call của LLM tự host")
    c1 = extract_tool_call('{"tool": "tim_dich_vu", "args": {"tu_khoa": "implant"}}')
    check("JSON thuần -> nhận đúng tool", c1 and c1["tool"] == "tim_dich_vu")
    check("lấy đúng args", c1 and c1["args"].get("tu_khoa") == "implant")
    c2 = extract_tool_call('```json\n{"tool":"kiem_tra_lich_trong","args":{"ngay":"mai"}}\n```')
    check("JSON trong ```json``` vẫn nhận", c2 and c2["tool"] == "kiem_tra_lich_trong")
    check("câu trả lời thường -> không có tool", extract_tool_call("Dạ chào anh/chị ạ!") is None)
    check("tool lạ -> bỏ qua", extract_tool_call('{"tool":"xoa_database","args":{}}') is None)


if __name__ == "__main__":
    print("===== KIỂM THỬ TRỢ LÝ AI NALI (offline) =====")
    test_parse()
    test_services()
    r = test_rag()
    test_fallback(r)
    test_tool_parsing()
    print(f"\n===== KẾT QUẢ: {_passed} PASS / {_failed} FAIL =====")
    raise SystemExit(1 if _failed else 0)
