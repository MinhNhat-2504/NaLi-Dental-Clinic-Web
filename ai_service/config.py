"""
config.py — Cấu hình tập trung cho Trợ lý AI NALI.

Đọc thiết lập từ biến môi trường / file .env, gói gọn trong một object `settings`
bất biến (frozen dataclass) để các module khác import dùng chung.
"""
from __future__ import annotations

import os
from dataclasses import dataclass
from functools import lru_cache

from dotenv import load_dotenv

# Nạp biến từ file .env nằm cùng thư mục (nếu có)
load_dotenv()


def _int(name: str, default: int) -> int:
    """Đọc biến môi trường kiểu số nguyên, có giá trị mặc định an toàn."""
    try:
        return int(os.getenv(name, str(default)))
    except (TypeError, ValueError):
        return default


def _parse_database_url() -> dict:
    """Đọc DATABASE_URL (Render/Aiven/TiDB) dạng mysql://user:pass@host:port/db nếu có."""
    from urllib.parse import unquote, urlparse
    url = os.getenv("DATABASE_URL", "").strip()
    if not url:
        return {}
    u = urlparse(url)
    return {"host": u.hostname, "port": u.port, "user": unquote(u.username or ""),
            "password": unquote(u.password or ""), "name": (u.path or "/").lstrip("/")}


_dburl = _parse_database_url()


@dataclass(frozen=True)
class Settings:
    # Chọn "bộ não": auto | local | gemini | offline
    #   auto  -> ưu tiên LLM tự host (local), rồi Gemini, cuối cùng offline
    #   local -> dùng model mở tự host (Qwen2.5 finetune qua Ollama/llama.cpp)
    llm_backend: str = os.getenv("LLM_BACKEND", "auto").strip().lower()

    # LLM tự host (chuẩn OpenAI-compatible: Ollama, llama.cpp server, vLLM...)
    local_llm_url: str = os.getenv("LOCAL_LLM_URL", "http://localhost:11434/v1").strip()
    local_llm_model: str = os.getenv("LOCAL_LLM_MODEL", "nali-dental").strip()
    local_llm_key: str = os.getenv("LOCAL_LLM_KEY", "ollama").strip()

    # Gemini
    gemini_api_key: str = os.getenv("GEMINI_API_KEY", "").strip()
    gemini_model: str = os.getenv("GEMINI_MODEL", "gemini-3.6-flash").strip()

    # Database (mặc định trùng với config.php của web PHP)
    db_host: str = _dburl.get("host") or os.getenv("DB_HOST", "localhost")
    db_port: int = _dburl.get("port") or _int("DB_PORT", 3306)
    db_user: str = _dburl.get("user") or os.getenv("DB_USER", "root")
    db_password: str = _dburl.get("password") or os.getenv("DB_PASSWORD", "")
    db_name: str = _dburl.get("name") or os.getenv("DB_NAME", "nali_dental")

    # Phòng khám
    clinic_open_hour: int = _int("CLINIC_OPEN_HOUR", 8)
    clinic_close_hour: int = _int("CLINIC_CLOSE_HOUR", 20)
    slot_minutes: int = _int("SLOT_MINUTES", 60)

    # Server
    host: str = os.getenv("HOST", "127.0.0.1")
    port: int = _int("PORT", 8000)

    @property
    def has_gemini(self) -> bool:
        """True nếu có API key => chạy chế độ AI online (Gemini)."""
        return bool(self.gemini_api_key)

    @property
    def has_local_llm(self) -> bool:
        """True nếu có cấu hình endpoint LLM tự host."""
        return bool(self.local_llm_url)


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    """Trả về một instance Settings duy nhất (singleton) cho toàn ứng dụng."""
    return Settings()


settings = get_settings()
