"""Nội dung thông tin khởi tạo cho website NALI.

Không tạo đánh giá khách hàng: phản hồi công khai phải có sự đồng ý và được quản trị viên duyệt.
"""
from datetime import datetime

from .extensions import db
from .models import BlogPost, FAQ


FAQS = [
    (
        "Tôi gửi yêu cầu đặt lịch trên website thì có được xác nhận ngay không?",
        "Yêu cầu trực tuyến giúp phòng khám ghi nhận thông tin ban đầu. Lễ tân sẽ kiểm tra khung giờ và liên hệ lại để xác nhận trước khi lịch hẹn được chốt.",
    ),
    (
        "Mức giá hiển thị trên website có phải giá cuối cùng không?",
        "Mức giá trên từng dịch vụ là thông tin tham khảo. Chi phí phù hợp chỉ được xác nhận sau khi bác sĩ thăm khám và tư vấn phương án điều trị.",
    ),
    (
        "Tôi nên chuẩn bị gì trước buổi khám đầu tiên?",
        "Bạn có thể ghi sẵn vấn đề đang gặp, các thuốc đang sử dụng và câu hỏi muốn trao đổi. Nếu có phim hoặc hồ sơ điều trị trước đây, hãy mang theo để bác sĩ tham khảo.",
    ),
    (
        "Khi nào tôi nên liên hệ phòng khám sớm?",
        "Nên liên hệ sớm khi đau tăng nhanh, sưng, chảy máu kéo dài, chấn thương răng hoặc gặp khó khăn khi ăn uống. Trường hợp khẩn cấp cần tìm cơ sở y tế phù hợp ngay.",
    ),
    (
        "Trợ lý AI có thay thế bác sĩ không?",
        "Không. Trợ lý AI chỉ hỗ trợ tra cứu thông tin và hướng dẫn gửi yêu cầu hẹn. Chẩn đoán, chỉ định và chi phí điều trị cần được bác sĩ xác nhận trực tiếp.",
    ),
    (
        "Thông tin cá nhân khi đặt lịch được dùng như thế nào?",
        "Thông tin liên hệ được dùng để tiếp nhận, xác nhận và hỗ trợ lịch hẹn. Bạn có thể xem chính sách dữ liệu cá nhân trên website hoặc liên hệ phòng khám khi cần cập nhật thông tin.",
    ),
]


