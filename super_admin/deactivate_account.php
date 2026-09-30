<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '../../config/config.php';

// --- Step: Select Admin/Operator ---
$user_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$user_id) {
    die('No account specified.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM user WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);

if (!$account) {
    die('Account not found.');
}

$newStatus = $account['status'] === 'active' ? 'inactive' : 'active';
$actionLabel = $account['status'] === 'active' ? 'Deactivate' : 'Reactivate';

// where to redirect back to, depending on which module linked here
$returnPage = $account['role'] === 'operator' ? 'operators/operators.php' : 'admins/school_admins.php';

// --- Step: Confirm Deactivation? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['confirm'] === 'yes') {
        // --- Step: Change Status to Inactive (or back to Active) ---
        $updateStmt = mysqli_prepare($conn, "UPDATE user SET status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $user_id);
        mysqli_stmt_execute($updateStmt);

        header('Location: ' . $returnPage);
        exit;
    } else {
        // --- Step: Cancel ---
        header('Location: ' . $returnPage);
        exit;
    }
}

$root = '../';
$pageTitle = 'GradScan | Account Status';
$topbarTitle = 'Account Status';
$activeNav = null;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title"><?= $actionLabel ?> Account</h1>
            <p class="gs-page-description">Change whether this account can sign in to GradScan.</p>
        </section>

        <div class="gs-card max-w-2xl p-8">

            <div class="gs-alert <?= $actionLabel === 'Deactivate' ? 'gs-alert-warning' : 'gs-alert-info' ?> mb-6">
                <strong>Are you sure you want to <?= strtolower($actionLabel) ?> this account?</strong>
            </div>

            <dl class="mb-8 grid grid-cols-[max-content_1fr] gap-x-8 gap-y-3 text-sm">

                <dt class="font-semibold text-gray-500">Username</dt>
                <dd class="text-gray-800"><?= htmlspecialchars($account['username']) ?></dd>

                <dt class="font-semibold text-gray-500">Full Name</dt>
                <dd class="text-gray-800"><?= htmlspecialchars($account['full_name']) ?></dd>

                <dt class="font-semibold text-gray-500">Role</dt>
                <dd class="text-gray-800"><?= htmlspecialchars($account['role']) ?></dd>

                <dt class="font-semibold text-gray-500">Current Status</dt>
                <dd>
                    <span class="gs-badge capitalize <?= $account['status'] === 'active' ? 'gs-badge-success' : 'gs-badge-neutral' ?>">
                        <?= htmlspecialchars($account['status']) ?>
                    </span>
                </dd>

            </dl>

            <form method="POST" class="flex gap-3">
                <input type="hidden" name="id" value="<?= $account['user_id'] ?>">
                <button type="submit" name="confirm" value="yes" class="<?= $actionLabel === 'Deactivate' ? 'gs-button-danger-solid' : 'gs-button-primary' ?>">Yes, <?= $actionLabel ?></button>
                <button type="submit" name="confirm" value="no" class="gs-button-secondary py-3">Cancel</button>
            </form>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
