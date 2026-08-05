<?php
/**
 * enrich_services.php — Cập nhật mô tả dịch vụ thành nội dung chuyên nghiệp,
 * "thật" như phòng khám thực tế. Idempotent: khớp theo TÊN, chạy lại vẫn ổn.
 * Chạy:  php enrich_services.php
 */
require_once 'config.php';

$desc = [
'Tẩy trắng răng Laser'      => 'Công nghệ tẩy trắng Laser Whitening thế hệ mới, làm sáng răng 3–5 tông chỉ sau 45 phút mà không ê buốt. An toàn cho men răng, hiệu quả duy trì đến 2 năm.',
'Bọc răng sứ Titan'         => 'Mão sứ Titan cứng chắc, ôm khít cùi răng, chịu lực nhai tốt và chống bám màu. Phù hợp phục hình răng đã điều trị tủy. Bảo hành 5–7 năm.',
'Niềng răng Invisalign'     => 'Khay niềng trong suốt Invisalign nhập khẩu chính hãng từ Mỹ, gần như vô hình, tháo lắp linh hoạt. Mô phỏng lộ trình 3D ClinCheck, ăn nhai thoải mái, ít đau.',
'Cấy ghép Implant'          => 'Trụ Implant Hàn Quốc/Thụy Sĩ tích hợp xương chắc chắn, phục hồi răng mất như răng thật. Phẫu thuật nhẹ nhàng với máng hướng dẫn, bảo hành trụ trọn đời.',
'Nhổ răng khôn'             => 'Tiểu phẫu nhổ răng khôn bằng máy siêu âm Piezotome, ít xâm lấn, giảm sưng đau, mau lành. Gây tê hiện đại, quy trình vô trùng khép kín.',
'Điều trị tủy răng'         => 'Điều trị nội nha với máy đo chóp và trâm xoay Ni-Ti, làm sạch triệt để ống tủy, bảo tồn răng thật tối đa. Giảm đau nhức, ngăn tái viêm.',
'Trám răng thẩm mỹ'         => 'Trám răng bằng composite nano cao cấp, màu sắc tự nhiên như răng thật, độ bền cao. Phục hồi răng sâu, mẻ, thưa chỉ trong một lần hẹn.',
'Lấy cao răng'              => 'Làm sạch cao răng và mảng bám bằng sóng siêu âm nhẹ nhàng, đánh bóng sáng khỏe. Ngăn viêm nướu, hôi miệng — nên thực hiện định kỳ 6 tháng/lần.',
'Nhổ răng sữa'             => 'Nhổ răng sữa cho bé nhẹ nhàng với gây tê bôi không đau. Bác sĩ nhi khoa khéo léo giúp bé hợp tác, thoải mái, không sợ hãi.',
'Trám răng sữa'            => 'Trám răng sâu cho bé bằng vật liệu Glass Ionomer giải phóng Fluor, bảo vệ răng sữa và ngăn sâu răng lan rộng.',
'Niềng răng trẻ em'        => 'Chỉnh nha can thiệp sớm định hướng xương hàm và răng vĩnh viễn mọc đều đẹp, tận dụng giai đoạn vàng 6–12 tuổi.',
'Bôi Fluoride ngừa sâu'    => 'Bôi gel Fluoride nồng độ chuẩn giúp tái khoáng men răng, tăng sức đề kháng chống sâu răng cho bé. Nhanh gọn, không đau.',
'Hàm giả tháo lắp'         => 'Hàm giả tháo lắp khung nhẹ, ôm sát nướu, phục hồi ăn nhai và thẩm mỹ cho người cao tuổi mất nhiều răng. Dễ vệ sinh, chi phí hợp lý.',
'Điều trị nha chu'          => 'Điều trị viêm nha chu chuyên sâu: làm sạch cao răng dưới nướu, xử lý túi nha chu, kiểm soát viêm và tiêu xương. Giữ răng chắc khỏe lâu dài.',
'Tư vấn nha khoa cao tuổi'  => 'Khám tổng quát và tư vấn kế hoạch chăm sóc răng miệng cá nhân hoá cho người cao tuổi, chú trọng bệnh lý nền. Miễn phí buổi tư vấn đầu tiên.',
];

$stmt = $conn->prepare("UPDATE products SET description = ? WHERE name = ?");
$updated = 0;
foreach ($desc as $name => $text) {
    $stmt->bind_param('ss', $text, $name);
    $stmt->execute();
    $updated += $stmt->affected_rows > 0 ? 1 : 0;
}
echo "✅ Đã cập nhật mô tả cho $updated / " . count($desc) . " dịch vụ.\n";
$conn->close();
