<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// ตรวจสอบการเข้าสู่ระบบ
check_admin_login();

// ตรวจสอบพารามิเตอร์
if (!isset($_GET['area_id']) || !is_numeric($_GET['area_id'])) {
    header('Location: impact-analysis.php');
    exit;
}

$area_id = (int)$_GET['area_id'];

// ดึงข้อมูลพื้นที่
$area_query = "
    SELECT aa.*, sd.subdistrict_name, d.district_name, p.province_name 
    FROM affected_areas aa
    JOIN subdistricts sd ON aa.subdistrict_id = sd.id
    JOIN districts d ON sd.district_id = d.id
    JOIN provinces p ON d.province_id = p.id
    WHERE aa.id = ?
";

$stmt = $conn->prepare($area_query);
$stmt->bind_param("i", $area_id);
$stmt->execute();
$area = $stmt->get_result()->fetch_assoc();

if (!$area) {
    header('Location: impact-analysis.php');
    exit;
}

// ดึงข้อมูลนักเรียนในพื้นที่
$students_query = "
    SELECT s.id, s.student_code, CONCAT(s.prefix, s.first_name, ' ', s.last_name) as full_name,
           s.education_level, s.class_year, s.group_number, d.department_name,
           CONCAT(s.house_number, ' หมู่ ', s.village_number, ' ', v.village_name) as address,
           s.phone
    FROM students s
    JOIN departments d ON s.department_id = d.id
    JOIN villages v ON s.village_id = v.id
    JOIN subdistricts sd ON v.subdistrict_id = sd.id
    WHERE sd.id = ? AND s.is_active = 1
    ORDER BY s.education_level, s.class_year, s.group_number, s.first_name
";

$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $area['subdistrict_id']);
$stmt->execute();
$students = $stmt->get_result();

