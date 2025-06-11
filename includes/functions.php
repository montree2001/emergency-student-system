<?php
// includes/functions.php

// เปิดการแสดง error สำหรับ debugging (ควรปิดใน production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    if (empty($data)) {
        return '';
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

// ฟังก์ชันป้องกัน SQL Injection
function escape_string($data) {
    global $conn;
    if ($conn) {
        return $conn->real_escape_string($data);
    }
    return $data;
}

// ฟังก์ชันสร้างรหัสนักเรียน
function generate_student_code($education_level, $department_id, $class_year) {
    global $conn;
    
    if (!$conn) {
        return null;
    }
    
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
            WHERE student_code LIKE ?";
    
    $search_pattern = $prefix_code . '%';
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $running = ($row['max_running'] ?? 0) + 1;
    
    // สร้างรหัสนักเรียนสมบูรณ์
    $student_code = $prefix_code . str_pad($running, 3, '0', STR_PAD_LEFT);
    
    $stmt->close();
    return $student_code;
}

// ฟังก์ชันสร้าง hash password ที่ปลอดภัย
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// ฟังก์ชันตรวจสอบ password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// ฟังก์ชันบันทึก log กิจกรรม
function log_activity($admin_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO activity_logs 
                          (admin_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        return false;
    }
    
    // แปลงค่าเป็น JSON ถ้าไม่ใช่ string
    if ($old_values !== null && !is_string($old_values)) {
        $old_values = json_encode($old_values, JSON_UNESCAPED_UNICODE);
    }
    
    if ($new_values !== null && !is_string($new_values)) {
        $new_values = json_encode($new_values, JSON_UNESCAPED_UNICODE);
    }
    
    $stmt->bind_param("issiisss", $admin_id, $action, $table_name, $record_id, $old_values, $new_values, $ip_address, $user_agent);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

// ฟังก์ชันสร้าง CSRF Token
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// ฟังก์ชันตรวจสอบ CSRF Token
function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    return true;
}

// ฟังก์ชันตรวจสอบการเชื่อมต่อฐานข้อมูล
function check_database_connection() {
    global $conn;
    
    if (!$conn || $conn->connect_error) {
        die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
    }
    
    return true;
}
?>