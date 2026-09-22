<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

// --- Step: Select School ---
$school_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$school_id) {
    die('No school specified.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM school WHERE school_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$school = mysqli_fetch_assoc($result);

if (!$school) {
    die('School not found.');
}

// --- Step: School has Records? (check for existing graduates in this department) ---
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM graduate WHERE school_id = ?");
mysqli_stmt_bind_param($countStmt, 'i', $school_id);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$graduateCount = mysqli_fetch_assoc($countResult)['total'];

$hasRecords = $graduateCount > 0;

// --- Step: Confirm Delete? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasRecords) {
    if ($_POST['confirm'] === 'yes') {
        // --- Step: Delete School ---
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM school WHERE school_id = ?");
        mysqli_stmt_bind_param($deleteStmt, 'i', $school_id);
        mysqli_stmt_execute($deleteStmt);

        header('Location: super_admin_schools.php?deleted=1');
        exit;
    } else {
        header('Location: super_admin_schools.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Delete School</title>
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
                        <a href="schools.php" class="nav-link active">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Manage Schools</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="school_admins.php" class="nav-link">
                            <i class="nav-icon fas fa-user-tie"></i>
                            <p>Manage School Admins</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="operators.php" class="nav-link">
                            <i class="nav-icon fas fa-user-cog"></i>
                            <p>Manage Operators</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
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
            <h1>Delete School</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <?php if ($hasRecords): ?>
                        <!-- --- Step: Cannot Delete --- -->
                        <div class="alert alert-danger">
                            <strong>Cannot delete this school.</strong>
                            It still has <?= $graduateCount ?> graduate record(s) attached.
                            Remove or reassign those records first.
                        </div>
                        <p><strong>School:</strong> <?= htmlspecialchars($school['school_name']) ?> (<?= htmlspecialchars($school['school_code']) ?>)</p>
                        <a href="super_admin_schools.php" class="btn btn-secondary">Back to Manage Schools</a>

                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>Are you sure you want to delete this school?</strong>
                            This action cannot be undone.
                        </div>
                        <p><strong>School:</strong> <?= htmlspecialchars($school['school_name']) ?> (<?= htmlspecialchars($school['school_code']) ?>)</p>

                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $school['school_id'] ?>">
                            <button type="submit" name="confirm" value="yes" class="btn btn-danger">Yes, Delete</button>
                            <button type="submit" name="confirm" value="no" class="btn btn-secondary">Cancel</button>
                        </form>
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
