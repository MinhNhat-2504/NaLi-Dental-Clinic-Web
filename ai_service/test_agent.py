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


def test_record_context(r: Retriever):
    """Khách đăng nhập hỏi về hồ sơ/dặn dò/tái khám -> trả lời xác định từ ngữ cảnh web gửi."""
    print("\n[Hồ sơ khám của khách đăng nhập]")
    from fallback_agent import FallbackAgent
    ctx = ("Họ tên: Trần Thị Lan Anh\nSĐT: 0901234567\nLịch hẹn sắp tới: chưa có\n"
           "Lần khám gần nhất: 05/08/2026 (14 ngày trước)\nChẩn đoán lần đó: Sâu răng số 36\n"
           "Đã điều trị: Trám composite răng 36\nDặn dò của bác sĩ: Tránh nhai đồ cứng 24 giờ\n"
           "Bác sĩ hẹn tái khám: 19/08/2026 (còn 0 ngày)")
    a = FallbackAgent(r)
    out = a.reply("rec1", "Lần trước bác sĩ dặn tôi gì vậy?", user_context=ctx)
    check("trả đúng dặn dò từ hồ sơ", "Tránh nhai đồ cứng 24 giờ" in out and "Lan Anh" in out, out[:80])
    out = a.reply("rec1", "Tôi cần tái khám khi nào?", user_context=ctx)
    check("trả đúng ngày tái khám", "19/08/2026" in out, out[:80])
    out = a.reply("rec2", "Tôi cần tái khám khi nào?", user_context="Họ tên: Khách Mới\nSĐT: 0900000000")
    check("chưa có hồ sơ -> nói rõ, không bịa", "chưa thấy hồ sơ" in out, out[:80])
    check("câu thường không bị bắt nhầm", FallbackAgent.record_answer("Giá tẩy trắng bao nhiêu?", ctx) is None)
    check("khách vãng lai không có ngữ cảnh -> None", FallbackAgent.record_answer("hồ sơ của tôi", "") is None)


def test_streaming(r: Retriever):
    """reply_stream ghép lại phải bằng reply; local agent phải giấu JSON tool khi stream."""
    print("\n[Streaming]")
    from fallback_agent import chunk_text
    a = FallbackAgent(r)
    full = a.reply("st-a", "phòng khám mở cửa mấy giờ?")
    pieces = list(a.reply_stream("st-b", "phòng khám mở cửa mấy giờ?"))
    check("stream ghép lại == trả lời thường", "".join(pieces) == full and len(pieces) > 1, f"{len(pieces)} mẩu")
    check("chunk_text giữ nguyên nội dung", "".join(chunk_text("một hai ba bốn năm sáu bảy")) == "một hai ba bốn năm sáu bảy")

    # Giả lập model local: lượt 1 trả JSON tool (phải bị giấu), lượt 2 trả văn bản (phải stream)
    import local_llm_agent as L
    calls = {"n": 0}

    def fake_stream(messages, **kw):
        calls["n"] += 1
        text = '{"tool": "tim_dich_vu", "args": {"tu_khoa": "implant"}}' if calls["n"] == 1 else "Dạ dịch vụ Implant tại NALI có giá tham khảo ạ."
        for i in range(0, len(text), 5):
            yield text[i:i + 5]

    orig = L._chat_completion_stream
    L._chat_completion_stream = fake_stream
    try:
        agent = L.LocalLLMAgent(r)
        out = "".join(agent.reply_stream("st-c", "implant giá sao?"))
    finally:
        L._chat_completion_stream = orig
    check("JSON gọi tool không lọt ra khách", "tool" not in out and "{" not in out, out[:80])
    check("câu trả lời sau tool được stream ra", "Implant" in out, out[:80])
    check("đã chạy 2 lượt model (tool -> trả lời)", calls["n"] == 2, str(calls))


def test_gemini_routes_booking(r: Retriever):
    """Backend Gemini: ý định đặt lịch phải đi qua slot-filling, không gọi model."""
    print("\n[Gemini: đặt lịch qua máy trạng thái]")
    try:
        from gemini_agent import GeminiAgent
        import google.generativeai as genai
        genai.configure(api_key="dummy-key-for-test")
        g = GeminiAgent(r)
    except Exception as exc:  # noqa: BLE001
        check("khởi tạo GeminiAgent (không gọi mạng)", False, str(exc)[:80])
        return
    g._chat_for = lambda sid: (_ for _ in ()).throw(AssertionError("không được gọi Gemini khi đặt lịch"))
    out = g.reply("g1", "tôi muốn đặt lịch")
    check("'đặt lịch' -> hỏi họ tên, không gọi Gemini", "họ tên" in out.lower(), out[:80])
    out = g.reply("g1", "Trần Thị B")
    check("lượt tiếp theo vẫn trong luồng đặt lịch", "điện thoại" in out.lower(), out[:80])
    check("Gemini không còn tool dat_lich_hen", "dat_lich_hen" not in open("gemini_agent.py", encoding="utf-8").read().split("tools=[")[1].split("]")[0])


def test_clinic_facts():
    """Kho tri thức phòng khám dựng từ clinic_settings; không có DB thì dùng mặc định."""
    print("\n[Cấu hình phòng khám -> tri thức]")
    from clinic import get_clinic, hotline
    from knowledge import clinic_facts
    c = get_clinic()
    check("mặc định có 3 chi nhánh", len(c["branches"]) == 3)
    docs = clinic_facts()
    check("8 tài liệu cố định", len(docs) == 8, str(len(docs)))
    check("tài liệu chi nhánh chứa địa chỉ", any("Đặng Thùy Trâm" in d.content for d in docs))
    check("tài liệu hotline dùng giá trị cấu hình", any(hotline() in d.content for d in docs))


def test_judge_parsing():
    """Đọc kết quả giám khảo LLM (JSON, có thể bọc ```json``` hoặc lẫn chữ)."""
    print("\n[Eval: đọc phán quyết giám khảo]")
    from eval_agent import parse_verdict
    check("JSON thuần", parse_verdict('{"dung": true, "ly_do": "khớp giờ mở cửa"}') == (True, "khớp giờ mở cửa"))
    check("bọc ```json```", parse_verdict('```json\n{"dung": false, "ly_do": "sai giờ"}\n```')[0] is False)
    check("chuỗi 'true'", parse_verdict('{"dung": "true"}')[0] is True)
    check("không JSON -> sai, có lý do", parse_verdict("không biết")[0] is False and parse_verdict("không biết")[1])
    from eval_agent import parse_verdicts, _sample
    batch = parse_verdicts('[{"i": 1, "dung": true, "ly_do": "ok"}, {"i": 3, "dung": "true"}]', 3)
    check("chấm theo lô: đúng vị trí, thiếu mục -> sai", [b[0] for b in batch] == [True, False, True], str(batch))
    check("lô không phải JSON -> tất cả sai", all(not b[0] for b in parse_verdicts("lỗi", 2)))
    qs = [{"topic": t, "q": f"{t}{k}", "expect": []} for t in ("a", "b", "c") for k in range(4)]
    picked = _sample(qs, 6)
    check("lấy mẫu chia đều chủ đề", len(picked) == 6 and {p["topic"] for p in picked} == {"a", "b", "c"})
    check("lấy mẫu cố định giữa các lần", _sample(qs, 6) == picked)


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
    test_record_context(r)
    test_gemini_routes_booking(r)
    test_clinic_facts()
    test_judge_parsing()
    test_streaming(r)
    test_tool_parsing()
    print(f"\n===== KẾT QUẢ: {_passed} PASS / {_failed} FAIL =====")
    raise SystemExit(1 if _failed else 0)
