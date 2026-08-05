"""
database.py — Lớp truy cập dữ liệu (Data Access Layer) tới MySQL `nali_dental`.

Chịu trách nhiệm:
  * Đọc danh sách dịch vụ (bảng `products`)  -> nguồn tri thức cho RAG.
  * Kiểm tra các khung giờ đã có người đặt   -> tính lịch trống.
  * Ghi lịch hẹn mới (bảng `appointments`)   -> hành động "đặt lịch" của AI.

Thiết kế phòng thủ: nếu MySQL không kết nối được (chưa bật XAMPP...),
các hàm ném DatabaseUnavailable để tầng trên chuyển sang dữ liệu tĩnh,
đảm bảo chatbot vẫn trả lời được khi demo.
"""
from __future__ import annotations

import contextlib
from typing import Iterator, Optional

import mysql.connector
from mysql.connector import Error as MySQLError

from config import settings


class DatabaseUnavailable(RuntimeError):
    """Ném ra khi không thể kết nối/thao tác với MySQL."""


@contextlib.contextmanager
def get_connection() -> Iterator["mysql.connector.MySQLConnection"]:
    """Context manager mở/đóng kết nối MySQL an toàn.

    Dùng:  with get_connection() as conn: ...
    Tự động đóng kết nối kể cả khi có lỗi.
    """
    conn = None
    try:
        conn = mysql.connector.connect(
            host=settings.db_host,
            port=settings.db_port,
            user=settings.db_user,
            password=settings.db_password,
            database=settings.db_name,
            connection_timeout=5,
            charset="utf8mb4",
        )
        yield conn
    except MySQLError as exc:
        raise DatabaseUnavailable(f"Không kết nối được MySQL: {exc}") from exc
    finally:
        if conn is not None and conn.is_connected():
            conn.close()


def db_available() -> bool:
    """Kiểm tra nhanh xem DB có sẵn sàng không (dùng cho /health)."""
    try:
        with get_connection() as conn:
            conn.ping(reconnect=False, attempts=1, delay=0)
        return True
    except (DatabaseUnavailable, MySQLError):
        return False


def fetch_products() -> list[dict]:
    """Lấy danh sách dịch vụ đang hoạt động để đưa vào kho tri thức RAG.

    Trả về list dict: {id, name, description, price, duration, target_group}.
    Tự bỏ qua các cột không tồn tại giữa các phiên bản schema khác nhau.
    """
    with get_connection() as conn:
        cur = conn.cursor(dictionary=True)
        try:
            cur.execute(
                """
                SELECT id, name, description,
                       COALESCE(price, 0)      AS price,
                       COALESCE(duration, 30)  AS duration,
                       COALESCE(target_group, '') AS target_group
                FROM products
                WHERE COALESCE(is_active, 1) = 1
                ORDER BY id
                """
            )
            return list(cur.fetchall())
        except MySQLError as exc:
            raise DatabaseUnavailable(f"Lỗi đọc bảng products: {exc}") from exc
        finally:
            cur.close()


def fetch_booked_times(date_str: str) -> set[str]:
    """Trả về tập các khung giờ ('HH:MM') đã có lịch trong ngày `date_str`.

    date_str định dạng 'YYYY-MM-DD'. Bỏ qua lịch đã huỷ (status='cancelled').
    """
    with get_connection() as conn:
        cur = conn.cursor()
        try:
            cur.execute(
                """
                SELECT TIME_FORMAT(appointment_time, '%H:%i')
                FROM appointments
                WHERE appointment_date = %s
                  AND COALESCE(status, 'pending') <> 'cancelled'
                """,
                (date_str,),
            )
            return {row[0] for row in cur.fetchall() if row[0]}
        except MySQLError as exc:
            raise DatabaseUnavailable(f"Lỗi đọc bảng appointments: {exc}") from exc
        finally:
            cur.close()


def insert_appointment(
    *,
    customer_name: str,
    customer_phone: str,
    appointment_date: str,
    appointment_time: str,
    customer_email: str = "",
    notes: str = "",
    product_ids: str = "",
    total_price: float = 0.0,
) -> int:
    """Ghi một lịch hẹn mới vào bảng `appointments`, trả về ID vừa tạo.

    Dùng đúng bộ cột mà admin_panel.php đọc, nên lịch AI đặt sẽ hiện ngay
    trong trang quản trị. Tham số keyword-only để tránh nhầm thứ tự.
    """
    with get_connection() as conn:
        cur = conn.cursor()
        try:
            cur.execute(
                """
                INSERT INTO appointments
                    (customer_name, customer_phone, customer_email,
                     appointment_date, appointment_time, notes,
                     product_ids, total_price, status)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 'pending')
                """,
                (
                    customer_name,
                    customer_phone,
                    customer_email,
                    appointment_date,
                    appointment_time,
                    notes,
                    product_ids,
                    total_price,
                ),
            )
            conn.commit()
            return int(cur.lastrowid)
        except MySQLError as exc:
            conn.rollback()
            raise DatabaseUnavailable(f"Lỗi ghi lịch hẹn: {exc}") from exc
        finally:
            cur.close()
