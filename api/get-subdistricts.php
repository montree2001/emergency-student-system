<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['district_id']) && is_numeric($_GET['district_id'])) {
    $district_id = (int)$_GET['district_id'];
    
    $stmt = $conn->prepare("SELECT id, subdistrict_name, postal_code FROM subdistricts WHERE district_id = ? ORDER BY subdistrict_name");
    $stmt->bind_param("i", $district_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $subdistricts = [];
    
    while ($row = $result->fetch_assoc()) {
        $subdistricts[] = $row;
    }
    
    echo json_encode($subdistricts);
} else {
    echo json_encode([]);
}