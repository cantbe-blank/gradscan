<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT user.*, school.school_name, school.school_code
    FROM user
    LEFT JOIN school ON user.school_id = school.school_id
    WHERE user.role = 'operator'
";

if ($search !== '') {
    $sql .= " AND (user.username LIKE ? OR user.full_name LIKE ?)";
    $likeSearch = '%' . $search . '%';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $likeSearch, $likeSearch);
} else {
    $stmt = mysqli_prepare($conn, $sql);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Manage Operators</title>
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
        <a href="../super_admin_dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">GradScan</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="../schools/super_admin_schools.php" class="nav-link">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Manage Schools</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admins/school_admins.php" class="nav-link">
                            <i class="nav-icon fas fa-user-tie"></i>
                            <p>Manage School Admins</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="operators.php" class="nav-link active">
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
            <h1>Manage Operators</h1>
        </div>
        <div class="content">

            <div class="d-flex justify-content-between mb-3">
                <form method="GET" class="form-inline">
                    <input type="text" name="q" class="form-control mr-2" placeholder="Search by username or name" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-secondary mr-2">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="operators.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </form>
                <a href="create_operator.php" class="btn btn-primary">+ Create Operator Account</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Assigned School</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr><td colspan="5" class="text-center">No operators found.</td></tr>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td><?= $row['school_name'] ? htmlspecialchars($row['school_name'] . ' (' . $row['school_code'] . ')') : '<em>Unassigned</em>' ?></td>
                                    <td>
                                        <span class="badge <?= $row['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_operator.php?id=<?= $row['user_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="../deactivate_account.php?id=<?= $row['user_id'] ?>" class="btn btn-sm btn-danger">
                                            <?= $row['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
                                        </a>
                                    </td>
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

<script src="../../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
