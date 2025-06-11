<?php
// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    return $data;
}

// ฟังก์ชันสร้างรหัสนักเรียน
function generate_student_code($education_level, $department_id, $class_year) {
    // สร้างรหัสในรูปแบบ YYDDCGxxx
    // YY = ปีการศึกษาปัจจุบัน (2 หลักสุดท้าย)
    // DD = รหัสแผนก (2 หลัก)
    // C = ระดับชั้น (ปวช=1, ปวส=2)
    // G = ชั้นปี
    // xxx = running number
    
    global $conn;
    
    $current_year = date('Y') + 543; // พ.ศ.
    $year_code = substr($current_year, -2);
    
    // ดึงรหัสแผนก
    $dept_code = str_pad($department_id, 2, '0', STR_PAD_LEFT);
    
    // กำหนดรหัสระดับชั้น
    $level_code = ($education_level == 'ปวช') ? '1' : '2';
    
    // สร้างรหัสเบื้องต้น
    $prefix_code = $year_code . $dept_code . $level_code . $class_year;
    
    // หาเลขลำดับล่าสุด
    $sql = "SELECT MAX(CAST(SUBSTRING(student_code, 8) AS UNSIGNED)) as max_running 
            FROM students 
            WHERE student_code LIKE '$prefix_code%'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $running = ($row['max_running'] ?? 0) + 1;
    
    // สร้างรหัสนักเรียนสมบูรณ์
    $student_code = $prefix_code . str_pad($running, 3, '0', STR_PAD_LEFT);
    
    return $student_code;
}

// ฟังก์ชันบันทึก log กิจกรรม
function log_activity($admin_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO activity_logs 
                          (admin_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // แปลงค่าเป็น JSON ถ้าไม่ใช่ string
    if ($old_values !== null && !is_string($old_values)) {
        $old_values = json_encode($old_values, JSON_UNESCAPED_UNICODE);
    }
    
    if ($new_values !== null && !is_string($new_values)) {
        $new_values = json_encode($new_values, JSON_UNESCAPED_UNICODE);
    }
    
    $stmt->bind_param("issiisss", $admin_id, $action, $table_name, $record_id, $old_values, $new_values, $ip_address, $user_agent);
    $stmt->execute();
}