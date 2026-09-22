<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '../../config/config.php';

// --- Step: Select Admin/Operator ---
$user_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$user_id) {
    die('No account specified.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM user WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);

if (!$account) {
    die('Account not found.');
}

$newStatus = $account['status'] === 'active' ? 'inactive' : 'active';
$actionLabel = $account['status'] === 'active' ? 'Deactivate' : 'Reactivate';

// where to redirect back to, depending on which module linked here
$returnPage = $account['role'] === 'operator' ? 'operators/operators.php' : 'admins/school_admins.php';

// --- Step: Confirm Deactivation? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['confirm'] === 'yes') {
        // --- Step: Change Status to Inactive (or back to Active) ---
        $updateStmt = mysqli_prepare($conn, "UPDATE user SET status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $user_id);
        mysqli_stmt_execute($updateStmt);

        header('Location: ' . $returnPage);
        exit;
    } else {
        // --- Step: Cancel ---
        header('Location: ' . $returnPage);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | <?= $actionLabel ?> Account</title>
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
        <a href="dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="super_admin_schools.php" class="nav-link">
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
            <h1><?= $actionLabel ?> Account</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <div class="alert alert-warning">
                        <strong>Are you sure you want to <?= strtolower($actionLabel) ?> this account?</strong>
                    </div>

                    <table class="table table-bordered w-auto">
                        <tr>
                            <th>Username</th>
                            <td><?= htmlspecialchars($account['username']) ?></td>
                        </tr>
                        <tr>
                            <th>Full Name</th>
                            <td><?= htmlspecialchars($account['full_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td><?= htmlspecialchars($account['role']) ?></td>
                        </tr>
                        <tr>
                            <th>Current Status</th>
                            <td><?= htmlspecialchars($account['status']) ?></td>
                        </tr>
                    </table>

                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $account['user_id'] ?>">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger">Yes, <?= $actionLabel ?></button>
                        <button type="submit" name="confirm" value="no" class="btn btn-secondary">Cancel</button>
                    </form>

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
