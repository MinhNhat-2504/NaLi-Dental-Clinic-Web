"""thư viện ca điều trị trước/sau (case_studies, ảnh lưu trong DB)

Viết TAY, idempotent. Không autogenerate vì DB dùng chung với bản PHP.

Revision ID: a9c1e7d3f5b2
Revises: f3b8d52ac7e1
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy import inspect
from sqlalchemy.dialects import mysql

revision = "a9c1e7d3f5b2"
down_revision = "f3b8d52ac7e1"
branch_labels = None
depends_on = None


def upgrade():
    bind = op.get_bind()
    if "case_studies" in inspect(bind).get_table_names():
        return
    blob = sa.LargeBinary().with_variant(mysql.MEDIUMBLOB(), "mysql")
    op.create_table(
        "case_studies",
        sa.Column("id", sa.Integer(), primary_key=True),
        sa.Column("title", sa.String(150), nullable=False),
        sa.Column("product_id", sa.Integer(), index=True),
        sa.Column("duration_text", sa.String(60)),
        sa.Column("description", sa.Text()),
        sa.Column("before_image", blob),
        sa.Column("after_image", blob),
        sa.Column("is_demo", sa.Boolean(), server_default=sa.text("0")),
        sa.Column("is_active", sa.Boolean(), server_default=sa.text("1"), index=True),
        sa.Column("sort_order", sa.Integer(), server_default=sa.text("0")),
        sa.Column("created_at", sa.DateTime(), server_default=sa.text("CURRENT_TIMESTAMP")),
        sa.Column("updated_at", sa.DateTime(), server_default=sa.text("CURRENT_TIMESTAMP")),
        mysql_charset="utf8mb4", mysql_collate="utf8mb4_unicode_ci",
    )


def downgrade():
    op.drop_table("case_studies")
