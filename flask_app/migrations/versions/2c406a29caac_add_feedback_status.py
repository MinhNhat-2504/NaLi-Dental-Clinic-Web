"""add feedback status

Revision ID: 2c406a29caac
Revises: 
Create Date: 2026-08-15 02:39:57.760917

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = '2c406a29caac'
down_revision = None
branch_labels = None
depends_on = None


def upgrade():
    # Bản cũ đã có bảng feedback nhưng chưa có trạng thái duyệt.
    # Kiểm tra trước giúp an toàn khi một máy đã được vá thủ công.
    columns = {column["name"] for column in sa.inspect(op.get_bind()).get_columns("feedback")}
    if "status" not in columns:
        with op.batch_alter_table("feedback") as batch_op:
            batch_op.add_column(sa.Column(
                "status",
                sa.Enum("pending", "approved", "rejected", name="feedback_status"),
                nullable=False,
                server_default="pending",
            ))


def downgrade():
    columns = {column["name"] for column in sa.inspect(op.get_bind()).get_columns("feedback")}
    if "status" in columns:
        with op.batch_alter_table("feedback") as batch_op:
            batch_op.drop_column("status")
