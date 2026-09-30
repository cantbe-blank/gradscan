<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

$errors = [];
$success = false;

$schoolsResult = mysqli_query($conn, "SELECT school_id, school_name, school_code FROM school WHERE status = 'active'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $school_id = $_POST['school_id'];

    if ($full_name === '' || $username === '' || $password === '' || $school_id === '') {
        $errors[] = 'Please fill out all fields.';
    }

    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM user WHERE username = ?");
        mysqli_stmt_bind_param($checkStmt, 's', $username);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'This username is already taken.';
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insertStmt = mysqli_prepare($conn, "INSERT INTO user (school_id, username, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'operator')");
        mysqli_stmt_bind_param($insertStmt, 'isss', $school_id, $username, $passwordHash, $full_name);
        mysqli_stmt_execute($insertStmt);

        $success = true;
    }
}

$root = '../../';
$pageTitle = 'GradScan | Create Operator';
$topbarTitle = 'Manage Operators';
$activeNav = 'operators';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/../includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Create Operator Account</h1>
            <p class="gs-page-description">Give an operator access to the ceremony scanner.</p>
        </section>

        <?php if ($success): ?>
            <div class="gs-alert gs-alert-success mb-6">
                Operator account created successfully.
                <a href="operators.php" class="ml-1 font-semibold underline">Back to Manage Operators</a>
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
        <div class="gs-card max-w-3xl p-8">
            <form method="POST" class="grid grid-cols-1 gap-5 md:grid-cols-2">

                <div class="md:col-span-2">
                    <label class="gs-label">Full Name</label>
                    <input type="text" name="full_name" class="gs-input" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>

                <div>
                    <label class="gs-label">Username</label>
                    <input type="text" name="username" class="gs-input" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div>
                    <label class="gs-label">Password</label>
                    <input type="password" name="password" class="gs-input">
                </div>

                <div class="md:col-span-2">
                    <label class="gs-label">Assign School</label>
                    <select name="school_id" class="gs-input">
                        <option value="">-- Select a School --</option>
                        <?php mysqli_data_seek($schoolsResult, 0); while ($s = mysqli_fetch_assoc($schoolsResult)): ?>
                            <option value="<?= $s['school_id'] ?>" <?= (($_POST['school_id'] ?? '') == $s['school_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['school_name'] . ' (' . $s['school_code'] . ')') ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mt-2 flex gap-3 md:col-span-2">
                    <button type="submit" onclick="this.disabled=true; this.form.submit();" class="gs-button-primary">
                        Save Account
                    </button>
                    <a href="operators.php" class="gs-button-secondary py-3">Cancel</a>
                </div>

            </form>
        </div>
        <?php endif; ?>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
