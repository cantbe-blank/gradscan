<?php
require_once __DIR__ . '/../config/config.php';
gs_require_role(['school_admin'], '../login.php');

$school_id = $_SESSION['school_id'];

// Gaps longer than this between two graduates are treated as a break, not
// processing time, when working out the typical time per graduate.
const BREAK_SECONDS = 600;

// --- Step: Search/Filter? ---
$statusFilter = $_GET['status'] ?? '';
$yearFilter   = $_GET['year'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';

// Graduation years that have graduates or scans, for the filter dropdown
$yearsStmt = mysqli_prepare($conn, "
    SELECT graduation_year FROM graduate WHERE school_id = ?
    UNION
    SELECT graduation_year FROM scan_log WHERE school_id = ? AND graduation_year IS NOT NULL
    ORDER BY graduation_year DESC
");
mysqli_stmt_bind_param($yearsStmt, 'ii', $school_id, $school_id);
mysqli_stmt_execute($yearsStmt);
$years = array_column(mysqli_fetch_all(mysqli_stmt_get_result($yearsStmt)), 0);

// --- Step: Retrieve Scan Records ---
// scan_log carries its own school_id and graduation_year (copied at scan time),
// so records stay here even if the graduate is later deleted. The graduate and
// operator are LEFT JOINed for names and may be missing for old records.
//
// interval_seconds: time since the previous successful scan of the same
// graduation year on the same day, i.e. how long each graduate took.
$where = " WHERE scan_log.school_id = ?";
$params = [$school_id];
$types = 'i';

if ($yearFilter !== '') {
    $where .= " AND scan_log.graduation_year = ?";
    $params[] = (int)$yearFilter;
    $types .= 'i';
}

if ($dateFrom !== '') {
    $where .= " AND scan_log.scanned_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}

if ($dateTo !== '') {
    $where .= " AND scan_log.scanned_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$sql = "
    SELECT * FROM (
        SELECT
            scan_log.scan_id,
            scan_log.scan_status,
            scan_log.scanned_at,
            scan_log.graduation_year,
            graduate.student_id,
            graduate.first_name,
            graduate.last_name,
            user.full_name AS operator_name,
            CASE WHEN scan_log.scan_status = 'success' THEN
                TIMESTAMPDIFF(SECOND,
                    LAG(scan_log.scanned_at) OVER (
                        PARTITION BY scan_log.scan_status = 'success', scan_log.graduation_year, DATE(scan_log.scanned_at)
                        ORDER BY scan_log.scanned_at, scan_log.scan_id
                    ),
                    scan_log.scanned_at)
            END AS interval_seconds
        FROM scan_log
        LEFT JOIN qr_code ON scan_log.qr_id = qr_code.qr_id
        LEFT JOIN graduate ON qr_code.graduate_id = graduate.graduate_id
        LEFT JOIN user ON scan_log.operator_id = user.user_id
        $where
    ) AS scans
";

// The status filter is applied outside so intervals are still measured
// between successful scans only
if ($statusFilter !== '') {
    $sql .= " WHERE scan_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " ORDER BY scanned_at DESC, scan_id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// --- Summary of the successful scans in view ---
$successes = array_values(array_filter($rows, fn($r) => $r['scan_status'] === 'success'));
$intervals = array_values(array_filter(
    array_map(fn($r) => $r['interval_seconds'] !== null ? (int)$r['interval_seconds'] : null, $successes),
    fn($s) => $s !== null && $s <= BREAK_SECONDS
));
sort($intervals);

$summary = [
    'processed'    => count($successes),
    'first'        => $successes ? end($successes)['scanned_at'] : null,
    'last'         => $successes ? $successes[0]['scanned_at'] : null,
    'median'       => $intervals ? $intervals[intdiv(count($intervals), 2)] : null,
    'average'      => $intervals ? (int)round(array_sum($intervals) / count($intervals)) : null,
    'problemScans' => count($rows) - count($successes),
];

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
                    <label class="gs-label">Graduation Year</label>
                    <select name="year" class="gs-input md:w-44">
                        <option value="">All years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= (int)$year ?>" <?= $yearFilter === (string)$year ? 'selected' : '' ?>><?= (int)$year ?></option>
                        <?php endforeach; ?>
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

        <!-- Summary of the graduates processed in this view -->
        <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <div class="gs-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Graduates processed</p>
                <p class="mt-2 text-2xl font-semibold text-ascot-dark"><?= $summary['processed'] ?></p>
                <p class="mt-1 text-xs text-gray-500"><?= $summary['problemScans'] ?> other scan attempt(s)</p>
            </div>

            <div class="gs-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Typical time per graduate</p>
                <p class="mt-2 text-2xl font-semibold text-ascot-dark"><?= gs_format_duration($summary['median']) ?></p>
                <p class="mt-1 text-xs text-gray-500">Average <?= gs_format_duration($summary['average']) ?>; gaps over <?= BREAK_SECONDS / 60 ?> min count as breaks</p>
            </div>

            <div class="gs-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">First scan</p>
                <p class="mt-2 text-base font-semibold text-gray-800"><?= gs_format_datetime($summary['first']) ?></p>
            </div>

            <div class="gs-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Latest scan</p>
                <p class="mt-2 text-base font-semibold text-gray-800"><?= gs_format_datetime($summary['last']) ?></p>
            </div>

        </section>

        <!-- --- Step: Display Scan Records --- -->
        <section class="gs-table-wrapper">
            <div class="overflow-x-auto">

            <table class="gs-table">

                <thead>
                    <tr class="bg-gray-50">
                        <th class="gs-table-header px-6 py-4">Student ID</th>
                        <th class="gs-table-header px-6 py-4">Graduate Name</th>
                        <th class="gs-table-header px-6 py-4">Year</th>
                        <th class="gs-table-header px-6 py-4">Status</th>
                        <th class="gs-table-header px-6 py-4">Scanned At</th>
                        <th class="gs-table-header px-6 py-4" title="Time since the previous graduate of the same year was scanned that day">Interval</th>
                        <th class="gs-table-header px-6 py-4">Operator</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">
                                No scan records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="transition hover:bg-gray-50">

                                <td class="gs-table-cell font-medium text-ascot-dark">
                                    <?= htmlspecialchars($row['student_id'] ?? 'N/A') ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'N/A') ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= $row['graduation_year'] !== null ? (int)$row['graduation_year'] : '—' ?>
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
                                    <?= gs_format_datetime($row['scanned_at']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?php if ($row['interval_seconds'] === null): ?>
                                        <span class="text-gray-400">—</span>
                                    <?php elseif ((int)$row['interval_seconds'] > BREAK_SECONDS): ?>
                                        <span class="text-gray-400" title="Longer than <?= BREAK_SECONDS / 60 ?> minutes: counted as a break"><?= gs_format_duration((int)$row['interval_seconds']) ?> (break)</span>
                                    <?php else: ?>
                                        <?= gs_format_duration((int)$row['interval_seconds']) ?>
                                    <?php endif; ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['operator_name'] ?? 'Unknown') ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>

            </table>

            </div>
        </section>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
