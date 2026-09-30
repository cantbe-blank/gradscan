<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
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

$root = '../../';
$pageTitle = 'GradScan | Edit School Admin';
$topbarTitle = 'Manage School Admins';
$activeNav = 'admins';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/../includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Edit School Admin</h1>
            <p class="gs-page-description">Update <?= htmlspecialchars($account['full_name']) ?>'s account.</p>
        </section>

        <?php if ($success): ?>
            <div class="gs-alert gs-alert-success mb-6">
                Changes saved successfully.
                <a href="school_admins.php" class="ml-1 font-semibold underline">Back to Manage School Admins</a>
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

        <div class="gs-card max-w-3xl p-8">
            <form method="POST" class="grid grid-cols-1 gap-5 md:grid-cols-2">

                <div>
                    <label class="gs-label">Username</label>
                    <input type="text" class="gs-input bg-gray-50 text-gray-500" value="<?= htmlspecialchars($account['username']) ?>" disabled>
                    <p class="mt-1 text-xs text-gray-500">Username cannot be changed.</p>
                </div>

                <div>
                    <label class="gs-label">Full Name</label>
                    <input type="text" name="full_name" class="gs-input" value="<?= htmlspecialchars($account['full_name']) ?>">
                </div>

                <div>
                    <label class="gs-label">Assign School</label>
                    <select name="school_id" class="gs-input">
                        <?php mysqli_data_seek($schoolsResult, 0); while ($s = mysqli_fetch_assoc($schoolsResult)): ?>
                            <option value="<?= $s['school_id'] ?>" <?= $account['school_id'] == $s['school_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['school_name'] . ' (' . $s['school_code'] . ')') ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="gs-label">Reset Password</label>
                    <input type="password" name="password" class="gs-input" placeholder="Leave blank to keep current password">
                </div>

                <div class="mt-2 flex gap-3 md:col-span-2">
                    <button type="submit" onclick="this.disabled=true; this.form.submit();" class="gs-button-primary">
                        Save Changes
                    </button>
                    <a href="school_admins.php" class="gs-button-secondary py-3">Cancel</a>
                </div>

            </form>
        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
