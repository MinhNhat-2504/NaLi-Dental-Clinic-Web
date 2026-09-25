"""clinic_settings: thông tin phòng khám (hotline, email, giờ, chi nhánh) do admin sửa

Viết TAY, idempotent. Không seed dữ liệu: khi bảng trống, code dùng DEFAULTS trong app/clinic.py
(giá trị giống các chuỗi từng hardcode trong template), admin lưu lần đầu mới ghi vào bảng.

Revision ID: d7e3f9a1c2b4
Revises: c4f0a2d7e9b1
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect

revision = "d7e3f9a1c2b4"
down_revision = "c4f0a2d7e9b1"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    if "clinic_settings" in inspect(bind).get_table_names():
        return
    op.create_table(
        "clinic_settings",
        sa.Column("key", sa.String(50), primary_key=True),
        sa.Column("value", sa.Text()),
        sa.Column("updated_at", sa.DateTime(), server_default=sa.text("CURRENT_TIMESTAMP")),
        mysql_charset="utf8mb4", mysql_collate="utf8mb4_unicode_ci",
    )


def downgrade():
    op.drop_table("clinic_settings")
