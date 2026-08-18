"""hồ sơ bệnh nhân (medical_records) + đặt cọc (appointments.deposit_*)

Viết TAY, idempotent. Không autogenerate vì DB dùng chung với bản PHP.

Revision ID: f3b8d52ac7e1
Revises: e2a7c41b9d10
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect

revision = "f3b8d52ac7e1"
down_revision = "e2a7c41b9d10"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    insp = inspect(bind)
    if "medical_records" not in insp.get_table_names():
        op.create_table(
            "medical_records",
            sa.Column("id", sa.Integer(), primary_key=True),
            sa.Column("patient_id", sa.Integer(), index=True),
            sa.Column("appointment_id", sa.Integer(), index=True),
            sa.Column("doctor_id", sa.Integer()),
            sa.Column("visit_date", sa.Date(), nullable=False),
            sa.Column("diagnosis", sa.Text()),
            sa.Column("treatment", sa.Text()),
            sa.Column("prescription", sa.Text()),
            sa.Column("next_visit_date", sa.Date()),
            sa.Column("created_at", sa.DateTime(), server_default=sa.text("CURRENT_TIMESTAMP")),
            sa.Column("updated_at", sa.DateTime(), server_default=sa.text("CURRENT_TIMESTAMP")),
            mysql_charset="utf8mb4", mysql_collate="utf8mb4_unicode_ci",
        )
    cols = {c["name"] for c in insp.get_columns("appointments")}
    if "deposit_amount" not in cols:
        op.add_column("appointments", sa.Column("deposit_amount", sa.Numeric(12, 2), nullable=True))
    if "deposit_status" not in cols:
        op.add_column("appointments", sa.Column("deposit_status", sa.String(20), nullable=True))
    if "deposit_paid_at" not in cols:
        op.add_column("appointments", sa.Column("deposit_paid_at", sa.DateTime(), nullable=True))


def downgrade():
    op.drop_column("appointments", "deposit_paid_at")
    op.drop_column("appointments", "deposit_status")
    op.drop_column("appointments", "deposit_amount")
    op.drop_table("medical_records")
