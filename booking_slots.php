<?php
session_start(); header('Content-Type: application/json; charset=utf-8'); require_once 'config.php'; require_once 'booking_repository.php'; ensureBookingSchema($conn);
$date=trim($_GET['date']??''); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$date<date('Y-m-d')){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Ngày không hợp lệ.']);exit;}
echo json_encode(['success'=>true,'slots'=>bookingAvailableSlots($conn,$date)],JSON_UNESCAPED_UNICODE);
