"""add chat_logs (nhật ký chatbot để đo chất lượng AI)

Viết TAY, chỉ tạo bảng chat_logs. Không autogenerate vì DB dùng chung với bản
PHP có vài cột/kiểu lệch model (discount_code...) — autogenerate sẽ xoá nhầm.

Revision ID: d185e1f8cee6
Revises: 2c406a29caac
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect

revision = "d185e1f8cee6"
down_revision = "2c406a29caac"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    if "chat_logs" in inspect(bind).get_table_names():
        return  # đã có (vd tạo bởi init-db) -> bỏ qua, chạy lại không lỗi
    op.create_table(
        "chat_logs",
        sa.Column("id", sa.Integer(), primary_key=True),
        sa.Column("session_id", sa.String(80), index=True),
        sa.Column("user_id", sa.Integer()),
        sa.Column("question", sa.Text(), nullable=False),
        sa.Column("answer", sa.Text()),
        sa.Column("mode", sa.String(20)),
        sa.Column("latency_ms", sa.Integer()),
        sa.Column("unanswered", sa.Boolean(), server_default=sa.text("0"), index=True),
        sa.Column("created_at", sa.TIMESTAMP(), server_default=sa.text("CURRENT_TIMESTAMP"), index=True),
        mysql_charset="utf8mb4", mysql_collate="utf8mb4_unicode_ci",
    )


def downgrade():
    op.drop_table("chat_logs")
