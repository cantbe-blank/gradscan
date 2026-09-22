<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

// --- Step: Select Report ---
$reportType = $_GET['type'] ?? 'schools';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | View Reports</title>
    <link rel="stylesheet" href="../AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link"><?= $_SESSION['full_name'] ?> (Super Admin)</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="super_admin_dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="schools/super_admin_schools.php" class="nav-link">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Manage Schools</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admins/school_admins.php" class="nav-link">
                            <i class="nav-icon fas fa-user-tie"></i>
                            <p>Manage School Admins</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="operators/operators.php" class="nav-link">
                            <i class="nav-icon fas fa-user-cog"></i>
                            <p>Manage Operators</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link active">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>View Reports</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <h1>View Reports</h1>
        </div>
        <div class="content">

            <!-- --- Step: Select Report --- -->
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link <?= $reportType === 'schools' ? 'active' : '' ?>" href="reports.php?type=schools">
                        <i class="fas fa-building"></i> Schools Report
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $reportType === 'scan_logs' ? 'active' : '' ?>" href="reports.php?type=scan_logs">
                        <i class="fas fa-history"></i> Scan Logs Report
                    </a>
                </li>
            </ul>

            <div class="card">
                <div class="card-body">

                    <?php if ($reportType === 'schools'): ?>
                        <!-- --- Step: Display Records (Schools) --- -->
                        <?php
                        $sql = "
                            SELECT
                                school.school_name,
                                school.school_code,
                                school.status,
                                COUNT(DISTINCT graduate.graduate_id) AS graduate_count,
                                COUNT(DISTINCT CASE WHEN user.role = 'school_admin' THEN user.user_id END) AS admin_count,
                                COUNT(DISTINCT CASE WHEN user.role = 'operator' THEN user.user_id END) AS operator_count
                            FROM school
                            LEFT JOIN graduate ON graduate.school_id = school.school_id
                            LEFT JOIN user ON user.school_id = school.school_id
                            GROUP BY school.school_id
                            ORDER BY school.school_name
                        ";
                        $result = mysqli_query($conn, $sql);
                        ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>School Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Graduates</th>
                                    <th>School Admins</th>
                                    <th>Operators</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) === 0): ?>
                                    <tr><td colspan="6" class="text-center">No schools found.</td></tr>
                                <?php else: ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['school_name']) ?></td>
                                        <td><?= htmlspecialchars($row['school_code']) ?></td>
                                        <td>
                                            <span class="badge <?= $row['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= $row['graduate_count'] ?></td>
                                        <td><?= $row['admin_count'] ?></td>
                                        <td><?= $row['operator_count'] ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <!-- --- Step: Display Records (Scan Logs, campus-wide) --- -->
                        <?php
                        $sql = "
                            SELECT
                                scan_log.scan_status,
                                scan_log.scanned_at,
                                graduate.student_id,
                                graduate.first_name,
                                graduate.last_name,
                                school.school_name,
                                user.full_name AS operator_name
                            FROM scan_log
                            LEFT JOIN qr_code ON scan_log.qr_id = qr_code.qr_id
                            LEFT JOIN graduate ON qr_code.graduate_id = graduate.graduate_id
                            LEFT JOIN school ON graduate.school_id = school.school_id
                            LEFT JOIN user ON scan_log.operator_id = user.user_id
                            ORDER BY scan_log.scanned_at DESC
                        ";
                        $result = mysqli_query($conn, $sql);
                        ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Graduate Name</th>
                                    <th>School</th>
                                    <th>Status</th>
                                    <th>Scanned At</th>
                                    <th>Operator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) === 0): ?>
                                    <tr><td colspan="6" class="text-center">No scan records found.</td></tr>
                                <?php else: ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['student_id'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($row['school_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = match($row['scan_status']) {
                                                'success' => 'badge-success',
                                                'used' => 'badge-warning',
                                                'expired' => 'badge-secondary',
                                                'invalidated' => 'badge-danger',
                                                'not_found' => 'badge-dark',
                                                default => 'badge-light',
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['scan_status']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['scanned_at']) ?></td>
                                        <td><?= htmlspecialchars($row['operator_name'] ?? 'Unknown') ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

</div>

<script src="../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
