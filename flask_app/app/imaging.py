"""imaging.py — Nén/chuẩn hoá ảnh upload (ca điều trị) trước khi lưu vào DB.
Ảnh điện thoại 3-5MB -> JPEG tối đa 1200px, chất lượng 82 (~100-200KB). Bỏ EXIF (riêng tư)."""
from __future__ import annotations

import io

from PIL import Image, ImageOps

MAX_SIDE = 1200
ALLOWED = {"image/jpeg", "image/png", "image/webp"}


def compress_image(data: bytes, max_side: int = MAX_SIDE, quality: int = 82) -> bytes:
    """Trả về bytes JPEG đã thu nhỏ; ném ValueError nếu không phải ảnh hợp lệ."""
    try:
        im = Image.open(io.BytesIO(data))
        im = ImageOps.exif_transpose(im)      # xoay đúng chiều theo EXIF rồi bỏ EXIF
    except Exception as exc:  # noqa: BLE001
        raise ValueError("File không phải ảnh hợp lệ") from exc
    if im.mode not in ("RGB", "L"):
        bg = Image.new("RGB", im.size, (255, 255, 255))
        if im.mode in ("RGBA", "LA", "P"):
            im = im.convert("RGBA")
            bg.paste(im, mask=im.split()[-1])
            im = bg
        else:
            im = im.convert("RGB")
    im.thumbnail((max_side, max_side))
    out = io.BytesIO()
    im.save(out, format="JPEG", quality=quality, optimize=True, progressive=True)
    return out.getvalue()
