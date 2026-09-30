<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
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

$root = '../../';
$pageTitle = 'GradScan | Edit School';
$topbarTitle = 'Manage Schools';
$activeNav = 'schools';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/../includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Edit School</h1>
            <p class="gs-page-description">Update <?= htmlspecialchars($school['school_name']) ?>'s details.</p>
        </section>

        <?php if ($success): ?>
            <div class="gs-alert gs-alert-success mb-6">
                Changes saved successfully.
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

        <div class="gs-card max-w-2xl p-8">
            <form method="POST" class="grid grid-cols-1 gap-5">

                <div>
                    <label class="gs-label">School Name</label>
                    <input type="text" name="school_name" class="gs-input" value="<?= htmlspecialchars($school['school_name']) ?>">
                </div>

                <div>
                    <label class="gs-label">School Code</label>
                    <input type="text" name="school_code" class="gs-input" value="<?= htmlspecialchars($school['school_code']) ?>">
                </div>

                <div>
                    <label class="gs-label">Status</label>
                    <select name="status" class="gs-input">
                        <option value="active" <?= $school['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $school['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="mt-2 flex gap-3">
                    <button type="submit" onclick="this.disabled=true; this.form.submit();" class="gs-button-primary">
                        Save Changes
                    </button>
                    <a href="super_admin_schools.php" class="gs-button-secondary py-3">Cancel</a>
                </div>

            </form>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
