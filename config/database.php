<?php
// config/database.php
$host = "localhost";
$username = "root";
$password = "";
$database = "emergency_student_system";

try {
    $conn = new mysqli($host, $username, $password, $database);
    
    // ตั้งค่า charset เป็น utf8mb4
    $conn->set_charset("utf8mb4");
    
    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
    }
    
} catch (Exception $e) {
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

// สำหรับ PDO (ทางเลือก)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("การเชื่อมต่อ PDO ล้มเหลว: " . $e->getMessage());
}
?>