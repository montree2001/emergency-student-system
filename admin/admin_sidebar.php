<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky sidebar-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    แผงควบคุม
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>" href="students.php">
                    <i class="bi bi-people me-2"></i>
                    ข้อมูลนักเรียน-นักศึกษา
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-bar-chart-line me-2"></i>
                    รายงานสถิติ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'impact-analysis.php' ? 'active' : ''; ?>" href="impact-analysis.php">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    วิเคราะห์ผลกระทบ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'evacuation_centers.php' ? 'active' : ''; ?>" href="evacuation_centers.php">
                    <i class="bi bi-building me-2"></i>
                    ศูนย์รับรอง
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>การจัดการ</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <?php if ($_SESSION['admin_role'] == 'super_admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admins.php' ? 'active' : ''; ?>" href="admins.php">
                    <i class="bi bi-person-badge me-2"></i>
                    ผู้ดูแลระบบ
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>" href="backup.php">
                    <i class="bi bi-cloud-download me-2"></i>
                    สำรองข้อมูล
                </a>
            </li>
            <?php if ($_SESSION['admin_role'] == 'super_admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear me-2"></i>
                    ตั้งค่าระบบ
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : ''; ?>" href="activity_logs.php">
                    <i class="bi bi-clock-history me-2"></i>
                    ประวัติการใช้งาน
                </a>
            </li>
        </ul>
    </div>
</nav>