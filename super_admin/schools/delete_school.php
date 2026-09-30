<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

// --- Step: Select School ---
$school_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$school_id) {
    die('No school specified.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM school WHERE school_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$school = mysqli_fetch_assoc($result);

if (!$school) {
    die('School not found.');
}

// --- Step: School has Records? (check for existing graduates in this department) ---
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM graduate WHERE school_id = ?");
mysqli_stmt_bind_param($countStmt, 'i', $school_id);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$graduateCount = mysqli_fetch_assoc($countResult)['total'];

$hasRecords = $graduateCount > 0;

// --- Step: Confirm Delete? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasRecords) {
    if ($_POST['confirm'] === 'yes') {
        // --- Step: Delete School ---
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM school WHERE school_id = ?");
        mysqli_stmt_bind_param($deleteStmt, 'i', $school_id);
        mysqli_stmt_execute($deleteStmt);

        header('Location: super_admin_schools.php?deleted=1');
        exit;
    } else {
        header('Location: super_admin_schools.php');
        exit;
    }
}

$root = '../../';
$pageTitle = 'GradScan | Delete School';
$topbarTitle = 'Manage Schools';
$activeNav = 'schools';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/../includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Delete School</h1>
            <p class="gs-page-description">Remove a school from GradScan.</p>
        </section>

        <div class="gs-card max-w-2xl p-8">

            <?php if ($hasRecords): ?>
                <!-- --- Step: Cannot Delete --- -->
                <div class="gs-alert gs-alert-error mb-6">
                    <strong>Cannot delete this school.</strong>
                    It still has <?= $graduateCount ?> graduate record(s) attached.
                    Remove or reassign those records first.
                </div>

                <p class="mb-6 text-sm text-gray-700">
                    <span class="font-semibold">School:</span>
                    <?= htmlspecialchars($school['school_name']) ?> (<?= htmlspecialchars($school['school_code']) ?>)
                </p>

                <a href="super_admin_schools.php" class="gs-button-secondary inline-block py-3">Back to Manage Schools</a>

            <?php else: ?>
                <div class="gs-alert gs-alert-warning mb-6">
                    <strong>Are you sure you want to delete this school?</strong>
                    This action cannot be undone.
                </div>

                <p class="mb-6 text-sm text-gray-700">
                    <span class="font-semibold">School:</span>
                    <?= htmlspecialchars($school['school_name']) ?> (<?= htmlspecialchars($school['school_code']) ?>)
                </p>

                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="id" value="<?= $school['school_id'] ?>">
                    <button type="submit" name="confirm" value="yes" class="gs-button-danger-solid">Yes, Delete</button>
                    <button type="submit" name="confirm" value="no" class="gs-button-secondary py-3">Cancel</button>
                </form>
            <?php endif; ?>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
