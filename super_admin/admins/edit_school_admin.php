<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

// --- Step: Search Admin (by ID from the Edit link) ---
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    die('No account specified.');
}

$errors = [];
$success = false;

// --- Step: Account Found? ---
$stmt = mysqli_prepare($conn, "SELECT * FROM user WHERE user_id = ? AND role = 'school_admin'");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);

if (!$account) {
    // --- Step: Error ---
    die('Account not found.');
}

$schoolsResult = mysqli_query($conn, "SELECT school_id, school_name, school_code FROM school WHERE status = 'active'");

// --- Step: Edit Account / Save Changes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $school_id = $_POST['school_id'];
    $newPassword = $_POST['password'];

    if ($full_name === '' || $school_id === '') {
        $errors[] = 'Please fill out all required fields.';
    }

    if (empty($errors)) {
        if ($newPassword !== '') {
            // Password reset requested alongside the other changes
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = mysqli_prepare($conn, "UPDATE user SET full_name = ?, school_id = ?, password_hash = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($updateStmt, 'sisi', $full_name, $school_id, $passwordHash, $user_id);
        } else {
            $updateStmt = mysqli_prepare($conn, "UPDATE user SET full_name = ?, school_id = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($updateStmt, 'sii', $full_name, $school_id, $user_id);
        }
        mysqli_stmt_execute($updateStmt);

        $success = true;
        $account['full_name'] = $full_name;
        $account['school_id'] = $school_id;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Edit School Admin</title>
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
            <h1>Edit School Admin</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Changes saved successfully.
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

                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($account['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($account['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Assign School</label>
                            <select name="school_id" class="form-control">
                                <?php mysqli_data_seek($schoolsResult, 0); while ($s = mysqli_fetch_assoc($schoolsResult)): ?>
                                    <option value="<?= $s['school_id'] ?>" <?= $account['school_id'] == $s['school_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['school_name'] . ' (' . $s['school_code'] . ')') ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reset Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="this.disabled=true; this.form.submit();">Save Changes</button>
                        <a href="school_admins.php" class="btn btn-secondary">Cancel</a>
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
