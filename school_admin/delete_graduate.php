<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';
$school_id = $_SESSION['school_id'];

// --- Step: Select Graduate ---
$graduate_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$graduate_id) {
    die('No graduate specified.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM graduate WHERE graduate_id = ? AND school_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $graduate_id, $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$graduate = mysqli_fetch_assoc($result);

if (!$graduate) {
    die('Graduate not found, or does not belong to your department.');
}

// --- Step: Confirm Delete? ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['confirm'] === 'yes') {
        // --- Step: Delete Record / Update DB ---
        // qr_code rows for this graduate are removed automatically (ON DELETE CASCADE)
        // scan_log rows referencing those qr_codes keep their history (qr_id set to NULL)
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM graduate WHERE graduate_id = ? AND school_id = ?");
        mysqli_stmt_bind_param($deleteStmt, 'ii', $graduate_id, $school_id);
        mysqli_stmt_execute($deleteStmt);

        header('Location: graduates.php?deleted=1');
        exit;
    } else {
        // --- No: back to Graduate Management ---
        header('Location: graduates.php');
        exit;
    }
}

$honorLabels = [
    'none' => 'No Honors',
    'cum laude' => 'Cum Laude',
    'magna cum laude' => 'Magna Cum Laude',
    'summa cum laude' => 'Summa Cum Laude',
];

$fullName = trim(implode(' ', array_filter([
    $graduate['first_name'],
    $graduate['middle_name'],
    $graduate['last_name'],
    $graduate['suffix'],
])));

$pageTitle = 'GradScan | Delete Graduate';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">Delete Graduate</h2>
            <p class="gs-topbar-subtitle">Permanently remove a graduate record</p>
        </div>

        <div class="flex items-center gap-5">

            <div class="text-right">
                <p class="gs-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
                <p class="gs-user-role"><?= htmlspecialchars($_SESSION['role']) ?></p>
            </div>

            <a href="../logout.php" class="gs-button-danger">Logout</a>

        </div>

    </header>


    <!-- Main Content -->
    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Delete Graduate</h1>
            <p class="gs-page-description">This action cannot be undone.</p>
        </section>

        <div class="gs-card p-8">

            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>Are you sure you want to delete this graduate?</strong>
                This will also permanently remove their QR code. This action cannot be undone.
            </div>

            <div class="mb-6 flex items-start gap-5">

                <?php if (!empty($graduate['photo'])): ?>
                    <img
                        src="../<?= htmlspecialchars($graduate['photo']) ?>"
                        alt="Graduate photo"
                        class="h-20 w-20 flex-shrink-0 rounded-lg border border-gray-200 object-cover"
                    >
                <?php endif; ?>

                <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Student ID</dt>
                        <dd class="text-sm font-medium text-ascot-dark"><?= htmlspecialchars($graduate['student_id']) ?></dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Name</dt>
                        <dd class="text-sm text-gray-800"><?= htmlspecialchars($fullName) ?></dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Course</dt>
                        <dd class="text-sm text-gray-800"><?= htmlspecialchars($graduate['course']) ?></dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Major</dt>
                        <dd class="text-sm text-gray-800"><?= htmlspecialchars($graduate['major'] ?: '—') ?></dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Honors</dt>
                        <dd class="text-sm text-gray-800"><?= htmlspecialchars($honorLabels[$graduate['honors']] ?? $graduate['honors']) ?></dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Graduation Year</dt>
                        <dd class="text-sm text-gray-800"><?= htmlspecialchars($graduate['graduation_year']) ?></dd>
                    </div>

                </dl>

            </div>

            <form method="POST" class="flex gap-3">
                <input type="hidden" name="id" value="<?= $graduate['graduate_id'] ?>">
                <button type="submit" name="confirm" value="yes" class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    Yes, Delete
                </button>
                <button type="submit" name="confirm" value="no" class="gs-button-secondary">
                    Cancel
                </button>
            </form>

        </div>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