// ดึงข้อมูลสถิติ
$stats_query = "
    SELECT COUNT(*) as total_students,
           SUM(CASE WHEN s.education_level = 'ปวช' THEN 1 ELSE 0 END) as pvch_students,
           SUM(CASE WHEN s.education_level = 'ปวส' THEN 1 ELSE 0 END) as pvs_students,
           COUNT(*) * 100.0 / (SELECT COUNT(*) FROM students WHERE is_active = 1) as percentage
    FROM students s
    JOIN villages v ON s.village_id = v.id
    JOIN subdistricts sd ON v.subdistrict_id = sd.id
    WHERE sd.id = ? AND s.is_active = 1
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $area['subdistrict_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// ดึงข้อมูลตามแผนก
$dept_stats_query = "
    SELECT d.department_name,
           COUNT(*) as total,
           SUM(CASE WHEN s.education_level = 'ปวช' THEN 1 ELSE 0 END) as pvch,
           SUM(CASE WHEN s.education_level = 'ปวส' THEN 1 ELSE 0 END) as pvs
    FROM students s
    JOIN departments d ON s.department_id = d.id
    JOIN villages v ON s.village_id = v.id
    JOIN subdistricts sd ON v.subdistrict_id = sd.id
    WHERE sd.id = ? AND s.is_active = 1
    GROUP BY d.id, d.department_name
    ORDER BY total DESC
";

$stmt = $conn->prepare($dept_stats_query);
$stmt->bind_param("i", $area['subdistrict_id']);
$stmt->execute();
$dept_stats = $stmt->get_result();

// ดึงข้อมูลศูนย์รับรอง
$centers_query = "
    SELECT ec.id, ec.center_name, ec.capacity, ec.center_type,
           COUNT(DISTINCT sa.student_id) as current_occupancy,
           ec.capacity - COUNT(DISTINCT sa.student_id) as available_capacity
    FROM evacuation_centers ec
    LEFT JOIN student_assignments sa ON ec.id = sa.evacuation_center_id AND sa.status IN ('assigned', 'confirmed')
    WHERE ec.subdistrict_id = ? AND ec.is_available = 1
    GROUP BY ec.id, ec.center_name, ec.capacity, ec.center_type
";

$stmt = $conn->prepare($centers_query);
$stmt->bind_param("i", $area['subdistrict_id']);
$stmt->execute();
$centers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นักเรียน-นักศึกษาในพื้นที่ <?php echo $area['subdistrict_name']; ?> - ระบบสำรวจข้อมูลนักเรียน-นักศึกษา (แผนฉุกเฉิน)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .nav-link {
            font-weight: 500;
            color: #333;
        }
        .nav-link.active {
            color: #2470dc;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">นักเรียน-นักศึกษาในพื้นที่ <?php echo $area['subdistrict_name']; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">แผงควบคุม</a></li>
                                <li class="breadcrumb-item"><a href="impact-analysis.php">วิเคราะห์ผลกระทบ</a></li>
                                <li class="breadcrumb-item active" aria-current="page">ตำบล<?php echo $area['subdistrict_name']; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportExcel">
                                <i class="bi bi-file-earmark-excel"></i> ส่งออก Excel
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportPDF">
                                <i class="bi bi-file-earmark-pdf"></i> ส่งออก PDF
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="printTable">
                                <i class="bi bi-printer"></i> พิมพ์
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                                <i class="bi bi-building"></i> จัดสรรศูนย์รับรอง
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลพื้นที่ -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">ข้อมูลพื้นที่ได้รับผลกระทบ</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">พื้นที่:</th>
                                        <td>ตำบล<?php echo $area['subdistrict_name']; ?> อำเภอ<?php echo $area['district_name']; ?> จังหวัด<?php echo $area['province_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>ระดับผลกระทบ:</th>
                                        <td>
                                            <?php 
                                            $impact_class = '';
                                            switch ($area['impact_level']) {
                                                case 'สูง':
                                                    $impact_class = 'badge bg-danger';
                                                    break;
                                                case 'ปานกลาง':
                                                    $impact_class = 'badge bg-warning text-dark';
                                                    break;
                                                case 'ต่ำ':
                                                    $impact_class = 'badge bg-info text-dark';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $impact_class; ?>"><?php echo $area['impact_level']; ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>วันที่เริ่ม:</th>
                                        <td><?php echo date('d/m/Y', strtotime($area['start_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>วันที่สิ้นสุด:</th>
                                        <td><?php echo $area['end_date'] ? date('d/m/Y', strtotime($area['end_date'])) : '-'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">จำนวนนักเรียน-นักศึกษา:</th>
                                        <td><strong><?php echo number_format($stats['total_students']); ?></strong> คน</td>
                                    </tr>
                                    <tr>
                                        <th>ระดับ ปวช.:</th>
                                        <td><?php echo number_format($stats['pvch_students']); ?> คน</td>
                                    </tr>
                                    <tr>
                                        <th>ระดับ ปวส.:</th>
                                        <td><?php echo number_format($stats['pvs_students']); ?> คน</td>
                                    </tr>
                                    <tr>
                                        <th>คิดเป็นร้อยละ:</th>
                                        <td><?php echo number_format($stats['percentage'], 2); ?>% ของนักเรียนทั้งหมด</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($area['impact_description']): ?>
                        <div class="mt-3">
                            <h6>รายละเอียดผลกระทบ:</h6>
                            <p><?php echo nl2br($area['impact_description']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- สถิติตามแผนก -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">สถิติตามแผนก/สาขาวิชา</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>แผนก/สาขาวิชา</th>
                                                <th class="text-end">ปวช.</th>
                                                <th class="text-end">ปวส.</th>
                                                <th class="text-end">รวม</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($dept = $dept_stats->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $dept['department_name']; ?></td>
                                                <td class="text-end"><?php echo number_format($dept['pvch']); ?></td>
                                                <td class="text-end"><?php echo number_format($dept['pvs']); ?></td>
                                                <td class="text-end"><strong><?php echo number_format($dept['total']); ?></strong></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">ศูนย์รับรองในพื้นที่</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($centers->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>ชื่อศูนย์</th>
                                                <th>ประเภท</th>
                                                <th class="text-end">ความจุ</th>
                                                <th class="text-end">ใช้งานแล้ว</th>
                                                <th class="text-end">คงเหลือ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($center = $centers->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $center['center_name']; ?></td>
                                                <td><?php echo $center['center_type']; ?></td>
                                                <td class="text-end"><?php echo number_format($center['capacity']); ?></td>
                                                <td class="text-end"><?php echo number_format($center['current_occupancy']); ?></td>
                                                <td class="text-end"><?php echo number_format($center['available_capacity']); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    ไม่พบศูนย์รับรองในพื้นที่ตำบล<?php echo $area['subdistrict_name']; ?>
                                    <a href="evacuation_centers.php" class="alert-link">เพิ่มศูนย์รับรอง</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ตารางรายชื่อนักเรียน -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">รายชื่อนักเรียน-นักศึกษาในพื้นที่</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="studentsTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>รหัส</th>
                                        <th>ชื่อ-สกุล</th>
                                        <th>ระดับชั้น</th>
                                        <th>แผนก/สาขาวิชา</th>
                                        <th>ที่อยู่</th>
                                        <th>เบอร์โทรศัพท์</th>
                                        <th>ศูนย์รับรอง</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): 
                                        // ดึงข้อมูลศูนย์รับรองที่นักเรียนอยู่
                                        $center_query = "
                                            SELECT ec.center_name, sa.status
                                            FROM student_assignments sa
                                            JOIN evacuation_centers ec ON sa.evacuation_center_id = ec.id
                                            WHERE sa.student_id = ? AND sa.status IN ('assigned', 'confirmed')
                                            LIMIT 1
                                        ";
                                        $stmt = $conn->prepare($center_query);
                                        $stmt->bind_param("i", $student['id']);
                                        $stmt->execute();
                                        $center_result = $stmt->get_result();
                                        $center_info = $center_result->fetch_assoc();
                                    ?>
                                    <tr>
                                        <td><?php echo $student['student_code']; ?></td>
                                        <td><?php echo $student['full_name']; ?></td>
                                        <td><?php echo $student['education_level'] . ' ' . $student['class_year'] . '/' . $student['group_number']; ?></td>
                                        <td><?php echo $student['department_name']; ?></td>
                                        <td><?php echo $student['address']; ?></td>
                                        <td><?php echo $student['phone'] ?: '-'; ?></td>
                                        <td>
                                            <?php if ($center_info): ?>
                                                <?php echo $center_info['center_name']; ?>
                                                <?php if ($center_info['status'] == 'confirmed'): ?>
                                                    <span class="badge bg-success">ยืนยันแล้ว</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">ยังไม่ได้กำหนด</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <a href="student_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-info-circle"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-primary assign-btn" data-bs-toggle="modal" data-bs-target="#assignStudentModal" data-id="<?php echo $student['id']; ?>" data-name="<?php echo $student['full_name']; ?>">
                                                    <i class="bi bi-building"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal จัดสรรศูนย์รับรองแบบกลุ่ม -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalLabel">จัดสรรศูนย์รับรองแบบกลุ่ม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assignForm" action="process_bulk_assign.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="subdistrict_id" value="<?php echo $area['subdistrict_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="center_id" class="form-label">เลือกศูนย์รับรอง</label>
                            <select class="form-select" id="center_id" name="center_id" required>
                                <option value="" selected disabled>เลือกศูนย์รับรอง</option>
                                <?php 
                                $centers->data_seek(0);
                                while ($center = $centers->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $center['id']; ?>"><?php echo $center['center_name']; ?> (คงเหลือ <?php echo $center['available_capacity']; ?> คน)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">เลือกนักเรียนตามเงื่อนไข</label>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="filter_education" class="form-label">ระดับชั้น</label>
                                            <select class="form-select" id="filter_education" name="filter_education">
                                                <option value="">ทั้งหมด</option>
                                                <option value="ปวช">ปวช</option>
                                                <option value="ปวส">ปวส</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="filter_year" class="form-label">ชั้นปี</label>
                                            <select class="form-select" id="filter_year" name="filter_year">
                                                <option value="">ทั้งหมด</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="filter_department" class="form-label">แผนก/สาขาวิชา</label>
                                            <select class="form-select" id="filter_department" name="filter_department">
                                                <option value="">ทั้งหมด</option>
                                                <?php 
                                                $departments = $conn->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
                                                while ($dept = $departments->fetch_assoc()): 
                                                ?>
                                                <option value="<?php echo $dept['id']; ?>"><?php echo $dept['department_name']; ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="only_unassigned" name="only_unassigned" value="1" checked>
                                <label class="form-check-label" for="only_unassigned">
                                    เฉพาะนักเรียนที่ยังไม่ได้กำหนดศูนย์รับรอง
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            ระบบจะจัดสรรนักเรียนตามเงื่อนไขที่เลือกไปยังศูนย์รับรองที่กำหนด โดยจะส่งข้อความแจ้งเตือนให้นักเรียนทราบโดยอัตโนมัติ
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">ยืนยันการจัดสรร</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal จัดสรรศูนย์รับรองรายบุคคล -->
    <div class="modal fade" id="assignStudentModal" tabindex="-1" aria-labelledby="assignStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignStudentModalLabel">จัดสรรศูนย์รับรอง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assignStudentForm" action="process_assign_student.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" id="student_id" name="student_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">นักเรียน-นักศึกษา</label>
                            <input type="text" class="form-control" id="student_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="single_center_id" class="form-label">เลือกศูนย์รับรอง</label>
                            <select class="form-select" id="single_center_id" name="center_id" required>
                                <option value="" selected disabled>เลือกศูนย์รับรอง</option>
                                <?php 
                                $centers->data_seek(0);
                                while ($center = $centers->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $center['id']; ?>"><?php echo $center['center_name']; ?> (คงเหลือ <?php echo $center['available_capacity']; ?> คน)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">หมายเหตุ (ถ้ามี)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">ยืนยัน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // เริ่มต้น DataTables
        var table = $('#studentsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
            },
            order: [[2, 'asc'], [1, 'asc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]]
        });
        
        // ปุ่มส่งออก Excel
        $('#exportExcel').on('click', function() {
            window.location.href = 'export_affected_students.php?area_id=<?php echo $area_id; ?>&format=excel';
        });
        
        // ปุ่มส่งออก PDF
        $('#exportPDF').on('click', function() {
            window.location.href = 'export_affected_students.php?area_id=<?php echo $area_id; ?>&format=pdf';
        });
        
        // ปุ่มพิมพ์
        $('#printTable').on('click', function() {
            window.open('print_affected_students.php?area_id=<?php echo $area_id; ?>', '_blank');
        });
        
        // เมื่อเปิด Modal จัดสรรรายบุคคล
        $('.assign-btn').on('click', function() {
            var studentId = $(this).data('id');
            var studentName = $(this).data('name');
            
            $('#student_id').val(studentId);
            $('#student_name').val(studentName);
        });
    });
    </script>
</body>
</html>