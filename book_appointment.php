<?php
session_start(); header('Content-Type: application/json; charset=utf-8'); require_once 'config.php'; require_once 'booking_repository.php'; ensureBookingSchema($conn);
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'Chỉ hỗ trợ POST.']);exit;}
if(!bookingAllowRequest($conn,'booking',5,3600)){http_response_code(429);echo json_encode(['success'=>false,'message'=>'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau một giờ.']);exit;}
$data=json_decode(file_get_contents('php://input'),true)?:[]; $out=bookingCreate($conn,$data);
if(!empty($out['success']) && !empty($data['reschedule_id']) && isset($_SESSION['auth_user']['email'])){
  $old=(int)$data['reschedule_id'];$uid=(int)$_SESSION['auth_user']['id'];$email=$_SESSION['auth_user']['email'];
  $s=$conn->prepare("SELECT id FROM appointments WHERE id=? AND (user_id=? OR customer_email=?) AND status IN ('pending','confirmed') AND TIMESTAMP(appointment_date,appointment_time)>DATE_ADD(NOW(),INTERVAL 4 HOUR)");$s->bind_param('iis',$old,$uid,$email);$s->execute();$ok=$s->get_result()->num_rows>0;$s->close();
  if($ok){$reason='Khách đổi lịch';$s=$conn->prepare("UPDATE appointments SET status='cancelled',cancelled_at=NOW(),cancellation_reason=? WHERE id=?");$s->bind_param('si',$reason,$old);$s->execute();$s=$conn->prepare('DELETE FROM appointment_slots WHERE appointment_id=?');$s->bind_param('i',$old);$s->execute();$new=(int)$out['appointment_id'];$s=$conn->prepare('UPDATE appointments SET rescheduled_from_id=? WHERE id=?');$s->bind_param('ii',$old,$new);$s->execute();}
}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
