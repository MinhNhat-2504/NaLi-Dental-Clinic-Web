"""UNIQUE(slot_key): hai lịch còn hiệu lực không được trùng ngày giờ; huỷ thì giải phóng slot."""
from datetime import date, time, timedelta

import pytest
from sqlalchemy.exc import IntegrityError

from app.extensions import db
from app.models import Appointment


def _appt(**kw):
    base = dict(customer_name="A", customer_phone="0900000001", customer_email="",
                appointment_date=date.today() + timedelta(days=2), appointment_time=time(9, 0), status="pending")
    base.update(kw)
    return Appointment(**base)


def test_slot_key_computed_and_unique(app):
    with app.app_context():
        a = _appt()
        db.session.add(a); db.session.commit()
        assert a.slot_key == f"{a.appointment_date.isoformat()} 09:00"
        db.session.add(_appt(customer_name="B"))
        with pytest.raises(IntegrityError):
            db.session.commit()
        db.session.rollback()
        # huỷ lịch A -> slot_key NULL -> B đặt được
        a.status = "cancelled"; db.session.commit()
        assert a.slot_key is None
        b = _appt(customer_name="B"); db.session.add(b); db.session.commit()
        assert b.slot_key == f"{b.appointment_date.isoformat()} 09:00"
        # đổi giờ của B -> slot_key đổi theo
        b.appointment_time = time(10, 30); db.session.commit()
        assert b.slot_key.endswith(" 10:30")


def test_booking_form_reports_taken_slot(app, client):
    """Người thứ hai đặt đúng slot qua form nhận thông báo, không 500."""
    d = date.today() + timedelta(days=3)
    with app.app_context():
        db.session.add(_appt(appointment_date=d, appointment_time=time(11, 0))); db.session.commit()
    client.post("/dang-nhap", data={"email": "khach@test.com", "password": "matkhau123"})
    r = client.post("/dat-lich", data={"customer_name": "Khách Test", "customer_phone": "0900000000",
                                       "customer_email": "khach@test.com", "product_id": 1,
                                       "appointment_date": d.isoformat(), "appointment_time": "11:00",
                                       "notes": "", "deposit": "none"})
    assert r.status_code == 200 and "có người đặt" in r.get_data(as_text=True)
    with app.app_context():
        assert Appointment.query.filter_by(appointment_date=d, appointment_time=time(11, 0)).count() == 1
