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
    $village_id = (int)$_POST['village_id'];
    $phone = sanitize_input($_POST['phone'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    
    // ข้อมูลติดต่อฉุกเฉิน (เพิ่มเติม)
    $emergency_contact_name = sanitize_input($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = sanitize_input($_POST['emergency_contact_phone'] ?? '');
    $emergency_contact_relation = sanitize_input($_POST['emergency_contact_relation'] ?? '');
    
    // ข้อมูลสุขภาพ (เพิ่มเติม)
    $medical_conditions = sanitize_input($_POST['medical_conditions'] ?? '');
    $allergies = sanitize_input($_POST['allergies'] ?? '');
    $special_needs = sanitize_input($_POST['special_needs'] ?? '');
    
    // สร้างรหัสนักเรียน (ถ้าต้องการ)
    $student_code = generate_student_code($education_level, $department_id, $class_year);
    
    // บันทึกข้อมูลลงฐานข้อมูล
    try {
        $stmt = $conn->prepare("INSERT INTO students 
                               (student_code, prefix, first_name, last_name, education_level, 
                                class_year, group_number, department_id, house_number, 
                                village_number, village_id, phone, email,
                                emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                                medical_conditions, allergies, special_needs) 
                               VALUES 
                               (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                               
        $stmt->bind_param("sssssiiisiissssss", 
                          $student_code, $prefix, $first_name, $last_name, $education_level, 
                          $class_year, $group_number, $department_id, $house_number, 
                          $village_number, $village_id, $phone, $email,
                          $emergency_contact_name, $emergency_contact_phone, $emergency_contact_relation,
                          $medical_conditions, $allergies, $special_needs);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $alert = '<div class="alert alert-success">บันทึกข้อมูลเรียบร้อยแล้ว</div>';
            // ล้างฟอร์ม
            $_POST = array();
        } else {
            $alert = '<div class="alert alert-danger">เกิดข้อผิดพลาดในการบันทึกข้อมูล</div>';
        }
    } catch (Exception $e) {
        $alert = '<div class="alert alert-danger">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนข้อมูลนักเรียน-นักศึกษา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .form-label.required:after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1 class="text-center mb-4">ลงทะเบียนข้อมูลนักเรียน-นักศึกษา</h1>
        <h5 class="text-center mb-4 text-muted">สำหรับแผนฉุกเฉิน กรณีอพยพจากเหตุไม่สงบชายแดนไทยกัมพูชา</h5>
        
        <?php echo $alert; ?>
        
        <div class="card shadow">
            <div class="card-body">
                <form method="post" id="studentForm" class="needs-validation" novalidate>
                    
                    <h4 class="mb-3">ข้อมูลส่วนตัว</h4>
                    <div class="row mb-3">
                        <div class="col-md-2">
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
                        <div class="col-md-5">
                            <label for="first_name" class="form-label required">ชื่อ</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                            <div class="invalid-feedback">กรุณากรอกชื่อ</div>
                        </div>
                        <div class="col-md-5">
                            <label for="last_name" class="form-label required">นามสกุล</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                            <div class="invalid-feedback">กรุณากรอกนามสกุล</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="education_level" class="form-label required">ระดับชั้น</label>
                            <select class="form-select" id="education_level" name="education_level" required>
                                <option value="" selected disabled>เลือก</option>
                                <option value="ปวช">ปวช.</option>
                                <option value="ปวส">ปวส.</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกระดับชั้น</div>
                        </div>
                        <div class="col-md-4">
                            <label for="class_year" class="form-label required">ชั้นปี</label>
                            <select class="form-select" id="class_year" name="class_year" required disabled>
                                <option value="" selected disabled>เลือกระดับชั้นก่อน</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกชั้นปี</div>
                        </div>
                        <div class="col-md-4">
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
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="department_id" class="form-label required">แผนก/สาขาวิชา</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="" selected disabled>เลือก</option>
                                <?php while ($row = $departments->fetch_assoc()) : ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo $row['department_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกแผนก/สาขาวิชา</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" class="form-control" id="phone" name="phone" pattern="[0-9]{9,10}">
                            <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">อีเมล</label>
                            <input type="email" class="form-control" id="email" name="email">
                            <div class="invalid-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div>
                        </div>
                    </div>
                    
                    <h4 class="mb-3 mt-4">ข้อมูลที่อยู่</h4>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="house_number" class="form-label required">บ้านเลขที่</label>
                            <input type="text" class="form-control" id="house_number" name="house_number" required>
                            <div class="invalid-feedback">กรุณากรอกบ้านเลขที่</div>
                        </div>
                        <div class="col-md-4">
                            <label for="village_number" class="form-label">หมู่ที่</label>
                            <input type="number" class="form-control" id="village_number" name="village_number" min="1" max="99">
                        </div>
                        <div class="col-md-4">
                            <label for="province_id" class="form-label required">จังหวัด</label>
                            <select class="form-select" id="province_id" required>
                                <option value="" selected disabled>เลือก</option>
                                <?php while ($row = $provinces->fetch_assoc()) : ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo $row['province_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="district_id" class="form-label required">อำเภอ</label>
                            <select class="form-select" id="district_id" required disabled>
                                <option value="" selected disabled>เลือกจังหวัดก่อน</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกอำเภอ</div>
                        </div>
                        <div class="col-md-4">
                            <label for="subdistrict_id" class="form-label required">ตำบล</label>
                            <select class="form-select" id="subdistrict_id" required disabled>
                                <option value="" selected disabled>เลือกอำเภอก่อน</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกตำบล</div>
                        </div>
                        <div class="col-md-4">
                            <label for="village_id" class="form-label required">หมู่บ้าน</label>
                            <select class="form-select" id="village_id" name="village_id" required disabled>
                                <option value="" selected disabled>เลือกตำบลก่อน</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกหมู่บ้าน</div>
                        </div>
                    </div>
                    
                    <h4 class="mb-3 mt-4">ข้อมูลติดต่อฉุกเฉิน</h4>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="emergency_contact_name" class="form-label">ชื่อผู้ติดต่อฉุกเฉิน</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name">
                        </div>
                        <div class="col-md-3">
                            <label for="emergency_contact_phone" class="form-label">เบอร์โทรฉุกเฉิน</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" pattern="[0-9]{9,10}">
                        </div>
                        <div class="col-md-3">
                            <label for="emergency_contact_relation" class="form-label">ความสัมพันธ์</label>
                            <input type="text" class="form-control" id="emergency_contact_relation" name="emergency_contact_relation">
                        </div>
                    </div>
                    
                    <h4 class="mb-3 mt-4">ข้อมูลสุขภาพ (ถ้ามี)</h4>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="medical_conditions" class="form-label">โรคประจำตัว</label>
                            <input type="text" class="form-control" id="medical_conditions" name="medical_conditions">
                        </div>
                        <div class="col-md-4">
                            <label for="allergies" class="form-label">แพ้ยา/แพ้อาหาร</label>
                            <input type="text" class="form-control" id="allergies" name="allergies">
                        </div>
                        <div class="col-md-4">
                            <label for="special_needs" class="form-label">ความต้องการพิเศษ</label>
                            <input type="text" class="form-control" id="special_needs" name="special_needs">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 col-md-6 mx-auto mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // เริ่มต้น Select2
        $('#department_id, #province_id, #district_id, #subdistrict_id, #village_id').select2({
            theme: 'bootstrap-5'
        });
        
        // เมื่อเลือกระดับชั้น
        $('#education_level').change(function() {
            let level = $(this).val();
            let classYearSelect = $('#class_year');
            
            // ล้างและเปิดใช้งาน
            classYearSelect.empty().prop('disabled', false);
            classYearSelect.append('<option value="" selected disabled>เลือก</option>');
            
            // เพิ่มตัวเลือกตามระดับชั้น
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
            
            // ล้างและเปิดใช้งาน
            districtSelect.empty().prop('disabled', true);
            $('#subdistrict_id').empty().prop('disabled', true);
            $('#village_id').empty().prop('disabled', true);
            
            if (provinceId) {
                // AJAX ดึงข้อมูลอำเภอ
                $.ajax({
                    url: '../api/get-districts.php',
                    type: 'GET',
                    data: { province_id: provinceId },
                    dataType: 'json',
                    success: function(data) {
                        districtSelect.prop('disabled', false);
                        districtSelect.append('<option value="" selected disabled>เลือกอำเภอ</option>');
                        
                        $.each(data, function(index, item) {
                            districtSelect.append(`<option value="${item.id}">${item.district_name}</option>`);
                        });
                        
                        districtSelect.trigger('change');
                    }
                });
            }
        });
        
        // เมื่อเลือกอำเภอ
        $('#district_id').change(function() {
            let districtId = $(this).val();
            let subdistrictSelect = $('#subdistrict_id');
            
            // ล้างและเปิดใช้งาน
            subdistrictSelect.empty().prop('disabled', true);
            $('#village_id').empty().prop('disabled', true);
            
            if (districtId) {
                // AJAX ดึงข้อมูลตำบล
                $.ajax({
                    url: '../api/get-subdistricts.php',
                    type: 'GET',
                    data: { district_id: districtId },
                    dataType: 'json',
                    success: function(data) {
                        subdistrictSelect.prop('disabled', false);
                        subdistrictSelect.append('<option value="" selected disabled>เลือกตำบล</option>');
                        
                        $.each(data, function(index, item) {
                            subdistrictSelect.append(`<option value="${item.id}">${item.subdistrict_name}</option>`);
                        });
                        
                        subdistrictSelect.trigger('change');
                    }
                });
            }
        });
        
        // เมื่อเลือกตำบล
        $('#subdistrict_id').change(function() {
            let subdistrictId = $(this).val();
            let villageSelect = $('#village_id');
            
            // ล้างและเปิดใช้งาน
            villageSelect.empty().prop('disabled', true);
            
            if (subdistrictId) {
                // AJAX ดึงข้อมูลหมู่บ้าน
                $.ajax({
                    url: '../api/get-villages.php',
                    type: 'GET',
                    data: { subdistrict_id: subdistrictId },
                    dataType: 'json',
                    success: function(data) {
                        villageSelect.prop('disabled', false);
                        villageSelect.append('<option value="" selected disabled>เลือกหมู่บ้าน</option>');
                        
                        $.each(data, function(index, item) {
                            let villageName = item.village_name;
                            if (item.village_number) {
                                villageName += ` (หมู่ ${item.village_number})`;
                            }
                            villageSelect.append(`<option value="${item.id}">${villageName}</option>`);
                        });
                    }
                });
            }
        });
        
        // การตรวจสอบความถูกต้องของฟอร์ม
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    });
    </script>
</body>
</html>