<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;

require_once __DIR__ . '/../config/config.php';
$school_id = $_SESSION['school_id'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id  = trim($_POST['student_id']);
    $first_name  = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name   = trim($_POST['last_name']);
    $suffix      = trim($_POST['suffix']);
    $course      = trim($_POST['course']);
    $major       = trim($_POST['major']);
    $address     = trim($_POST['address']);
    $honors      = $_POST['honors'];
    $graduation_year = trim($_POST['graduation_year']);

    // --- Step: Information Complete? ---
    if ($student_id === '' || $first_name === '' || $last_name === '' || $course === '' || $address === '' || $graduation_year === '') {
        $errors[] = 'Please fill out all required fields.';
    }

    // --- Step: Student ID already exist? ---
    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT graduate_id FROM graduate WHERE student_id = ?");
        mysqli_stmt_bind_param($checkStmt, 's', $student_id);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'A graduate with this Student ID already exists.';
        }
    }

    // --- Step: Save Graduate ---
    if (empty($errors)) {
        $insertStmt = mysqli_prepare($conn, "INSERT INTO graduate (school_id, student_id, first_name, middle_name, last_name, suffix, course, address, major, honors, graduation_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param(
            $insertStmt,
            'i' . str_repeat('s', 10),
            $school_id,
            $student_id,
            $first_name,
            $middle_name,
            $last_name,
            $suffix,
            $course,
            $address,
            $major,
            $honors,
            $graduation_year
        );
        mysqli_stmt_execute($insertStmt);

        $graduate_id = mysqli_insert_id($conn);
        // --- Handle photo upload ---
        $photoPath = null;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoDir = __DIR__ . '/../photos/';
            if (!is_dir($photoDir)) {
                mkdir($photoDir, 0755, true);
            }

            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photoFilename = 'photo_' . $graduate_id . '.' . $extension;
            $photoPath = 'photos/' . $photoFilename;

            move_uploaded_file($_FILES['photo']['tmp_name'], $photoDir . $photoFilename);

            $photoUpdateStmt = mysqli_prepare($conn, "UPDATE graduate SET photo = ? WHERE graduate_id = ?");
            mysqli_stmt_bind_param($photoUpdateStmt, 'si', $photoPath, $graduate_id);
            mysqli_stmt_execute($photoUpdateStmt);
        }

        // --- Step: Automatically Generate QR Code ---
        $qr_token = bin2hex(random_bytes(16)); // random unique token, e.g. used when scanning

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => true,
        ]);
        $qrcode = new QRCode($options);
        $qrImageData = $qrcode->render($qr_token); 

        // --- Step: Save QR Code (to disk) ---
        $qrDir = __DIR__ . '/../qrcodes/';
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        $qrFilename = 'qr_' . $graduate_id . '.png';
        $qrData = base64_decode(explode(',', $qrImageData)[1]);
        file_put_contents($qrDir . $qrFilename, $qrData);

        // --- Save QR record to qr_code table ---
        $qrStmt = mysqli_prepare($conn, "INSERT INTO qr_code (graduate_id, qr_token, qr_status) VALUES (?, ?, 'active')");
        mysqli_stmt_bind_param($qrStmt, 'is', $graduate_id, $qr_token);
        mysqli_stmt_execute($qrStmt);

        // --- Step: Graduate Successfully Added ---
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GradScan | Add Graduate</title>
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
            <h1>Add Graduate</h1>
        </div>
        <div class="content">
            <div class="card">
                <div class="card-body">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Graduate successfully added! QR code generated.
                            <a href="graduates.php">Back to Graduate Management</a>
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
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Student ID</label>
                            <input type="text" name="student_id" class="form-control" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" name="suffix" class="form-control" value="<?= htmlspecialchars($_POST['suffix'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Course</label>
                            <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($_POST['course'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Major</label>
                            <input type="text" name="major" class="form-control" value="<?= htmlspecialchars($_POST['major'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Enter graduate's address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Honors</label>
                            <select name="honors" class="form-control">
                                <option value="none">No Honors</option>
                                <option value="cum laude">Cum Laude</option>
                                <option value="magna cum laude">Magna Cum Laude</option>
                                <option value="summa cum laude">Summa Cum Laude</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Photo</label>
                            <input type="file" name="photo" class="form-control-file" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control" value="<?= htmlspecialchars($_POST['graduation_year'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="this.disabled=true; this.form.submit();">Save Graduate</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='graduates.php'">Cancel</button>
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