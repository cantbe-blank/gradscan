<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';
$school_id = $_SESSION['school_id'];

// --- Step: Select Graduate ---
$graduate_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$graduate_id) {
    die('No graduate specified.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM graduate WHERE graduate_id = ? AND school_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $graduate_id, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$graduate = mysqli_fetch_assoc($result);

if (!$graduate) {
    die('Graduate not found, or does not belong to your department.');
}

// --- Step: Confirm Delete? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['confirm'] === 'yes') {
        // --- Step: Delete Record / Update DB ---
        // qr_code rows for this graduate are removed automatically (ON DELETE CASCADE)
        // scan_log rows referencing those qr_codes keep their history (qr_id set to NULL)
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM graduate WHERE graduate_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($deleteStmt, 'ii', $graduate_id, $school_id);
        mysqli_stmt_execute($deleteStmt);

        header('Location: graduates.php?deleted=1');
        exit;
    } else {
        // --- No: back to Graduate Management ---
        header('Location: graduates.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Delete Graduate</title>
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
                        <a href="graduates.php" class="nav-link active">
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
                        <a href="scan_history.php" class="nav-link">
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
            <h1>Delete Graduate</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <div class="alert alert-warning">
                        <strong>Are you sure you want to delete this graduate?</strong>
                        This will also permanently remove their QR code. This action cannot be undone.
                    </div>

                    <table class="table table-bordered w-auto">
                        <tr>
                            <th>Student ID</th>
                            <td><?= htmlspecialchars($graduate['student_id']) ?></td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td><?= htmlspecialchars($graduate['first_name'] . ' ' . $graduate['middle_name'] . ' ' . $graduate['last_name'] . ' ' . $graduate['suffix']) ?></td>
                        </tr>
                        <tr>
                            <th>Course</th>
                            <td><?= htmlspecialchars($graduate['course']) ?></td>
                        </tr>
                        <tr>
                            <th>Major</th>
                            <td><?= htmlspecialchars($graduate['major']) ?></td>
                        </tr>
                    </table>

                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $graduate['graduate_id'] ?>">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger">Yes, Delete</button>
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
