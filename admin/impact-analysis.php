<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// ตรวจสอบการเข้าสู่ระบบ
check_admin_login();

// ดึงข้อมูลพื้นที่ได้รับผลกระทบ
$affected_areas_query = "
    SELECT aa.id, sd.subdistrict_name, d.district_name, p.province_name, 
           aa.impact_level, aa.impact_description, aa.start_date, aa.end_date,
           COUNT(DISTINCT s.id) as student_count,
           COUNT(DISTINCT s.id) * 100.0 / (SELECT COUNT(*) FROM students WHERE is_active = 1) as percentage
    FROM affected_areas aa
    JOIN subdistricts sd ON aa.subdistrict_id = sd.id
    JOIN districts d ON sd.district_id = d.id
    JOIN provinces p ON d.province_id = p.id
    LEFT JOIN villages v ON v.subdistrict_id = sd.id
    LEFT JOIN students s ON s.village_id = v.id AND s.is_active = 1
    WHERE aa.is_active = 1
    GROUP BY aa.id, sd.subdistrict_name, d.district_name, p.province_name, 
             aa.impact_level, aa.impact_description, aa.start_date, aa.end_date
    ORDER BY aa.impact_level, aa.start_date DESC
";
$affected_areas = $conn->query($affected_areas_query);

// เตรียมข้อมูลสำหรับการเพิ่มพื้นที่ได้รับผลกระทบ
$provinces = $conn->query("SELECT * FROM provinces ORDER BY province_name");
$impact_levels = ['สูง', 'ปานกลาง', 'ต่ำ'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิเคราะห์ผลกระทบ - ระบบสำรวจข้อมูลนักเรียน-นักศึกษา (แผนฉุกเฉิน)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
                    <h1 class="h2">วิเคราะห์ผลกระทบ</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAreaModal">
                            <i class="bi bi-plus-circle"></i> เพิ่มพื้นที่ได้รับผลกระทบ
                        </button>
                    </div>
                </div>

                <!-- สรุปข้อมูลผลกระทบ -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">ผลกระทบระดับสูง</h5>
                                <?php
                                $high_impact = $conn->query("
                                    SELECT COUNT(DISTINCT s.id) as student_count,
                                           COUNT(DISTINCT s.id) * 100.0 / (SELECT COUNT(*) FROM students WHERE is_active = 1) as percentage
                                    FROM affected_areas aa
                                    JOIN subdistricts sd ON aa.subdistrict_id = sd.id
                                    JOIN villages v ON v.subdistrict_id = sd.id
                                    JOIN students s ON s.village_id = v.id AND s.is_active = 1
                                    WHERE aa.is_active = 1 AND aa.impact_level = 'สูง'
                                ")->fetch_assoc();
                                ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h2><?php echo number_format($high_impact['student_count'] ?? 0); ?> คน</h2>
                                    <h4><?php echo number_format($high_impact['percentage'] ?? 0, 2); ?>%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">ผลกระทบระดับปานกลาง</h5>
                                <?php
                                $medium_impact = $conn->query("
                                    SELECT COUNT(DISTINCT s.id) as student_count,
                                           COUNT(DISTINCT s.id) * 100.0 / (SELECT COUNT(*) FROM students WHERE is_active = 1) as percentage
                                    FROM affected_areas aa
                                    JOIN subdistricts sd ON aa.subdistrict_id = sd.id
                                    JOIN villages v ON v.subdistrict_id = sd.id
                                    JOIN students s ON s.village_id = v.id AND s.is_active = 1
                                    WHERE aa.is_active = 1 AND aa.impact_level = 'ปานกลาง'
                                ")->fetch_assoc();
                                ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h2><?php echo number_format($medium_impact['student_count'] ?? 0); ?> คน</h2>
                                    <h4><?php echo number_format($medium_impact['percentage'] ?? 0, 2); ?>%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-dark">
                            <div class="card-body">
                                <h5 class="card-title">ผลกระทบระดับต่ำ</h5>
                                <?php
                                $low_impact = $conn->query("
                                    SELECT COUNT(DISTINCT s.id) as student_count,
                                           COUNT(DISTINCT s.id) * 100.0 / (SELECT COUNT(*) FROM students WHERE is_active = 1) as percentage
                                    FROM affected_areas aa
                                    JOIN subdistricts sd ON aa.subdistrict_id = sd.id
                                    JOIN villages v ON v.subdistrict_id = sd.id
                                    JOIN students s ON s.village_id = v.id AND s.is_active = 1
                                    WHERE aa.is_active = 1 AND aa.impact_level = 'ต่ำ'
                                ")->fetch_assoc();
                                ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h2><?php echo number_format($low_impact['student_count'] ?? 0); ?> คน</h2>
                                    <h4><?php echo number_format($low_impact['percentage'] ?? 0, 2); ?>%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ตารางพื้นที่ได้รับผลกระทบ -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">พื้นที่ได้รับผลกระทบ</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="impactTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>พื้นที่</th>
                                        <th>ระดับผลกระทบ</th>
                                        <th>จำนวนนักเรียน</th>
                                        <th>ร้อยละ</th>
                                        <th>วันที่เริ่ม</th>
                                        <th>วันที่สิ้นสุด</th>
                                        <th>รายละเอียด</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($area = $affected_areas->fetch_assoc()) : ?>
                                    <tr>
                                        <td>ต.<?php echo $area['subdistrict_name']; ?> อ.<?php echo $area['district_name']; ?> จ.<?php echo $area['province_name']; ?></td>
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
                                        <td class="text-end"><?php echo number_format($area['student_count']); ?> คน</td>
                                        <td class="text-end"><?php echo number_format($area['percentage'], 2); ?>%</td>
                                        <td><?php echo date('d/m/Y', strtotime($area['start_date'])); ?></td>
                                        <td><?php echo $area['end_date'] ? date('d/m/Y', strtotime($area['end_date'])) : '-'; ?></td>
                                        <td><?php echo mb_substr($area['impact_description'], 0, 50) . (mb_strlen($area['impact_description']) > 50 ? '...' : ''); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <a href="view_affected_students.php?area_id=<?php echo $area['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-list-ul"></i>
                                                </a>
                                                <a href="edit_affected_area.php?id=<?php echo $area['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAreaModal" data-id="<?php echo $area['id']; ?>">
                                                    <i class="bi bi-trash"></i>
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

                <!-- ศูนย์รับรอง -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">ศูนย์รับรอง</h5>
                        <a href="evacuation_centers.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> จัดการศูนย์รับรอง
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="centersTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ชื่อศูนย์</th>
                                        <th>ประเภท</th>
                                        <th>พื้นที่</th>
                                        <th class="text-end">ความจุ</th>
                                        <th class="text-end">จำนวนนักเรียนปัจจุบัน</th>
                                        <th class="text-end">คงเหลือ</th>
                                        <th class="text-center">อัตราการใช้งาน</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $centers_query = "
                                        SELECT ec.id, ec.center_name, ec.center_type, ec.capacity, 
                                               sd.subdistrict_name, d.district_name, p.province_name,
                                               COUNT(DISTINCT sa.student_id) as current_occupancy,
                                               ec.capacity - COUNT(DISTINCT sa.student_id) as available_capacity,
                                               (COUNT(DISTINCT sa.student_id) * 100.0 / ec.capacity) as occupancy_rate
                                        FROM evacuation_centers ec
                                        JOIN subdistricts sd ON ec.subdistrict_id = sd.id
                                        JOIN districts d ON sd.district_id = d.id
                                        JOIN provinces p ON d.province_id = p.id
                                        LEFT JOIN student_assignments sa ON ec.id = sa.evacuation_center_id AND sa.status IN ('assigned', 'confirmed')
                                        WHERE ec.is_available = 1
                                        GROUP BY ec.id, ec.center_name, ec.center_type, ec.capacity, 
                                                 sd.subdistrict_name, d.district_name, p.province_name
                                        ORDER BY occupancy_rate DESC
                                    ";
                                    $centers = $conn->query($centers_query);
                                    while ($center = $centers->fetch_assoc()) :
                                    ?>
                                    <tr>
                                        <td><?php echo $center['center_name']; ?></td>
                                        <td><?php echo $center['center_type']; ?></td>
                                        <td>ต.<?php echo $center['subdistrict_name']; ?> อ.<?php echo $center['district_name']; ?></td>
                                        <td class="text-end"><?php echo number_format($center['capacity']); ?> คน</td>
                                        <td class="text-end"><?php echo number_format($center['current_occupancy']); ?> คน</td>
                                        <td class="text-end"><?php echo number_format($center['available_capacity']); ?> คน</td>
                                        <td class="text-center">
                                            <div class="progress">
                                                <?php 
                                                $rate = $center['occupancy_rate'];
                                                $class = $rate > 80 ? 'bg-danger' : ($rate > 50 ? 'bg-warning' : 'bg-success');
                                                ?>
                                                <div class="progress-bar <?php echo $class; ?>" role="progressbar" style="width: <?php echo $rate; ?>%" aria-valuenow="<?php echo $rate; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($rate, 1); ?>%</div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <a href="view_center_students.php?center_id=<?php echo $center['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-list-ul"></i> รายชื่อนักเรียน
                                            </a>
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

    <!-- Modal เพิ่มพื้นที่ได้รับผลกระทบ -->
    <div class="modal fade" id="addAreaModal" tabindex="-1" aria-labelledby="addAreaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAreaModalLabel">เพิ่มพื้นที่ได้รับผลกระทบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addAreaForm" action="process_affected_area.php" method="post">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="province_id" class="form-label">จังหวัด</label>
                                <select class="form-select" id="province_id" name="province_id" required>
                                    <option value="" selected disabled>เลือก</option>
                                    <?php while ($row = $provinces->fetch_assoc()) : ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo $row['province_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="district_id" class="form-label">อำเภอ</label>
                                <select class="form-select" id="district_id" name="district_id" required disabled>
                                    <option value="" selected disabled>เลือกจังหวัดก่อน</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="subdistrict_id" class="form-label">ตำบล</label>
                                <select class="form-select" id="subdistrict_id" name="subdistrict_id" required disabled>
                                    <option value="" selected disabled>เลือกอำเภอก่อน</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="impact_level" class="form-label">ระดับผลกระทบ</label>
                                <select class="form-select" id="impact_level" name="impact_level" required>
                                    <option value="" selected disabled>เลือก</option>
                                    <?php foreach ($impact_levels as $level) : ?>
                                    <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">วันที่เริ่ม</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">วันที่สิ้นสุด (ถ้ามี)</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="impact_description" class="form-label">รายละเอียดผลกระทบ</label>
                            <textarea class="form-control" id="impact_description" name="impact_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ลบพื้นที่ได้รับผลกระทบ -->
    <div class="modal fade" id="deleteAreaModal" tabindex="-1" aria-labelledby="deleteAreaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAreaModalLabel">ยืนยันการลบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ต้องการลบพื้นที่ได้รับผลกระทบนี้ใช่หรือไม่?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <form action="delete_affected_area.php" method="post">
                        <input type="hidden" id="delete_area_id" name="id" value="">
                        <button type="submit" class="btn btn-danger">ลบ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // เริ่มต้น DataTables
        $('#impactTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
            }
        });
        
        $('#centersTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
            }
        });
        
        // เริ่มต้น Select2
        $('#province_id, #district_id, #subdistrict_id').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addAreaModal')
        });
        
        // เมื่อเลือกจังหวัด
        $('#province_id').change(function() {
            let provinceId = $(t