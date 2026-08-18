"""
fallback_agent.py — Agent DỰ PHÒNG chạy hoàn toàn offline (không cần Gemini).

Kích hoạt khi không có GEMINI_API_KEY hoặc gọi Gemini thất bại. Bảo đảm buổi
demo không bao giờ "chết": vẫn tư vấn được (RAG bằng TF-IDF) và vẫn đặt được
lịch (máy trạng thái slot-filling thu thập thông tin theo lượt).

Tuy đơn giản hơn Gemini, nó minh hoạ rõ kỹ thuật xử lý hội thoại bằng luật:
nhận diện ý định, trích xuất thực thể (SĐT, ngày, giờ) và điền dần các "slot".
"""
from __future__ import annotations

import re
from dataclasses import dataclass, field

from knowledge import _format_price
from retriever import Retriever
from tools import (
    _strip_accents,
    dat_lich_hen,
    kiem_tra_lich_trong,
    parse_date,
    parse_time,
    tim_dich_vu,
)

_BOOK_KEYWORDS = ("dat lich", "dat hen", "book", "dang ky kham", "hen kham", "muon kham")
_PHONE_RE = re.compile(r"0\d{9,10}")


@dataclass
class BookingState:
    """Trạng thái thu thập thông tin đặt lịch cho một phiên."""
    active: bool = False
    ho_ten: str = ""
    so_dien_thoai: str = ""
    dich_vu: str = ""
    ngay: str = ""   # đã chuẩn hoá 'YYYY-MM-DD'
    gio: str = ""    # đã chuẩn hoá 'HH:MM'

    def missing(self) -> str | None:
        """Trả về tên slot còn thiếu đầu tiên, hoặc None nếu đã đủ."""
        for slot in ("ho_ten", "so_dien_thoai", "ngay", "gio"):
            if not getattr(self, slot):
                return slot
        return None


