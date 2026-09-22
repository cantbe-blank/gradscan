<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Super Admin Dashboard</title>
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
            <h1>Super Admin Dashboard</h1>
        </div>
        <div class="content">
            <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>. Select a module from the sidebar to get started.</p>
        </div>
    </div>

</div>

<script src="../AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="../AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
