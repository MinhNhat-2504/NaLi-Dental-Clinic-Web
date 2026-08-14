<?php
/** Core booking rules shared by the public booking flow and customer actions. */
function ensureBookingSchema(mysqli $conn): void {
    static $done = false; if ($done) return; $done = true;
    $ddl = [
        "CREATE TABLE IF NOT EXISTS appointment_slots (
            appointment_date DATE NOT NULL, appointment_time TIME NOT NULL,
            appointment_id INT NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (appointment_date, appointment_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS promotions (
            id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL UNIQUE,
            discount_type ENUM('fixed','percent') NOT NULL DEFAULT 'fixed', discount_value DECIMAL(12,2) NOT NULL,
            min_order DECIMAL(12,2) NOT NULL DEFAULT 0, max_uses INT NULL, used_count INT NOT NULL DEFAULT 0,
            starts_at DATETIME NULL, ends_at DATETIME NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS booking_rate_limits (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, action_name VARCHAR(40) NOT NULL, client_key VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_rate(action_name,client_key,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS appointment_reminders (
            appointment_id INT PRIMARY KEY, channel ENUM('email','zalo') NOT NULL DEFAULT 'email',
            scheduled_for DATETIME NOT NULL, sent_at DATETIME NULL, last_error VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS account_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
            purpose ENUM('verify_email','reset_password') NOT NULL, expires_at DATETIME NOT NULL,
            used_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token(patient_id,purpose,expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($ddl as $sql) $conn->query($sql);
    foreach ([
        "ALTER TABLE patients ADD COLUMN email_verified_at DATETIME NULL",
        "ALTER TABLE appointments ADD COLUMN cancelled_at DATETIME NULL",
        "ALTER TABLE appointments ADD COLUMN cancellation_reason VARCHAR(500) NULL",
        "ALTER TABLE appointments ADD COLUMN rescheduled_from_id INT NULL",
    ] as $sql) {
        // MySQL has no portable ADD COLUMN IF NOT EXISTS in older versions; duplicate errors are benign.
        @$conn->query($sql);
    }
}

function bookingClientKey(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $account = $_SESSION['auth_user']['id'] ?? 'guest';
    return substr(hash('sha256', $ip . '|' . $account), 0, 96);
}

function bookingAllowRequest(mysqli $conn, string $action, int $limit, int $seconds): bool {
    $key = bookingClientKey();
    $stmt=$conn->prepare("SELECT COUNT(*) c FROM booking_rate_limits WHERE action_name=? AND client_key=? AND created_at >= (UTC_TIMESTAMP() - INTERVAL ? SECOND)");
    $stmt->bind_param('ssi',$action,$key,$seconds); $stmt->execute(); $count=(int)($stmt->get_result()->fetch_assoc()['c']??0); $stmt->close();
    if ($count >= $limit) return false;
    $stmt=$conn->prepare('INSERT INTO booking_rate_limits(action_name,client_key) VALUES(?,?)'); $stmt->bind_param('ss',$action,$key); $stmt->execute(); $stmt->close();
    return true;
}

function bookingTimes(): array { return ['08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00']; }
function bookingValidDateTime(string $date, string $time): bool {
    $d=DateTime::createFromFormat('Y-m-d',$date); if(!$d || $d->format('Y-m-d')!==$date || $date < date('Y-m-d')) return false;
    return in_array(substr($time,0,5), bookingTimes(), true);
}
function bookingAvailableSlots(mysqli $conn, string $date): array {
    $taken=[]; $stmt=$conn->prepare("SELECT appointment_time FROM appointments WHERE appointment_date=? AND status IN ('pending','confirmed')");$stmt->bind_param('s',$date);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())$taken[substr($r['appointment_time'],0,5)]=true;$stmt->close();
    $stmt=$conn->prepare('SELECT appointment_time FROM appointment_slots WHERE appointment_date=?');$stmt->bind_param('s',$date);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())$taken[substr($r['appointment_time'],0,5)]=true;$stmt->close();
    return array_map(fn($time)=>['time'=>$time,'available'=>!isset($taken[$time])],bookingTimes());
}
function bookingPromotion(mysqli $conn, string $code, float $subtotal): array {
    if ($code==='') return ['valid'=>true,'code'=>'','discount'=>0.0];
    $code=strtoupper(trim($code)); $stmt=$conn->prepare("SELECT * FROM promotions WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>=UTC_TIMESTAMP()) LIMIT 1");$stmt->bind_param('s',$code);$stmt->execute();$p=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$p || $subtotal<(float)$p['min_order'] || ($p['max_uses']!==null && (int)$p['used_count'] >= (int)$p['max_uses'])) return ['valid'=>false,'message'=>'Mã ưu đãi không hợp lệ hoặc đã hết lượt dùng.'];
    $discount=$p['discount_type']==='percent' ? $subtotal*((float)$p['discount_value']/100) : (float)$p['discount_value'];
    return ['valid'=>true,'code'=>$code,'discount'=>min($subtotal,max(0,$discount)),'promotion_id'=>(int)$p['id']];
}
function bookingCreate(mysqli $conn, array $data): array {
    $name=trim($data['name']??'');$phone=trim($data['phone']??'');$email=trim($data['email']??'');$date=trim($data['date']??'');$time=substr(trim($data['time']??''),0,5);$service=trim($data['service']??'');$notes=trim($data['notes']??'');$discountCode=trim($data['discount_code']??'');
    if($name===''||!preg_match('/^0\d{9}$/',$phone)||!bookingValidDateTime($date,$time)) return ['success'=>false,'message'=>'Thông tin ngày, giờ hoặc liên hệ không hợp lệ.'];
    $productId=null;$subtotal=0.0;if($service!==''){$s=$conn->prepare('SELECT id,price FROM products WHERE name=? AND COALESCE(is_active,1)=1 LIMIT 1');$s->bind_param('s',$service);$s->execute();$p=$s->get_result()->fetch_assoc();$s->close();if($p){$productId=(int)$p['id'];$subtotal=(float)$p['price'];}}
    $promo=bookingPromotion($conn,$discountCode,$subtotal);if(!$promo['valid'])return ['success'=>false,'message'=>$promo['message']];
    $lock='nali-slot-'.preg_replace('/[^0-9]/','',$date.'-'.$time);$s=$conn->prepare('SELECT GET_LOCK(?,5) locked');$s->bind_param('s',$lock);$s->execute();$locked=(int)($s->get_result()->fetch_assoc()['locked']??0);$s->close();if(!$locked)return ['success'=>false,'message'=>'Khung giờ đang được xử lý, vui lòng thử lại.'];
    try {
        $conn->begin_transaction();
        $s=$conn->prepare("SELECT id FROM appointments WHERE appointment_date=? AND appointment_time=? AND status IN ('pending','confirmed') LIMIT 1 FOR UPDATE");$timeSql=$time.':00';$s->bind_param('ss',$date,$timeSql);$s->execute();$busy=$s->get_result()->num_rows>0;$s->close();
        if($busy){$conn->rollback();return ['success'=>false,'message'=>'Khung giờ này vừa có người đặt. Vui lòng chọn giờ khác.'];}
        $extra=[];if(!empty($data['category']))$extra[]='Nhóm: '.trim($data['category']);if(!empty($data['doctor']))$extra[]='Bác sĩ mong muốn: '.trim($data['doctor']);if($extra)$notes=trim($notes.($notes?' | ':'').implode(' | ',$extra));
        $uid=(isset($_SESSION['auth_user']['email']) && isset($_SESSION['auth_user']['id']))?(int)$_SESSION['auth_user']['id']:null;$pid=$productId?(string)$productId:'';$total=max(0,$subtotal-(float)$promo['discount']);
        $s=$conn->prepare("INSERT INTO appointments(user_id,product_ids,customer_name,customer_phone,customer_email,appointment_date,appointment_time,notes,payment_method,discount_code,discount_amount,total_price,status) VALUES(?,?,?,?,?,?,?,?, 'cash', ?,?,?, 'pending')");
        $s->bind_param('issssssssdd',$uid,$pid,$name,$phone,$email,$date,$timeSql,$notes,$promo['code'],$promo['discount'],$total);
        if(!$s->execute()) throw new RuntimeException($s->error); $appointmentId=$conn->insert_id; $s->close();
        $s=$conn->prepare('INSERT INTO appointment_slots(appointment_date,appointment_time,appointment_id) VALUES(?,?,?)');$s->bind_param('ssi',$date,$timeSql,$appointmentId);if(!$s->execute()) throw new RuntimeException('Khung giờ vừa được đặt.');$s->close();
        if(!empty($promo['promotion_id'])){$s=$conn->prepare('UPDATE promotions SET used_count=used_count+1 WHERE id=?');$s->bind_param('i',$promo['promotion_id']);$s->execute();$s->close();}
        $scheduled=date('Y-m-d H:i:s',strtotime($date.' '.$timeSql.' -24 hours'));$s=$conn->prepare("INSERT INTO appointment_reminders(appointment_id,scheduled_for) VALUES(?,?)");$s->bind_param('is',$appointmentId,$scheduled);$s->execute();$s->close();
        $conn->commit(); return ['success'=>true,'appointment_id'=>$appointmentId,'discount'=>(float)$promo['discount'],'total'=>$total];
    } catch (Throwable $e) { $conn->rollback(); return ['success'=>false,'message'=>'Không thể tạo lịch hẹn: '.$e->getMessage()]; }
    finally { $release=$conn->prepare('SELECT RELEASE_LOCK(?)'); if($release){$release->bind_param('s',$lock);$release->execute();$release->close();} }
}
?>
