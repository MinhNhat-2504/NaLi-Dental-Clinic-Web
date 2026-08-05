"""
forms.py — Các form Flask-WTF (có validator + bảo vệ CSRF tự động).
"""
from datetime import date, time

from flask_wtf import FlaskForm
from wtforms import (BooleanField, DateField, DecimalField, IntegerField,
                     PasswordField, SelectField, StringField, SubmitField,
                     TextAreaField, TimeField, ValidationError)
from wtforms.validators import (DataRequired, Email, EqualTo, Length,
                                NumberRange, Optional, Regexp)

PHONE_RE = r"^0\d{9}$"
CLINIC_OPEN = time(8, 0)    # 08:00
CLINIC_CLOSE = time(20, 0)  # 20:00


class LoginForm(FlaskForm):
    email = StringField("Email hoặc Tên đăng nhập", validators=[DataRequired()])
    password = PasswordField("Mật khẩu", validators=[DataRequired()])
    remember = BooleanField("Ghi nhớ đăng nhập")
    submit = SubmitField("Đăng nhập")


class RegisterForm(FlaskForm):
    full_name = StringField("Họ và tên", validators=[DataRequired(), Length(2, 100)])
    email = StringField("Email", validators=[DataRequired(), Email(message="Email không hợp lệ")])
    phone = StringField("Số điện thoại",
                        validators=[DataRequired(), Regexp(PHONE_RE, message="SĐT dạng 0xxxxxxxxx")])
    password = PasswordField("Mật khẩu", validators=[DataRequired(), Length(6, 100)])
    confirm = PasswordField("Nhập lại mật khẩu",
                            validators=[DataRequired(), EqualTo("password", message="Mật khẩu không khớp")])
    submit = SubmitField("Đăng ký")


class AppointmentForm(FlaskForm):
    """Form đặt lịch (khách hàng)."""
    customer_name = StringField("Họ và tên", validators=[DataRequired(), Length(2, 100)])
    customer_phone = StringField("Số điện thoại",
                                 validators=[DataRequired(), Regexp(PHONE_RE, message="SĐT dạng 0xxxxxxxxx")])
    customer_email = StringField("Email", validators=[Optional(), Email()])
    product_id = SelectField("Dịch vụ", coerce=int, validators=[Optional()])
    appointment_date = DateField("Ngày hẹn", validators=[DataRequired()])
    appointment_time = TimeField("Giờ hẹn", validators=[DataRequired()])
    notes = TextAreaField("Ghi chú", validators=[Optional(), Length(0, 500)])
    submit = SubmitField("Xác nhận đặt lịch")

    # --- Validator tùy biến (Flask-WTF) ---
    def validate_appointment_date(self, field):
        if field.data and field.data < date.today():
            raise ValidationError("Ngày hẹn không được ở quá khứ.")

    def validate_appointment_time(self, field):
        if field.data and not (CLINIC_OPEN <= field.data < CLINIC_CLOSE):
            raise ValidationError("Phòng khám chỉ nhận lịch từ 08:00 đến 20:00.")


class ProductForm(FlaskForm):
    """Form thêm/sửa dịch vụ (admin CRUD)."""
    name = StringField("Tên dịch vụ", validators=[DataRequired(), Length(2, 150)])
    description = TextAreaField("Mô tả", validators=[Optional(), Length(0, 1000)])
    price = DecimalField("Giá (VNĐ)", places=0, validators=[Optional(), NumberRange(min=0)], default=0)
    duration = IntegerField("Thời gian (phút)", validators=[Optional(), NumberRange(min=5, max=600)], default=30)
    target_group = SelectField("Nhóm đối tượng", choices=[
        ("adults", "Người lớn"), ("children", "Trẻ em"),
        ("elderly", "Người cao tuổi"), ("chronic", "Bệnh lý nền")])
    image = StringField("Tên file ảnh (trong /static/images)", validators=[Optional(), Length(0, 255)])
    is_active = BooleanField("Đang hoạt động", default=True)
    submit = SubmitField("Lưu")


class SearchForm(FlaskForm):
    """Form tìm kiếm dịch vụ (dùng GET)."""
    class Meta:
        csrf = False
    q = StringField("Tìm kiếm", validators=[Optional()])
    submit = SubmitField("Tìm")


class AdminAppointmentForm(FlaskForm):
    """Form sửa lịch hẹn phía admin (đủ CRUD cho Appointment)."""
    appointment_date = DateField("Ngày hẹn", validators=[DataRequired()])
    appointment_time = TimeField("Giờ hẹn", validators=[DataRequired()])
    status = SelectField("Trạng thái", choices=[
        ("pending", "Chờ xác nhận"), ("confirmed", "Đã xác nhận"),
        ("completed", "Hoàn thành"), ("cancelled", "Đã huỷ")])
    admin_notes = TextAreaField("Ghi chú của admin", validators=[Optional(), Length(0, 500)])
    submit = SubmitField("Lưu thay đổi")


class FeedbackForm(FlaskForm):
    """Form góp ý / phản hồi của khách hàng."""
    name = StringField("Họ và tên", validators=[DataRequired(), Length(2, 100)])
    phone = StringField("Số điện thoại", validators=[Optional(), Regexp(PHONE_RE, message="SĐT dạng 0xxxxxxxxx")])
    email = StringField("Email", validators=[Optional(), Email()])
    rating = SelectField("Mức độ hài lòng", choices=[
        ("5", "★★★★★ Rất hài lòng"), ("4", "★★★★ Hài lòng"),
        ("3", "★★★ Bình thường"), ("2", "★★ Chưa hài lòng"), ("1", "★ Không hài lòng")], default="5")
    type = SelectField("Loại phản hồi", choices=[
        ("khen", "Khen ngợi"), ("gop-y", "Góp ý"), ("phan-anh", "Phản ánh")])
    message = TextAreaField("Nội dung", validators=[DataRequired(), Length(5, 1000)])
    submit = SubmitField("Gửi phản hồi")
