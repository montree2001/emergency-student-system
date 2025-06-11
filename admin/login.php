<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// ตรวจสอบว่าเข้าสู่ระบบแล้วหรือไม่
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    // ดึงข้อมูลผู้ใช้
    $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, role FROM admins WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $admin['password_hash'])) {
            // สร้าง session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['last_activity'] = time();
            
            // บันทึกเวลาเข้าสู่ระบบ
            $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $admin['id']);
            $stmt->execute();
            
            // บันทึก log
            log_activity($admin['id'], 'login', 'admins', $admin['id'], null, null);
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'รหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'ไม่พบชื่อผู้ใช้นี้ในระบบ';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card shadow">
            <div class="card-body p-4">
                <h3 class="text-center mb-4">เข้าสู่ระบบผู้ดูแล</h3>
                <h6 class="text-center mb-4 text-muted">ระบบสำรวจข้อมูลนักเรียน-นักศึกษา (แผนฉุกเฉิน)</h6>
                
                <?php if ($error) : ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">รหัสผ่าน</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>