"""appointments.slot_key: UNIQUE chặn trùng khung giờ ở tầng DB

Viết TAY, idempotent. slot_key = "YYYY-MM-DD HH:MM" khi status pending/confirmed, NULL khi huỷ/hoàn thành
(NULL không tính vào UNIQUE nên slot được giải phóng khi huỷ). Dữ liệu cũ được backfill; nếu đã có
hai lịch trùng slot thì giữ lịch có id nhỏ nhất, các lịch còn lại để NULL (vẫn giữ nguyên, chỉ không khoá).

Revision ID: c4f0a2d7e9b1
Revises: b7d4e91c2a06
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect, text

revision = "c4f0a2d7e9b1"
down_revision = "b7d4e91c2a06"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    insp = inspect(bind)
    cols = {c["name"] for c in insp.get_columns("appointments")}
    if "slot_key" not in cols:
        op.add_column("appointments", sa.Column("slot_key", sa.String(20), nullable=True))
    if bind.dialect.name == "mysql":
        bind.execute(text(
            "UPDATE appointments SET slot_key = CONCAT(appointment_date, ' ', TIME_FORMAT(appointment_time, '%H:%i')) "
            "WHERE status IN ('pending','confirmed') AND slot_key IS NULL"))
        # Bỏ khoá ở các lịch trùng nhau đã tồn tại (giữ id nhỏ nhất) để tạo được UNIQUE
        bind.execute(text(
            "UPDATE appointments a JOIN (SELECT slot_key, MIN(id) AS keep FROM appointments "
            "WHERE slot_key IS NOT NULL GROUP BY slot_key HAVING COUNT(*) > 1) d ON a.slot_key = d.slot_key "
            "SET a.slot_key = NULL WHERE a.id <> d.keep"))
    idx = {i["name"] for i in insp.get_indexes("appointments")}
    if "uq_appointments_slot_key" not in idx:
        op.create_index("uq_appointments_slot_key", "appointments", ["slot_key"], unique=True)


def downgrade():
    op.drop_index("uq_appointments_slot_key", table_name="appointments")
    op.drop_column("appointments", "slot_key")
