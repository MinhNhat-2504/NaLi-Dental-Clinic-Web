"""medical_records.revisit_reminder_sent_at — email nhắc tái khám trước 3 ngày

Viết tay, idempotent.

Revision ID: b7d4e91c2a06
Revises: a9c1e7d3f5b2
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect

revision = "b7d4e91c2a06"
down_revision = "a9c1e7d3f5b2"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    cols = {c["name"] for c in inspect(bind).get_columns("medical_records")}
    if "revisit_reminder_sent_at" not in cols:
        op.add_column("medical_records", sa.Column("revisit_reminder_sent_at", sa.DateTime(), nullable=True))


def downgrade():
    op.drop_column("medical_records", "revisit_reminder_sent_at")
