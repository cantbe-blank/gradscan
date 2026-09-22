<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = mysqli_prepare($conn, "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, photo FROM graduate WHERE school_id = ? AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)");
    $likeSearch = '%' . $search . '%';
    mysqli_stmt_bind_param($stmt, 'isss', $school_id, $likeSearch, $likeSearch, $likeSearch);
} else {
    $stmt = mysqli_prepare($conn, "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, photo FROM graduate WHERE school_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $school_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Graduate Management</title>
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
            <h1>Graduate Management</h1>
            <a href="add_graduate.php" class="btn btn-primary float-right">+ Add Graduate</a>
        </div>
        <div class="content">
            <form method="GET" class="form-inline mb-3">
                <input type="text" name="q" class="form-control mr-2" placeholder="Search by name or student ID" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-secondary mr-2">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="graduates.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
            <div class="card">
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Suffix</th>
                                <th>Course</th>
                                <th>Major</th>
                                <th>Photo</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['student_id']) ?></td>
                                <td><?= htmlspecialchars($row['first_name']) ?></td>
                                <td><?= htmlspecialchars($row['middle_name']) ?></td>
                                <td><?= htmlspecialchars($row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['suffix']) ?></td>
                                <td><?= htmlspecialchars($row['course']) ?></td>
                                <td><?= htmlspecialchars($row['major']) ?></td>
                                <td>
                                    <?php if ($row['photo']): ?>
                                        <img src="../<?= htmlspecialchars($row['photo']) ?>" width="50">
                                    <?php else: ?>
                                        No photo
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_graduate.php?id=<?= $row['graduate_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="delete_graduate.php?id=<?= $row['graduate_id'] ?>" class="btn btn-sm btn-danger">Delete</a>
                                </td>  
                            </tr>
                            <?php endwhile; ?>
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