class FallbackAgent:
    """Trợ lý luật-based: RAG tư vấn + slot-filling đặt lịch."""

    def __init__(self, retriever: Retriever) -> None:
        self.retriever = retriever
        self._states: dict[str, BookingState] = {}

    def _state(self, session_id: str) -> BookingState:
        return self._states.setdefault(session_id, BookingState())

    def reset(self, session_id: str) -> None:
        self._states.pop(session_id, None)

    # ---------- Trích xuất thực thể từ câu nói ----------
    def _extract(self, state: BookingState, text: str) -> None:
        """Rút các thông tin có trong câu và điền vào slot còn trống."""
        # Số điện thoại
        if not state.so_dien_thoai:
            m = _PHONE_RE.search(text.replace(" ", ""))
            if m:
                state.so_dien_thoai = m.group(0)
        # Ngày & giờ
        if not state.ngay:
            d = parse_date(text)
            if d:
                state.ngay = d
        if not state.gio:
            g = parse_time(text)
            if g:
                state.gio = g
        # Dịch vụ (khớp catalog) — khớp CHẶT để không nhầm tên người với tên dịch vụ.
        # Ví dụ tên "Trần Văn Test" không được khớp "Tư vấn" chỉ vì trùng "van".
        if not state.dich_vu:
            norm = _strip_accents(text)
            for h in tim_dich_vu(text)["ket_qua"]:
                hn = _strip_accents(h["ten"])
                toks = [t for t in hn.split() if len(t) >= 3]
                n_match = sum(1 for t in toks if t in norm)
                has_distinct = any(len(t) >= 5 and t in norm for t in toks)  # implant/laser/invisalign...
                if hn in norm or n_match >= 2 or has_distinct:
                    state.dich_vu = h["ten"]
                    break

    def _ask_for(self, slot: str) -> str:
        prompts = {
            "ho_ten": "Dạ anh/chị cho NALI xin **họ tên** để đặt lịch ạ?",
            "so_dien_thoai": "Anh/chị cho xin **số điện thoại** (dạng 0xxxxxxxxx) nhé ạ?",
            "ngay": "Anh/chị muốn đến vào **ngày** nào ạ? (vd: ngày mai, 15/07)",
            "gio": "Anh/chị muốn khung **giờ** nào ạ? (phòng khám mở 08:00–20:00)",
        }
        return prompts.get(slot, "Anh/chị bổ sung thêm thông tin giúp NALI nhé ạ.")

    # ---------- Xử lý chính ----------
    @staticmethod
    def _parse_user_context(user_context: str) -> dict:
        """Rút 'Họ tên: ...' và 'SĐT: ...' từ chuỗi ngữ cảnh do web gửi (nếu có)."""
        info = {}
        for line in (user_context or "").splitlines():
            low = line.lower()
            if low.startswith("họ tên:") or low.startswith("ho ten:"):
                info["ho_ten"] = line.split(":", 1)[1].strip()
            elif "sđt" in low or "sdt" in low or "điện thoại" in low:
                m = _PHONE_RE.search(line.replace(" ", ""))
                if m:
                    info["so_dien_thoai"] = m.group(0)
        return info

    def reply(self, session_id: str, message: str, user_context: str = "") -> str:
        state = self._state(session_id)
        norm = _strip_accents(message)

        # 0) Khách đã đăng nhập: điền sẵn tên/SĐT để không phải hỏi lại
        known = self._parse_user_context(user_context)
        if known.get("ho_ten") and not state.ho_ten:
            state.ho_ten = known["ho_ten"]
        if known.get("so_dien_thoai") and not state.so_dien_thoai:
            state.so_dien_thoai = known["so_dien_thoai"]

        # 1) Nếu đang/không trong luồng đặt lịch: quyết định ý định
        wants_booking = any(k in norm for k in _BOOK_KEYWORDS)
        if wants_booking and not state.active:
            state.active = True

        if state.active:
            return self._handle_booking(session_id, state, message)

        # 2) Câu hỏi thông tin -> trả lời bằng RAG
        if "gio thi thi" in norm:  # tránh trùng nhầm, no-op an toàn
            pass
        docs = self.retriever.search(message, k=3)
        if not docs:
            return ("Dạ NALI chưa có thông tin này. Anh/chị vui lòng gọi hotline "
                    "0945 457 512 để được hỗ trợ ạ.")
        lines = [f"• {d.title}: {d.content}" for d in docs]
        tip = "\n\n👉 Anh/chị muốn *đặt lịch* thì nhắn \"đặt lịch\" giúp NALI nhé ạ."
        greet = f"Dạ chào {known['ho_ten']} ạ! " if known.get("ho_ten") else "Dạ "
        return greet + "NALI xin thông tin ạ:\n" + "\n".join(lines) + tip

    def _handle_booking(self, session_id: str, state: BookingState, message: str) -> str:
        self._extract(state, message)
        missing = state.missing()

        # Chưa có tên và câu này không phải SĐT/ngày/giờ/từ-khoá-đặt-lịch -> coi như tên.
        # (Tránh bắt nhầm chính câu "tôi muốn đặt lịch" làm họ tên.)
        is_trigger = any(k in _strip_accents(message) for k in _BOOK_KEYWORDS)
        if missing == "ho_ten" and not is_trigger and not any(
            [_PHONE_RE.search(message), parse_date(message), parse_time(message)]
        ):
            candidate = message.strip()
            if 2 <= len(candidate) <= 40:
                state.ho_ten = candidate
                missing = state.missing()

        if missing:
            return self._ask_for(missing)

        # Đã đủ slot -> thực hiện đặt lịch
        result = dat_lich_hen(
            ho_ten=state.ho_ten,
            so_dien_thoai=state.so_dien_thoai,
            ngay=state.ngay,
            gio=state.gio,
            dich_vu=state.dich_vu,
        )
        if result.get("thanh_cong"):
            self.reset(session_id)  # xong -> xoá trạng thái
            gia = result.get("gia_text", "")
            return (
                f"✅ NALI đã đặt lịch thành công ạ!\n"
                f"• Mã lịch hẹn: #{result['ma_lich_hen']}\n"
                f"• Khách: {result['ho_ten']} — {result['so_dien_thoai']}\n"
                f"• Thời gian: {result['gio']} ngày {result['ngay']}\n"
                f"• Dịch vụ: {result['dich_vu']} ({gia})\n"
                f"Lễ tân sẽ gọi xác nhận trước buổi hẹn ạ. Cảm ơn anh/chị! 💙"
            )

        # Thất bại: nếu do trùng giờ, gợi ý giờ trống rồi xoá slot giờ
        if result.get("gio_trong"):
            state.gio = ""
            free = ", ".join(result["gio_trong"][:8]) or "khung khác"
            return f"⚠️ {result['loi']}\nCác khung còn trống: {free}.\nAnh/chị chọn giờ khác giúp NALI nhé ạ?"

        # Lỗi validate -> xoá slot liên quan để hỏi lại
        loi = result.get("loi", "Thông tin chưa hợp lệ.")
        if "điện thoại" in loi:
            state.so_dien_thoai = ""
        elif "Ngày" in loi or "ngày" in loi:
            state.ngay = ""
        elif "Giờ" in loi or "giờ" in loi:
            state.gio = ""
        return f"⚠️ {loi}\nAnh/chị nhập lại giúp NALI nhé ạ."
