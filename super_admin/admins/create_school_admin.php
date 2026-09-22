<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

$errors = [];
$success = false;

// --- Load schools for the Assign School dropdown ---
$schoolsResult = mysqli_query($conn, "SELECT school_id, school_name, school_code FROM school WHERE status = 'active'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $school_id = $_POST['school_id'];

    // --- Step: Information Complete? ---
    if ($full_name === '' || $username === '' || $password === '' || $school_id === '') {
        $errors[] = 'Please fill out all fields.';
    }

    // --- Step: Username Exists? ---
    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM user WHERE username = ?");
        mysqli_stmt_bind_param($checkStmt, 's', $username);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'This username is already taken.';
        }
    }

    // --- Step: Save Account ---
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insertStmt = mysqli_prepare($conn, "INSERT INTO user (school_id, username, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'school_admin')");
        mysqli_stmt_bind_param($insertStmt, 'isss', $school_id, $username, $passwordHash, $full_name);
        mysqli_stmt_execute($insertStmt);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Create School Admin</title>
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
                <a class="nav-link" href="../../logout.php">Logout</a>
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
                        <a href="super_admin_schools.php" class="nav-link">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Manage Schools</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="school_admins.php" class="nav-link active">
                            <i class="nav-icon fas fa-user-tie"></i>
                            <p>Manage School Admins</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../operators/operators.php" class="nav-link">
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
            <h1>Create School Admin Account</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            School admin account created successfully.
                            <a href="school_admins.php">Back to Manage School Admins</a>
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

                    <?php if (!$success): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Assign School</label>
                            <select name="school_id" class="form-control">
                                <option value="">-- Select a School --</option>
                                <?php mysqli_data_seek($schoolsResult, 0); while ($s = mysqli_fetch_assoc($schoolsResult)): ?>
                                    <option value="<?= $s['school_id'] ?>" <?= (($_POST['school_id'] ?? '') == $s['school_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['school_name'] . ' (' . $s['school_code'] . ')') ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="this.disabled=true; this.form.submit();">Save Account</button>
                        <a href="school_admins.php" class="btn btn-secondary">Cancel</a>
                    </form>
                    <?php endif; ?>

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