POSTS = [
    {
        "slug": "chuan-bi-truoc-buoi-kham-rang-dau-tien",
        "title": "Chuẩn bị trước buổi khám răng đầu tiên",
        "category": "Hướng dẫn",
        "cover_image": "clinic-space-ai.webp",
        "excerpt": "Một danh sách ngắn giúp buổi trao đổi với bác sĩ rõ ràng và tiết kiệm thời gian hơn.",
        "content": """Khám răng lần đầu không cần chuẩn bị phức tạp. Điều hữu ích nhất là ghi lại vấn đề bạn đang gặp: vị trí khó chịu, thời điểm xuất hiện và điều gì làm triệu chứng tăng hoặc giảm.\n\nNếu đang dùng thuốc, có bệnh nền hoặc từng điều trị răng miệng, hãy thông báo cho bác sĩ. Bạn cũng có thể mang theo phim chụp hoặc hồ sơ cũ nếu có.\n\nTrong buổi tư vấn, nên hỏi rõ mục tiêu điều trị, các lựa chọn phù hợp, thời gian dự kiến, chi phí tham khảo và cách theo dõi sau hẹn. Nội dung bài viết chỉ mang tính tham khảo, không thay thế thăm khám trực tiếp.""",
    },
    {
        "slug": "dau-rang-khi-nao-can-hen-kham-som",
        "title": "Đau răng: khi nào nên hẹn khám sớm?",
        "category": "Chăm sóc răng miệng",
        "cover_image": "service-root-canal-ai.webp",
        "excerpt": "Một số dấu hiệu cần được đánh giá sớm thay vì tự xử lý kéo dài tại nhà.",
        "content": """Cơn đau răng có thể có nhiều nguyên nhân và không thể kết luận chỉ qua mô tả trực tuyến. Bạn nên hẹn khám sớm nếu đau tăng dần, tái diễn, sưng vùng nướu hoặc mặt, có chảy máu kéo dài, răng bị chấn thương hay ảnh hưởng đến ăn uống và giấc ngủ.\n\nKhi có dấu hiệu khó thở, khó nuốt, sưng lan nhanh hoặc tình trạng khẩn cấp khác, cần tìm hỗ trợ y tế phù hợp ngay. Không tự dùng thuốc điều trị kéo dài khi chưa được tư vấn chuyên môn.\n\nBác sĩ sẽ thăm khám trực tiếp và có thể chỉ định phương án phù hợp theo tình trạng thực tế.""",
    },
    {
        "slug": "cach-doc-bang-gia-dich-vu-nha-khoa",
        "title": "Cách đọc bảng giá dịch vụ nha khoa trước khi đặt lịch",
        "category": "Chi phí & quy trình",
        "cover_image": "service-veneer-ai.webp",
        "excerpt": "Ba thông tin nên đối chiếu để việc trao đổi chi phí minh bạch hơn.",
        "content": """Bảng giá giúp bạn hình dung mức chi phí tham khảo, nhưng không thay thế kế hoạch điều trị cá nhân. Khi xem một dịch vụ, hãy đối chiếu tên dịch vụ, phạm vi công việc và thời gian dự kiến.\n\nỞ buổi tư vấn, nên hỏi phần nào đã bao gồm trong mức giá, những khoản nào có thể phát sinh theo tình trạng thực tế và chính sách bảo hành đang áp dụng. Hãy yêu cầu xác nhận bằng văn bản nếu bạn cần lưu lại phương án.\n\nMinh bạch về thông tin giúp bạn chủ động quyết định; không nên lựa chọn chỉ dựa trên một con số quảng cáo.""",
    },
    {
        "slug": "dat-lich-nha-khoa-truc-tuyen-hieu-qua",
        "title": "Đặt lịch nha khoa trực tuyến: 4 thông tin giúp xác nhận nhanh hơn",
        "category": "Hướng dẫn",
        "cover_image": "hero-human-ai.webp",
        "excerpt": "Cung cấp đủ thông tin cơ bản để phòng khám kiểm tra nhu cầu và khung giờ phù hợp.",
        "content": """Khi gửi yêu cầu đặt lịch, hãy chọn dịch vụ bạn muốn tham khảo, ngày giờ thuận tiện, họ tên và phương thức liên hệ chính xác. Phần ghi chú có thể nêu ngắn gọn nhu cầu để lễ tân chuẩn bị tốt hơn.\n\nKhung giờ hiển thị là trạng thái tại thời điểm tra cứu. Lịch chỉ được xác nhận sau khi phòng khám phản hồi. Nếu cần đổi hoặc huỷ, hãy thực hiện sớm theo quy định trên website.\n\nKhông gửi thông tin sức khỏe nhạy cảm không cần thiết qua biểu mẫu công khai. Nội dung chuyên môn nên trao đổi trực tiếp với bác sĩ trong buổi hẹn.""",
    },
]


def seed_information_content():
    """Chèn FAQ và bài kiến thức nếu chưa tồn tại; không ghi đè nội dung đã biên tập."""
    faq_added = 0
    post_added = 0
    for order, (question, answer) in enumerate(FAQS, start=1):
        if not FAQ.query.filter_by(question=question).first():
            db.session.add(FAQ(question=question, answer=answer, sort_order=order, is_active=1))
            faq_added += 1
    for post in POSTS:
        if not BlogPost.query.filter_by(slug=post["slug"]).first():
            db.session.add(BlogPost(**post, status="published", published_at=datetime.utcnow()))
            post_added += 1
    db.session.commit()
    return faq_added, post_added
