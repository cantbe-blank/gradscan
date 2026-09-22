<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

// --- Step: Search School (by ID from the Edit link) ---
$school_id = $_GET['id'] ?? null;

if (!$school_id) {
    die('No school specified.');
}

$errors = [];
$success = false;

// --- Step: School Found? ---
$stmt = mysqli_prepare($conn, "SELECT * FROM school WHERE school_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$school = mysqli_fetch_assoc($result);

if (!$school) {
    // --- Step: Show Error ---
    die('School not found.');
}

// --- Step: Edit School / Save Changes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name']);
    $school_code = trim($_POST['school_code']);
    $status = $_POST['status'];

    if ($school_name === '' || $school_code === '') {
        $errors[] = 'Please fill out all fields.';
    }

    // Duplicate check, excluding this school's own current row
    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT school_id FROM school WHERE school_code = ? AND school_id != ?");
        mysqli_stmt_bind_param($checkStmt, 'si', $school_code, $school_id);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'Another school already uses this code.';
        }
    }

    // --- Step: Save Changes ---
    if (empty($errors)) {
        $updateStmt = mysqli_prepare($conn, "UPDATE school SET school_name = ?, school_code = ?, status = ? WHERE school_id = ?");
        mysqli_stmt_bind_param($updateStmt, 'sssi', $school_name, $school_code, $status, $school_id);
        mysqli_stmt_execute($updateStmt);

        $success = true;

        $school['school_name'] = $school_name;
        $school['school_code'] = $school_code;
        $school['status'] = $status;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Edit School</title>
    <link rel="stylesheet" href="../../AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../../AdminLTE-3.2.0/dist/css/adminlte.min.css">
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
                        <a href="super_admin_schools.php" class="nav-link active">
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
            <h1>Edit School</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Changes saved successfully.
                            <a href="super_admin_schools.php">Back to Manage Schools</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>School Name</label>
                            <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($school['school_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>School Code</label>
                            <input type="text" name="school_code" class="form-control" value="<?= htmlspecialchars($school['school_code']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= $school['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $school['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="this.disabled=true; this.form.submit();">Save Changes</button>
                        <a href="super_admin_schools.php" class="btn btn-secondary">Cancel</a>
                    </form>

                </div>
            </div>
        </div>
    </div>

</div>

<script src="../../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
