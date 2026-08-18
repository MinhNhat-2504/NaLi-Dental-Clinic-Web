"""add appointments.reminder_sent_at (email nhắc lịch trước 24h)

Viết TAY, chỉ thêm 1 cột NULL. Không autogenerate vì DB dùng chung với bản PHP.

Revision ID: e2a7c41b9d10
Revises: d185e1f8cee6
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect

revision = "e2a7c41b9d10"
down_revision = "d185e1f8cee6"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    cols = {c["name"] for c in inspect(bind).get_columns("appointments")}
    if "reminder_sent_at" in cols:
        return  # đã có -> chạy lại không lỗi
    op.add_column("appointments", sa.Column("reminder_sent_at", sa.DateTime(), nullable=True))


def downgrade():
    op.drop_column("appointments", "reminder_sent_at")
