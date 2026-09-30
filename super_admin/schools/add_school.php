<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name']);
    $school_code = trim($_POST['school_code']);

    // --- Step: Information Complete? ---
    if ($school_name === '' || $school_code === '') {
        $errors[] = 'Please fill out all fields.';
    }

    // --- Step: School code already exist? ---
    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT school_id FROM school WHERE school_code = ?");
        mysqli_stmt_bind_param($checkStmt, 's', $school_code);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'A school with this code already exists.';
        }
    }

    // --- Step: Save School / Save to DB ---
    if (empty($errors)) {
        $insertStmt = mysqli_prepare($conn, "INSERT INTO school (school_name, school_code) VALUES (?, ?)");
        mysqli_stmt_bind_param($insertStmt, 'ss', $school_name, $school_code);
        mysqli_stmt_execute($insertStmt);

        $success = true;
    }
}

$root = '../../';
$pageTitle = 'GradScan | Add School';
$topbarTitle = 'Manage Schools';
$activeNav = 'schools';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/../includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Add School</h1>
            <p class="gs-page-description">Register a new school in GradScan.</p>
        </section>

        <?php if ($success): ?>
            <div class="gs-alert gs-alert-success mb-6">
                School added successfully.
                <a href="super_admin_schools.php" class="ml-1 font-semibold underline">Back to Manage Schools</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="gs-alert gs-alert-error mb-6">
                <ul class="list-inside list-disc space-y-0.5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="gs-card max-w-2xl p-8">
            <form method="POST" class="grid grid-cols-1 gap-5">

                <div>
                    <label class="gs-label">School Name</label>
                    <input type="text" name="school_name" class="gs-input" value="<?= htmlspecialchars($_POST['school_name'] ?? '') ?>" placeholder="e.g. School of Information Technology">
                </div>

                <div>
                    <label class="gs-label">School Code</label>
                    <input type="text" name="school_code" class="gs-input" value="<?= htmlspecialchars($_POST['school_code'] ?? '') ?>" placeholder="e.g. BSIT">
                </div>

                <div class="mt-2 flex gap-3">
                    <button type="submit" onclick="this.disabled=true; this.form.submit();" class="gs-button-primary">
                        Save School
                    </button>
                    <a href="super_admin_schools.php" class="gs-button-secondary py-3">Cancel</a>
                </div>

            </form>
        </div>
        <?php endif; ?>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
