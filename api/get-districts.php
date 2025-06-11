<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['province_id']) && is_numeric($_GET['province_id'])) {
    $province_id = (int)$_GET['province_id'];
    
    $stmt = $conn->prepare("SELECT id, district_name FROM districts WHERE province_id = ? ORDER BY district_name");
    $stmt->bind_param("i", $province_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $districts = [];
    
    while ($row = $result->fetch_assoc()) {
        $districts[] = $row;
    }
    
    echo json_encode($districts);
} else {
    echo json_encode([]);
}