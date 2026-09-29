<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';
$school_id = $_SESSION['school_id'];

// --- Step: Search/Filter? ---
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

// --- Step: Retrieve Scan Records ---
// Joined through qr_code -> graduate so we can filter to this department's scans only.
// LEFT JOINs matter here: qr_id and operator_id can be NULL (a QR code or user account
// may have been deleted since the scan happened), and we still want that history to show.
$sql = "
    SELECT
        scan_log.scan_id,
        scan_log.scan_status,
        scan_log.scanned_at,
        graduate.student_id,
        graduate.first_name,
        graduate.last_name,
        user.full_name AS operator_name
    FROM scan_log
    LEFT JOIN qr_code ON scan_log.qr_id = qr_code.qr_id
    LEFT JOIN graduate ON qr_code.graduate_id = graduate.graduate_id
    LEFT JOIN user ON scan_log.operator_id = user.user_id
    WHERE graduate.school_id = ?
";

$params = [$school_id];
$types = 'i';

if ($statusFilter !== '') {
    $sql .= " AND scan_log.scan_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($dateFrom !== '') {
    $sql .= " AND scan_log.scanned_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}

if ($dateTo !== '') {
    $sql .= " AND scan_log.scanned_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$sql .= " ORDER BY scan_log.scanned_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$statusBadgeClasses = [
    'success'     => 'border-green-200 bg-green-50 text-green-700',
    'used'        => 'border-amber-200 bg-amber-50 text-amber-700',
    'expired'     => 'border-gray-200 bg-gray-100 text-gray-600',
    'invalidated' => 'border-red-200 bg-red-50 text-red-700',
];

$pageTitle = 'GradScan | Scan History';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">Scan History</h2>
            <p class="gs-topbar-subtitle">Review graduation scanning records and activity</p>
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
            <h1 class="gs-page-title">Scan History</h1>
            <p class="gs-page-description">Review and filter graduation scan records.</p>
        </section>

        <!-- --- Step: Search/Filter? / Apply Filter --- -->
        <section class="gs-card mb-6 p-5">

            <form method="GET" class="flex flex-col gap-3 md:flex-row md:items-end md:flex-wrap">

                <div>
                    <label class="gs-label">Status</label>
                    <select name="status" class="gs-input md:w-44">
                        <option value="">All</option>
                        <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                        <option value="used" <?= $statusFilter === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="invalidated" <?= $statusFilter === 'invalidated' ? 'selected' : '' ?>>Invalidated</option>
                    </select>
                </div>

                <div>
                    <label class="gs-label">From</label>
                    <input type="date" name="date_from" class="gs-input md:w-44" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>

                <div>
                    <label class="gs-label">To</label>
                    <input type="date" name="date_to" class="gs-input md:w-44" value="<?= htmlspecialchars($dateTo) ?>">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="gs-button-primary">Apply Filter</button>
                    <a href="scan_history.php" class="gs-button-secondary">Clear</a>
                </div>

            </form>

        </section>

        <!-- --- Step: Display Scan Records --- -->
        <section class="gs-table-wrapper">

            <table class="gs-table">

                <thead>
                    <tr class="bg-gray-50">
                        <th class="gs-table-header px-6 py-4">Student ID</th>
                        <th class="gs-table-header px-6 py-4">Graduate Name</th>
                        <th class="gs-table-header px-6 py-4">Status</th>
                        <th class="gs-table-header px-6 py-4">Scanned At</th>
                        <th class="gs-table-header px-6 py-4">Operator</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">
                                No scan records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="transition hover:bg-gray-50">

                                <td class="gs-table-cell font-medium text-ascot-dark">
                                    <?= htmlspecialchars($row['student_id'] ?? 'N/A') ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'N/A') ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?php
                                    $badgeClass = $statusBadgeClasses[$row['scan_status']] ?? 'border-gray-200 bg-gray-100 text-gray-600';
                                    ?>
                                    <span class="inline-block rounded-full border px-3 py-1 text-xs font-semibold capitalize <?= $badgeClass ?>">
                                        <?= htmlspecialchars($row['scan_status']) ?>
                                    </span>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['scanned_at']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['operator_name'] ?? 'Unknown') ?>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                </tbody>

            </table>

        </section>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
