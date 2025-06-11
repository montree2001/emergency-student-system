<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// ตรวจสอบการเข้าสู่ระบบ
check_admin_login();

// ดึงข้อมูลสถิตินักเรียน
$students_stats = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_active = 1")->fetch_assoc();
$students_pvch = $conn->query("SELECT COUNT(*) as total FROM students WHERE education_level = 'ปวช' AND is_active = 1")->fetch_assoc();
$students_pvs = $conn->query("SELECT COUNT(*) as total FROM students WHERE education_level = 'ปวส' AND is_active = 1")->fetch_assoc();

// ดึงข้อมูลแผนก
$departments_stats = $conn->query("SELECT * FROM v_department_statistics ORDER BY total_students DESC");

// ดึงข้อมูลพื้นที่ได้รับผลกระทบ
$affected_areas_query = "
    SELECT a.impact_level, COUNT(DISTINCT s.id) as student_count,
           COUNT(DISTINCT s.id) * 100.0 / (SELECT COUNT(*) FROM students WHERE is_active = 1) as percentage
    FROM affected_areas a
    JOIN subdistricts sd ON a.subdistrict_id = sd.id
    JOIN villages v ON v.subdistrict_id = sd.id
    JOIN students s ON s.village_id = v.id AND s.is_active = 1
    WHERE a.is_active = 1
    GROUP BY a.impact_level
    ORDER BY FIELD(a.impact_level, 'สูง', 'ปานกลาง', 'ต่ำ')
";
$affected_areas_stats = $conn->query($affected_areas_query);

// ดึงข้อมูลศูนย์รับรอง
$centers_query = "
    SELECT ec.id, ec.center_name, ec.capacity, 
           COUNT(DISTINCT sa.student_id) as current_occupancy,
           ec.capacity - COUNT(DISTINCT sa.student_id) as available_capacity,
           (COUNT(DISTINCT sa.student_id) * 100.0 / ec.capacity) as occupancy_rate
    FROM evacuation_centers ec
    LEFT JOIN student_assignments sa ON ec.id = sa.evacuation_center_id AND sa.status IN ('assigned', 'confirmed')
    WHERE ec.is_available = 1
    GROUP BY ec.id
    ORDER BY occupancy_rate DESC
";
$centers_stats = $conn->query($centers_query);

// ดึงข้อมูลล่าสุด
$recent_students = $conn->query("SELECT * FROM v_students_full_address WHERE is_active = 1 ORDER BY id DESC LIMIT 5");
$recent_affected = $conn->query("
    SELECT aa.*, sd.subdistrict_name, d.district_name, p.province_name, a.full_name as created_by_name
    FROM affected_areas aa
    JOIN subdistricts sd ON aa.subdistrict_id = sd.id
    JOIN districts d ON sd.district_id = d.id
    JOIN provinces p ON d.province_id = p.id
    LEFT JOIN admins a ON aa.created_by = a.id
    WHERE aa.is_active = 1
    ORDER BY aa.created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผงควบคุม - ระบบสำรวจข้อมูลนักเรียน-นักศึกษา (แผนฉุกเฉิน)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
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
                    <h1 class="h2">แผงควบคุม</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-file-earmark-excel"></i> ส่งออก Excel
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-file-earmark-pdf"></i> ส่งออก PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- สรุปข้อมูลนักเรียน -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">นักเรียน-นักศึกษาทั้งหมด</h5>
                                        <h2 class="display-4"><?php echo number_format($students_stats['total']); ?></h2>
                                    </div>
                                    <i class="bi bi-people-fill display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">ระดับ ปวช.</h5>
                                        <h2 class="display-4"><?php echo number_format($students_pvch['total']); ?></h2>
                                    </div>
                                    <i class="bi bi-mortarboard-fill display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">ระดับ ปวส.</h5>
                                        <h2 class="display-4"><?php echo number_format($students_pvs['total']); ?></h2>
                                    </div>
                                    <i class="bi bi-mortarboard-fill display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- กราฟและตาราง -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">สัดส่วนนักเรียนแต่ละแผนก</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="departmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">สัดส่วนผลกระทบตามระดับความรุนแรง</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="impactChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ศูนย์รับรอง -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">ศูนย์รับรอง</h5>
                                <a href="evacuation_centers.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ชื่อศูนย์</th>
                                                <th class="text-end">ความจุ</th>
                                                <th class="text-end">จำนวนนักเรียนปัจจุบัน</th>
                                                <th class="text-end">คงเหลือ</th>
                                                <th class="text-center">อัตราการใช้งาน</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($center = $centers_stats->fetch_assoc()) : ?>
                                            <tr>
                                                <td><?php echo $center['center_name']; ?></td>
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
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลล่าสุด -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">ข้อมูลนักเรียนล่าสุด</h5>
                                <a href="students.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>ชื่อ-สกุล</th>
                                                <th>แผนก</th>
                                                <th>ระดับชั้น</th>
                                                <th>พื้นที่</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($student = $recent_students->fetch_assoc()) : ?>
                                            <tr>
                                                <td><?php echo $student['full_name']; ?></td>
                                                <td><?php echo $student['department_name']; ?></td>
                                                <td><?php echo $student['education_level'] . ' ' . $student['class_year'] . '/' . $student['group_number']; ?></td>
                                                <td>
                                                    <?php 
                                                    $address_parts = explode(' ', $student['full_address']);
                                                    $district = array_search('อำเภอ', $address_parts);
                                                    $province = array_search('จังหวัด', $address_parts);
                                                    echo $address_parts[$district + 1] . ', ' . $address_parts[$province + 1];
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">พื้นที่ได้รับผลกระทบล่าสุด</h5>
                                <a href="affected_areas.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>พื้นที่</th>
                                                <th>ระดับผลกระทบ</th>
                                                <th>วันที่เริ่ม</th>
                                                <th>บันทึกโดย</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($area = $recent_affected->fetch_assoc()) : ?>
                                            <tr>
                                                <td>ต.<?php echo $area['subdistrict_name']; ?> อ.<?php echo $area['district_name']; ?></td>
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
                                                <td><?php echo date('d/m/Y', strtotime($area['start_date'])); ?></td>
                                                <td><?php echo $area['created_by_name'] ?: 'ระบบ'; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // กราฟแผนก
    const deptCtx = document.getElementById('departmentChart').getContext('2d');
    const departmentChart = new Chart(deptCtx, {
        type: 'pie',
        data: {
            labels: [
                <?php 
                $departments_stats->data_seek(0);
                while ($dept = $departments_stats->fetch_assoc()) {
                    echo '"' . $dept['department_name'] . '",';
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    $departments_stats->data_seek(0);
                    while ($dept = $departments_stats->fetch_assoc()) {
                        echo $dept['total_students'] . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(83, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });

    // กราฟผลกระทบ
    const impactCtx = document.getElementById('impactChart').getContext('2d');
    const impactChart = new Chart(impactCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                $affected_areas_stats->data_seek(0);
                while ($impact = $affected_areas_stats->fetch_assoc()) {
                    echo '"' . $impact['impact_level'] . '",';
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    $affected_areas_stats->data_seek(0);
                    while ($impact = $affected_areas_stats->fetch_assoc()) {
                        echo $impact['student_count'] . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    </script>
</body>
</html>