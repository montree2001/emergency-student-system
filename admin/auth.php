<?php
// ตรวจสอบการเข้าสู่ระบบของผู้ดูแล
function check_admin_login() {
    // ตรวจสอบว่ามี session หรือไม่
    if (!isset($_SESSION['admin_id'])) {
        // ถ้าไม่มี session ให้ redirect ไปยังหน้า login
        header('Location: login.php');
        exit;
    }
    
    // ตรวจสอบเวลาที่ไม่มีการใช้งาน (30 นาที)
    $timeout = 30 * 60; // 30 นาที
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // หมดเวลา session
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    
// อัปเดตเวลาล่าสุดที่มีการใช้งาน
$_SESSION['last_activity'] = time();
    
// ตรวจสอบความถูกต้องของข้อมูลผู้ใช้ (ถ้าต้องการเพิ่มความปลอดภัย)
global $conn;
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT is_active FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // ไม่พบข้อมูลผู้ใช้หรือบัญชีถูกปิดใช้งาน
    session_unset();
    session_destroy();
    header('Location: login.php?error=invalid_account');
    exit;
}

$admin = $result->fetch_assoc();
if ($admin['is_active'] != 1) {
    // บัญชีถูกปิดใช้งาน
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_disabled');
    exit;
}
}

// ตรวจสอบสิทธิ์ผู้ดูแลระดับสูง (super_admin)
function check_super_admin() {
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    // ไม่มีสิทธิ์เข้าถึง
    header('Location: unauthorized.php');
    exit;
}
}

// สร้าง CSRF token
function generate_csrf_token() {
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
return $_SESSION['csrf_token'];
}

// ตรวจสอบ CSRF token
function verify_csrf_token($token) {
if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
    // Token ไม่ถูกต้อง
    die('CSRF token validation failed');
}

// สร้าง token ใหม่หลังจากใช้งาน
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}