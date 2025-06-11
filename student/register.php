<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// เตรียมข้อมูลสำหรับ dropdown
$departments = $conn->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
$provinces = $conn->query("SELECT * FROM provinces ORDER BY province_name");

// กำหนดตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$alert = '';

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ตรวจสอบและทำความสะอาดข้อมูล
    $prefix = sanitize_input($_POST['prefix']);
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $education_level = sanitize_input($_POST['education_level']);
    $class_year = (int)$_POST['class_year'];
    $group_number = (int)$_POST['group_number'];
    $department_id = (int)$_POST['department_id'];
    $house_number = sanitize_input($_POST['house_number']);
    $village_number = (int)$_POST['village_number'] ?? null;
    $village_name = sanitize_input($_POST['village_name']); // หมู่บ้านพิมพ์เองเสมอ
    $phone = sanitize_input($_POST['phone'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');

    // ข้อมูลติดต่อฉุกเฉิน
    $emergency_contact_name = sanitize_input($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = sanitize_input($_POST['emergency_contact_phone'] ?? '');
    $emergency_contact_relation = sanitize_input($_POST['emergency_contact_relation'] ?? '');

    // ข้อมูลสุขภาพ
    $medical_conditions = sanitize_input($_POST['medical_conditions'] ?? '');
    $allergies = sanitize_input($_POST['allergies'] ?? '');
    $special_needs = sanitize_input($_POST['special_needs'] ?? '');

    // จัดการข้อมูลที่อยู่
    $province_name = '';
    $district_name = '';
    $subdistrict_name = '';
    $subdistrict_id = null;
    $village_id = null;

    // ตรวจสอบว่าเลือกตำบลจากฐานข้อมูลหรือพิมพ์เอง
    if (!empty($_POST['subdistrict_id']) && is_numeric($_POST['subdistrict_id'])) {
        $subdistrict_id = (int)$_POST['subdistrict_id'];
    } else {
        // กรณีพิมพ์เอง - บันทึกข้อมูลใหม่
        $province_name = sanitize_input($_POST['custom_province'] ?? '');
        $district_name = sanitize_input($_POST['custom_district'] ?? '');
        $subdistrict_name = sanitize_input($_POST['custom_subdistrict'] ?? '');

        // บันทึกข้อมูลที่อยู่ใหม่ลงฐานข้อมูล
        try {
            $conn->begin_transaction();

            // เพิ่มจังหวัด
            $stmt = $conn->prepare("INSERT IGNORE INTO provinces (province_name) VALUES (?)");
            $stmt->bind_param("s", $province_name);
            $stmt->execute();
            $province_id = $conn->insert_id ?: $conn->query("SELECT id FROM provinces WHERE province_name = '$province_name'")->fetch_assoc()['id'];

            // เพิ่มอำเภอ
            $stmt = $conn->prepare("INSERT IGNORE INTO districts (district_name, province_id) VALUES (?, ?)");
            $stmt->bind_param("si", $district_name, $province_id);
            $stmt->execute();
            $district_id = $conn->insert_id ?: $conn->query("SELECT id FROM districts WHERE district_name = '$district_name' AND province_id = $province_id")->fetch_assoc()['id'];

            // เพิ่มตำบล
            $stmt = $conn->prepare("INSERT IGNORE INTO subdistricts (subdistrict_name, district_id) VALUES (?, ?)");
            $stmt->bind_param("si", $subdistrict_name, $district_id);
            $stmt->execute();
            $subdistrict_id = $conn->insert_id ?: $conn->query("SELECT id FROM subdistricts WHERE subdistrict_name = '$subdistrict_name' AND district_id = $district_id")->fetch_assoc()['id'];

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $alert = '<div class="alert alert-danger">เกิดข้อผิดพลาดในการบันทึกข้อมูลที่อยู่: ' . $e->getMessage() . '</div>';
        }
    }

    // สร้างหรือหาข้อมูลหมู่บ้าน (พิมพ์เองเสมอ)
    if ($subdistrict_id && !empty($village_name)) {
        try {
            // ตรวจสอบว่ามีหมู่บ้านนี้อยู่แล้วหรือไม่
            $stmt = $conn->prepare("SELECT id FROM villages WHERE village_name = ? AND village_number = ? AND subdistrict_id = ?");
            $stmt->bind_param("sii", $village_name, $village_number, $subdistrict_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $village_id = $result->fetch_assoc()['id'];
            } else {
                // เพิ่มหมู่บ้านใหม่
                $stmt = $conn->prepare("INSERT INTO villages (village_name, village_number, subdistrict_id) VALUES (?, ?, ?)");
                $stmt->bind_param("sii", $village_name, $village_number, $subdistrict_id);
                $stmt->execute();
                $village_id = $conn->insert_id;
            }
        } catch (Exception $e) {
            $alert = '<div class="alert alert-danger">เกิดข้อผิดพลาดในการบันทึกข้อมูลหมู่บ้าน: ' . $e->getMessage() . '</div>';
        }
    }

    // สร้างรหัสนักเรียน
    $student_code = generate_student_code($education_level, $department_id, $class_year);

    // บันทึกข้อมูลนักเรียนลงฐานข้อมูล
    if ($village_id && empty($alert)) {
        try {
            $stmt = $conn->prepare("INSERT INTO students 
                                   (student_code, prefix, first_name, last_name, education_level, 
                                    class_year, group_number, department_id, house_number, 
                                    village_number, village_id, phone, email,
                                    emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                                    medical_conditions, allergies, special_needs) 
                                   VALUES 
                                   (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "sssssiiisiissssss",
                $student_code,
                $prefix,
                $first_name,
                $last_name,
                $education_level,
                $class_year,
                $group_number,
                $department_id,
                $house_number,
                $village_number,
                $village_id,
                $phone,
                $email,
                $emergency_contact_name,
                $emergency_contact_phone,
                $emergency_contact_relation,
                $medical_conditions,
                $allergies,
                $special_needs
            );
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $alert = '<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>บันทึกข้อมูลเรียบร้อยแล้ว รหัสนักเรียน: <strong>' . $student_code . '</strong></div>';
                // ล้างฟอร์ม
                $_POST = array();
            } else {
                $alert = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>เกิดข้อผิดพลาดในการบันทึกข้อมูล</div>';
            }
        } catch (Exception $e) {
            $alert = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนข้อมูลนักเรียน-นักศึกษา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            overflow: hidden;
        }

        .header-section {
            background: linear-gradient(45deg, var(--primary-color), #4dabf7);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        }

        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .header-section h5 {
            font-weight: 300;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .form-section {
            padding: 2rem;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--success-color);
        }

        .form-label.required:after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }

        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), #4dabf7);
            border: none;
            border-radius: 50px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
        }

        .btn-secondary {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(45deg, var(--light-color), #ffffff);
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .address-toggle {
            background: var(--info-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .address-toggle:hover {
            background: #0aa2c0;
            transform: translateY(-2px);
        }

        .custom-input-section {
            background: var(--light-color);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid var(--info-color);
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(45deg, rgba(25, 135, 84, 0.1), rgba(25, 135, 84, 0.05));
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(45deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .progress-indicator {
            background: var(--light-color);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .progress-step {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 0.5rem;
            font-weight: 600;
        }

        .progress-step.active {
            background: var(--primary-color);
        }

        .progress-step.completed {
            background: var(--success-color);
        }

        @media (max-width: 768px) {
            .header-section {
                padding: 1.5rem 1rem;
            }

            .header-section h1 {
                font-size: 1.75rem;
            }

            .form-section {
                padding: 1rem;
            }

            .btn-primary {
                padding: 0.875rem 2rem;
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }

            .header-section h1 {
                font-size: 1.5rem;
            }

            .header-section h5 {
                font-size: 0.875rem;
            }
        }

        .select2-container--bootstrap-5 .select2-selection {
            border: 2px solid #e9ecef !important;
            border-radius: 10px !important;
            padding: 0.375rem 0.75rem !important;
            min-height: calc(1.5em + 1.5rem + 4px) !important;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15) !important;
        }
    </style>
</head>

<body>
    <div class="container-fluid px-3">
        <div class="main-container">
            <!-- Header Section -->
            <div class="header-section">
                <h1><i class="bi bi-person-plus-fill me-3"></i>ลงทะเบียนข้อมูลนักเรียน-นักศึกษา</h1>
                <h5>สำหรับแผนฉุกเฉิน กรณีอพยพจากเหตุไม่สงบชายแดนไทยกัมพูชา</h5>
            </div>

            <div class="form-section">
                <?php echo $alert; ?>

                <!-- Progress Indicator -->
                <div class="progress-indicator text-center">
                    <span class="progress-step active" id="step1">1</span>
                    <small>ข้อมูลส่วนตัว</small>
                    <span class="progress-step" id="step2">2</span>
                    <small>ข้อมูลที่อยู่</small>
                    <span class="progress-step" id="step3">3</span>
                    <small>ข้อมูลติดต่อ</small>
                    <span class="progress-step" id="step4">4</span>
                    <small>เสร็จสิ้น</small>
                </div>

                <form method="post" id="studentForm" class="needs-validation" novalidate>

                    <!-- Step 1: ข้อมูลส่วนตัว -->
                    <div class="step-content" id="content-step1">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-person-circle me-2"></i>ข้อมูลส่วนตัว
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-2 col-md-3 col-sm-4 mb-3">
                                        <label for="prefix" class="form-label required">คำนำหน้า</label>
                                        <select class="form-select" id="prefix" name="prefix" required>
                                            <option value="" selected disabled>เลือก</option>
                                            <option value="นาย">นาย</option>
                                            <option value="นางสาว">นางสาว</option>
                                            <option value="นาง">นาง</option>
                                            <option value="เด็กชาย">เด็กชาย</option>
                                            <option value="เด็กหญิง">เด็กหญิง</option>
                                        </select>
                                        <div class="invalid-feedback">กรุณาเลือกคำนำหน้า</div>
                                    </div>
                                    <div class="col-lg-5 col-md-4 col-sm-8 mb-3">
                                        <label for="first_name" class="form-label required">ชื่อ</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                        <div class="invalid-feedback">กรุณากรอกชื่อ</div>
                                    </div>
                                    <div class="col-lg-5 col-md-5 mb-3">
                                        <label for="last_name" class="form-label required">นามสกุล</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                        <div class="invalid-feedback">กรุณากรอกนามสกุล</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 mb-3">
                                        <label for="education_level" class="form-label required">ระดับชั้น</label>
                                        <select class="form-select" id="education_level" name="education_level" required>
                                            <option value="" selected disabled>เลือก</option>
                                            <option value="ปวช">ปวช.</option>
                                            <option value="ปวส">ปวส.</option>
                                        </select>
                                        <div class="invalid-feedback">กรุณาเลือกระดับชั้น</div>
                                    </div>
                                    <div class="col-lg-3 col-md-4 mb-3">
                                        <label for="class_year" class="form-label required">ชั้นปี</label>
                                        <select class="form-select" id="class_year" name="class_year" required disabled>
                                            <option value="" selected disabled>เลือกระดับชั้นก่อน</option>
                                        </select>
                                        <div class="invalid-feedback">กรุณาเลือกชั้นปี</div>
                                    </div>
                                    <div class="col-lg-3 col-md-4 mb-3">
                                        <label for="group_number" class="form-label required">กลุ่ม</label>
                                        <select class="form-select" id="group_number" name="group_number" required>
                                            <option value="" selected disabled>เลือก</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                        </select>
                                        <div class="invalid-feedback">กรุณาเลือกกลุ่ม</div>
                                    </div>
                                    <div class="col-lg-3 col-md-12 mb-3">
                                        <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" pattern="[0-9]{9,10}" placeholder="081-234-5678">
                                        <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="department_id" class="form-label required">แผนก/สาขาวิชา</label>
                                        <select class="form-select" id="department_id" name="department_id" required>
                                            <option value="" selected disabled>เลือกแผนก/สาขาวิชา</option>
                                            <?php while ($row = $departments->fetch_assoc()) : ?>
                                                <option value="<?php echo $row['id']; ?>"><?php echo $row['department_name']; ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="invalid-feedback">กรุณาเลือกแผนก/สาขาวิชา</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">อีเมล</label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="example@email.com">
                                        <div class="invalid-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: ข้อมูลที่อยู่ -->
                    <div class="step-content d-none" id="content-step2">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-house-door me-2"></i>ข้อมูลที่อยู่</span>
                                <button type="button" class="address-toggle" onclick="toggleAddressMode()">
                                    <i class="bi bi-pencil-square me-1"></i>พิมพ์เอง
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 col-md-4 mb-3">
                                        <label for="house_number" class="form-label required">บ้านเลขที่</label>
                                        <input type="text" class="form-control" id="house_number" name="house_number" required>
                                        <div class="invalid-feedback">กรุณากรอกบ้านเลขที่</div>
                                    </div>
                                    <div class="col-lg-3 col-md-4 mb-3">
                                        <label for="village_number" class="form-label">หมู่ที่</label>
                                        <input type="number" class="form-control" id="village_number" name="village_number" min="1" max="99">
                                    </div>
                                    <div class="col-lg-6 col-md-4 mb-3">
                                        <label for="village_name" class="form-label required">ชื่อหมู่บ้าน</label>
                                        <input type="text" class="form-control" id="village_name" name="village_name" placeholder="เช่น บ้านหนองบัว" required>
                                        <div class="invalid-feedback">กรุณากรอกชื่อหมู่บ้าน</div>
                                    </div>
                                </div>

                                <!-- โหมดเลือกจากฐานข้อมูล -->
                                <div id="select-mode">
                                    <div class="row">
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <label for="province_id" class="form-label required">จังหวัด</label>
                                            <select class="form-select" id="province_id">
                                                <option value="" selected disabled>เลือกจังหวัด</option>
                                                <?php while ($row = $provinces->fetch_assoc()) : ?>
                                                    <option value="<?php echo $row['id']; ?>"><?php echo $row['province_name']; ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
                                        </div>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <label for="district_id" class="form-label required">อำเภอ</label>
                                            <select class="form-select" id="district_id" disabled>
                                                <option value="" selected disabled>เลือกจังหวัดก่อน</option>
                                            </select>
                                            <div class="invalid-feedback">กรุณาเลือกอำเภอ</div>
                                        </div>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <label for="subdistrict_id" class="form-label required">ตำบล</label>
                                            <select class="form-select" id="subdistrict_id" name="subdistrict_id" disabled>
                                                <option value="" selected disabled>เลือกอำเภอก่อน</option>
                                            </select>
                                            <div class="invalid-feedback">กรุณาเลือกตำบล</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- โหมดพิมพ์เอง -->
                                <div id="custom-mode" class="d-none">
                                    <div class="custom-input-section">
                                        <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>กรณีไม่มีข้อมูลให้เลือก สามารถพิมพ์เองได้</h6>
                                        <div class="row">
                                            <div class="col-lg-4 col-md-6 mb-3">
                                                <label for="custom_province" class="form-label required">จังหวัด</label>
                                                <input type="text" class="form-control" id="custom_province" name="custom_province" placeholder="ชื่อจังหวัด">
                                                <div class="invalid-feedback">กรุณากรอกจังหวัด</div>
                                            </div>
                                            <div class="col-lg-4 col-md-6 mb-3">
                                                <label for="custom_district" class="form-label required">อำเภอ</label>
                                                <input type="text" class="form-control" id="custom_district" name="custom_district" placeholder="ชื่ออำเภอ">
                                                <div class="invalid-feedback">กรุณากรอกอำเภอ</div>
                                            </div>
                                            <div class="col-lg-4 col-md-6 mb-3">
                                                <label for="custom_subdistrict" class="form-label required">ตำบล</label>
                                                <input type="text" class="form-control" id="custom_subdistrict" name="custom_subdistrict" placeholder="ชื่อตำบล">
                                                <div class="invalid-feedback">กรุณากรอกตำบล</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: ข้อมูลติดต่อฉุกเฉิน -->
                    <div class="step-content d-none" id="content-step3">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-telephone-plus me-2"></i>ข้อมูลติดต่อฉุกเฉิน
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <label for="emergency_contact_name" class="form-label">ชื่อผู้ติดต่อฉุกเฉิน</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name">
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <label for="emergency_contact_phone" class="form-label">เบอร์โทรฉุกเฉิน</label>
                                        <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" pattern="[0-9]{9,10}">
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <label for="emergency_contact_relation" class="form-label">ความสัมพันธ์</label>
                                        <input type="text" class="form-control" id="emergency_contact_relation" name="emergency_contact_relation" placeholder="เช่น บิดา มารดา">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-heart-pulse me-2"></i>ข้อมูลสุขภาพ (ถ้ามี)
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <label for="medical_conditions" class="form-label">โรคประจำตัว</label>
                                        <input type="text" class="form-control" id="medical_conditions" name="medical_conditions">
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <label for="allergies" class="form-label">แพ้ยา/แพ้อาหาร</label>
                                        <input type="text" class="form-control" id="allergies" name="allergies">
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <label for="special_needs" class="form-label">ความต้องการพิเศษ</label>
                                        <input type="text" class="form-control" id="special_needs" name="special_needs">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: สรุป -->
                    <div class="step-content d-none" id="content-step4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-check-circle me-2"></i>ตรวจสอบข้อมูลก่อนส่ง
                            </div>
                            <div class="card-body">
                                <div id="summary-content">
                                    <!-- ข้อมูลสรุปจะแสดงที่นี่ -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display: none;">
                            <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                        </button>
                        <div></div>
                        <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                            ถัดไป<i class="bi bi-arrow-right ms-2"></i>
                        </button>
                        <button type="submit" class="btn btn-success d-none" id="submitBtn">
                            <i class="bi bi-check-circle me-2"></i>บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // เริ่มต้น Select2
            $('#department_id, #province_id, #district_id, #subdistrict_id').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            // ตัวแปรสำหรับควบคุม step
            let currentStep = 1;
            const totalSteps = 4;
            let isCustomMode = false;

            // ฟังก์ชันเปลี่ยน step
            window.changeStep = function(direction) {
                // ตรวจสอบความถูกต้องก่อนไปขั้นตอนต่อไป
                if (direction === 1 && !validateCurrentStep()) {
                    return;
                }

                const newStep = currentStep + direction;
                if (newStep < 1 || newStep > totalSteps) return;

                // ซ่อน step ปัจจุบัน
                $(`#content-step${currentStep}`).addClass('d-none');
                $(`#step${currentStep}`).removeClass('active').removeClass('completed');

                // แสดง step ใหม่
                currentStep = newStep;
                $(`#content-step${currentStep}`).removeClass('d-none');
                $(`#step${currentStep}`).addClass('active');

                // อัปเดต completed steps
                for (let i = 1; i < currentStep; i++) {
                    $(`#step${i}`).addClass('completed');
                }

                // จัดการปุ่ม
                updateButtons();

                // ถ้าเป็นขั้นตอนสุดท้าย ให้แสดงสรุป
                if (currentStep === totalSteps) {
                    generateSummary();
                }
            };

            // ฟังก์ชันตรวจสอบความถูกต้องของขั้นตอนปัจจุบัน
            function validateCurrentStep() {
                const currentForm = $(`#content-step${currentStep}`);
                const requiredFields = currentForm.find('[required]');
                let isValid = true;

                requiredFields.each(function() {
                    const field = $(this);
                    if (!field.val() || field.val() === '') {
                        field.addClass('is-invalid');
                        isValid = false;
                    } else {
                        field.removeClass('is-invalid');
                    }
                });

                // ตรวจสอบเพิ่มเติมสำหรับ step 2 (ที่อยู่)
                if (currentStep === 2) {
                    // ตรวจสอบชื่อหมู่บ้าน (required เสมอ)
                    if (!$('#village_name').val()) {
                        $('#village_name').addClass('is-invalid');
                        isValid = false;
                    }

                    if (isCustomMode) {
                        const customFields = ['custom_province', 'custom_district', 'custom_subdistrict'];
                        customFields.forEach(fieldId => {
                            const field = $(`#${fieldId}`);
                            if (!field.val()) {
                                field.addClass('is-invalid');
                                isValid = false;
                            } else {
                                field.removeClass('is-invalid');
                            }
                        });
                    } else {
                        if (!$('#subdistrict_id').val()) {
                            $('#subdistrict_id').addClass('is-invalid');
                            isValid = false;
                        }
                    }
                }

                return isValid;
            }

            // ฟังก์ชันอัปเดตปุ่ม
            function updateButtons() {
                console.log('Current step:', currentStep, 'Total steps:', totalSteps); // Debug

                // จัดการปุ่มย้อนกลับ
                if (currentStep > 1) {
                    $('#prevBtn').removeClass('d-none').show();
                } else {
                    $('#prevBtn').addClass('d-none').hide();
                }

                // จัดการปุ่มถัดไป และ ปุ่มบันทึก
                if (currentStep < totalSteps) {
                    $('#nextBtn').removeClass('d-none').show();
                    $('#submitBtn').addClass('d-none').hide();
                } else {
                    $('#nextBtn').addClass('d-none').hide();
                    $('#submitBtn').removeClass('d-none').show();
                    console.log('Submit button should be visible now'); // Debug
                }
            }

            // ฟังก์ชันสร้างสรุปข้อมูล
            function generateSummary() {
                const data = {
                    prefix: $('#prefix').val(),
                    first_name: $('#first_name').val(),
                    last_name: $('#last_name').val(),
                    education_level: $('#education_level').val(),
                    class_year: $('#class_year').val(),
                    group_number: $('#group_number').val(),
                    department: $('#department_id option:selected').text(),
                    phone: $('#phone').val(),
                    email: $('#email').val(),
                    house_number: $('#house_number').val(),
                    village_number: $('#village_number').val(),
                    village_name: $('#village_name').val()
                };

                // ข้อมูลที่อยู่
                let addressHtml = '';
                if (isCustomMode) {
                    addressHtml = `
                <strong>ที่อยู่:</strong> ${data.house_number} หมู่ ${data.village_number || '-'} 
                ${data.village_name} ตำบล${$('#custom_subdistrict').val()} 
                อำเภอ${$('#custom_district').val()} จังหวัด${$('#custom_province').val()}
            `;
                } else {
                    addressHtml = `
                <strong>ที่อยู่:</strong> ${data.house_number} หมู่ ${data.village_number || '-'} 
                ${data.village_name} ตำบล${$('#subdistrict_id option:selected').text()} 
                อำเภอ${$('#district_id option:selected').text()} จังหวัด${$('#province_id option:selected').text()}
            `;
                }

                const summaryHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">ข้อมูลส่วนตัว</h6>
                    <p><strong>ชื่อ-สกุล:</strong> ${data.prefix}${data.first_name} ${data.last_name}</p>
                    <p><strong>ระดับชั้น:</strong> ${data.education_level} ชั้นปีที่ ${data.class_year} กลุ่ม ${data.group_number}</p>
                    <p><strong>แผนก:</strong> ${data.department}</p>
                    <p><strong>เบอร์โทร:</strong> ${data.phone || '-'}</p>
                    <p><strong>อีเมล:</strong> ${data.email || '-'}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">ข้อมูลที่อยู่</h6>
                    <p>${addressHtml}</p>
                    
                    <h6 class="text-primary mt-3">ข้อมูลติดต่อฉุกเฉิน</h6>
                    <p><strong>ชื่อ:</strong> ${$('#emergency_contact_name').val() || '-'}</p>
                    <p><strong>เบอร์โทร:</strong> ${$('#emergency_contact_phone').val() || '-'}</p>
                    <p><strong>ความสัมพันธ์:</strong> ${$('#emergency_contact_relation').val() || '-'}</p>
                </div>
            </div>
        `;

                $('#summary-content').html(summaryHtml);
            }

            // ฟังก์ชันเปลี่ยนโหมดที่อยู่
            window.toggleAddressMode = function() {
                isCustomMode = !isCustomMode;

                if (isCustomMode) {
                    $('#select-mode').addClass('d-none');
                    $('#custom-mode').removeClass('d-none');
                    $('.address-toggle').html('<i class="bi bi-list-ul me-1"></i>เลือกจากรายการ');

                    // ล้าง required จาก select mode
                    $('#province_id, #district_id, #subdistrict_id').removeAttr('required');
                    // เพิ่ม required ให้ custom mode
                    $('#custom_province, #custom_district, #custom_subdistrict').attr('required', true);
                } else {
                    $('#select-mode').removeClass('d-none');
                    $('#custom-mode').addClass('d-none');
                    $('.address-toggle').html('<i class="bi bi-pencil-square me-1"></i>พิมพ์เอง');

                    // เพิ่ม required กลับมาที่ select mode
                    $('#subdistrict_id').attr('required', true);
                    // ล้าง required จาก custom mode
                    $('#custom_province, #custom_district, #custom_subdistrict').removeAttr('required');
                }
            };

            // เมื่อเลือกระดับชั้น
            $('#education_level').change(function() {
                let level = $(this).val();
                let classYearSelect = $('#class_year');

                classYearSelect.empty().prop('disabled', false);
                classYearSelect.append('<option value="" selected disabled>เลือก</option>');

                if (level === 'ปวช') {
                    for (let i = 1; i <= 3; i++) {
                        classYearSelect.append(`<option value="${i}">${i}</option>`);
                    }
                } else if (level === 'ปวส') {
                    for (let i = 1; i <= 2; i++) {
                        classYearSelect.append(`<option value="${i}">${i}</option>`);
                    }
                }
            });

            // เมื่อเลือกจังหวัด
            $('#province_id').change(function() {
                let provinceId = $(this).val();
                let districtSelect = $('#district_id');

                districtSelect.empty().prop('disabled', true);
                $('#subdistrict_id').empty().prop('disabled', true);

                if (provinceId) {
                    $.ajax({
                        url: '../api/get-districts.php',
                        type: 'GET',
                        data: {
                            province_id: provinceId
                        },
                        dataType: 'json',
                        success: function(data) {
                            districtSelect.prop('disabled', false);
                            districtSelect.append('<option value="" selected disabled>เลือกอำเภอ</option>');

                            $.each(data, function(index, item) {
                                districtSelect.append(`<option value="${item.id}">${item.district_name}</option>`);
                            });
                        }
                    });
                }
            });

            // เมื่อเลือกอำเภอ
            $('#district_id').change(function() {
                let districtId = $(this).val();
                let subdistrictSelect = $('#subdistrict_id');

                subdistrictSelect.empty().prop('disabled', true);

                if (districtId) {
                    $.ajax({
                        url: '../api/get-subdistricts.php',
                        type: 'GET',
                        data: {
                            district_id: districtId
                        },
                        dataType: 'json',
                        success: function(data) {
                            subdistrictSelect.prop('disabled', false);
                            subdistrictSelect.append('<option value="" selected disabled>เลือกตำบล</option>');

                            $.each(data, function(index, item) {
                                subdistrictSelect.append(`<option value="${item.id}">${item.subdistrict_name}</option>`);
                            });
                        }
                    });
                }
            });

            // Validation เรียลไทม์
            $('input, select').on('blur change', function() {
                if ($(this).attr('required') && !$(this).val()) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            // การตรวจสอบความถูกต้องของฟอร์ม
            $('#studentForm').on('submit', function(event) {
                if (!this.checkValidity() || !validateCurrentStep()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                $(this).addClass('was-validated');
            });

            // เริ่มต้นแสดง step แรก
            updateButtons();
        });
    </script>
</body>

</html>