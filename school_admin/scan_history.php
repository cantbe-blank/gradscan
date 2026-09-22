<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';
$school_id = $_SESSION['school_id'];

// --- Step: Search/Filter? ---
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

// --- Step: Retrieve Scan Records ---
// Joined through qr_code -> graduate so we can filter to this department's scans only.
// LEFT JOINs matter here: qr_id and operator_id can be NULL (a QR code or user account
// may have been deleted since the scan happened), and we still want that history to show.
$sql = "
    SELECT
        scan_log.scan_id,
        scan_log.scan_status,
        scan_log.scanned_at,
        graduate.student_id,
        graduate.first_name,
        graduate.last_name,
        user.full_name AS operator_name
    FROM scan_log
    LEFT JOIN qr_code ON scan_log.qr_id = qr_code.qr_id
    LEFT JOIN graduate ON qr_code.graduate_id = graduate.graduate_id
    LEFT JOIN user ON scan_log.operator_id = user.user_id
    WHERE graduate.school_id = ?
";

$params = [$school_id];
$types = 'i';

if ($statusFilter !== '') {
    $sql .= " AND scan_log.scan_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($dateFrom !== '') {
    $sql .= " AND scan_log.scanned_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}

if ($dateTo !== '') {
    $sql .= " AND scan_log.scanned_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$sql .= " ORDER BY scan_log.scanned_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Scan History</title>
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
                <span class="nav-link"><?= $_SESSION['full_name'] ?> (<?= $_SESSION['role'] ?>)</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="graduates.php" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Graduate Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="layouts.php" class="nav-link">
                            <i class="nav-icon fas fa-desktop"></i>
                            <p>Layout Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="scan_history.php" class="nav-link active">
                            <i class="nav-icon fas fa-history"></i>
                            <p>Scan History</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <h1>Scan History</h1>
        </div>
        <div class="content">

            <!-- --- Step: Search/Filter? / Apply Filter --- -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="form-inline">
                        <div class="form-group mr-2">
                            <label class="mr-2">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                                <option value="used" <?= $statusFilter === 'used' ? 'selected' : '' ?>>Used</option>
                                <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                                <option value="invalidated" <?= $statusFilter === 'invalidated' ? 'selected' : '' ?>>Invalidated</option>
                            </select>
                        </div>
                        <div class="form-group mr-2">
                            <label class="mr-2">From</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="form-group mr-2">
                            <label class="mr-2">To</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <button type="submit" class="btn btn-secondary mr-2">Apply Filter</button>
                        <a href="scan_history.php" class="btn btn-outline-secondary">Clear</a>
                    </form>
                </div>
            </div>

            <!-- --- Step: Display Scan Records --- -->
            <div class="card">
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Graduate Name</th>
                                <th>Status</th>
                                <th>Scanned At</th>
                                <th>Operator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No scan records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['student_id'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match($row['scan_status']) {
                                            'success' => 'badge-success',
                                            'used' => 'badge-warning',
                                            'expired' => 'badge-secondary',
                                            'invalidated' => 'badge-danger',
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
