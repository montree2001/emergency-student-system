<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="dashboard.php">
        ระบบสำรวจนักเรียนฉุกเฉิน
    </a>
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <?php 
    // ดึงข้อมูลสถานศึกษา
    $school_name = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'school_name'")->fetch_assoc()['setting_value'] ?? 'วิทยาลัยเทคนิคหนองกี่';
    ?>
    <span class="text-light d-none d-md-block mx-auto"><?php echo $school_name; ?></span>
    <div class="navbar-nav">
        <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="logout.php">ออกจากระบบ <i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</header>