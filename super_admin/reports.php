<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

// --- Step: Select Report ---
$reportType = $_GET['type'] ?? 'schools';

$root = '../';
$pageTitle = 'GradScan | View Reports';
$topbarTitle = 'View Reports';
$activeNav = 'reports';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">
            <h1 class="gs-page-title">Reports</h1>
            <p class="gs-page-description">School totals and campus-wide scan activity.</p>
        </section>

        <!-- --- Step: Select Report --- -->
        <nav class="mb-6 flex gap-2 border-b border-gray-200">
            <?php foreach (['schools' => 'Schools Report', 'scan_logs' => 'Scan Logs Report'] as $type => $label): ?>
                <a
                    href="reports.php?type=<?= $type ?>"
                    class="-mb-px border-b-2 px-4 py-3 text-sm font-semibold transition <?= $reportType === $type ? 'border-ascot-green text-ascot-dark' : 'border-transparent text-gray-500 hover:text-ascot-green' ?>"
                >
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="gs-table-wrapper">
            <div class="overflow-x-auto">

            <?php if ($reportType === 'schools'): ?>
                <!-- --- Step: Display Records (Schools) --- -->
                <?php
                $sql = "
                    SELECT
                        school.school_name,
                        school.school_code,
                        school.status,
                        COUNT(DISTINCT graduate.graduate_id) AS graduate_count,
                        COUNT(DISTINCT CASE WHEN user.role = 'school_admin' THEN user.user_id END) AS admin_count,
                        COUNT(DISTINCT CASE WHEN user.role = 'operator' THEN user.user_id END) AS operator_count
                    FROM school
                    LEFT JOIN graduate ON graduate.school_id = school.school_id
                    LEFT JOIN user ON user.school_id = school.school_id
                    GROUP BY school.school_id
                    ORDER BY school.school_name
                ";
                $result = mysqli_query($conn, $sql);
                ?>
                <table class="gs-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="gs-table-header px-6 py-4">School Name</th>
                            <th class="gs-table-header px-6 py-4">Code</th>
                            <th class="gs-table-header px-6 py-4">Status</th>
                            <th class="gs-table-header px-6 py-4">Graduates</th>
                            <th class="gs-table-header px-6 py-4">School Admins</th>
                            <th class="gs-table-header px-6 py-4">Operators</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php if (mysqli_num_rows($result) === 0): ?>
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">No schools found.</td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="transition hover:bg-gray-50">
                                <td class="gs-table-cell font-medium text-ascot-dark"><?= htmlspecialchars($row['school_name']) ?></td>
                                <td class="gs-table-cell"><?= htmlspecialchars($row['school_code']) ?></td>
                                <td class="gs-table-cell">
                                    <span class="gs-badge capitalize <?= $row['status'] === 'active' ? 'gs-badge-success' : 'gs-badge-neutral' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="gs-table-cell"><?= $row['graduate_count'] ?></td>
                                <td class="gs-table-cell"><?= $row['admin_count'] ?></td>
                                <td class="gs-table-cell"><?= $row['operator_count'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <!-- --- Step: Display Records (Scan Logs, campus-wide) --- -->
                <?php
                // School and graduation year are stored on each scan, so logs
                // stay attributed even after a graduate is deleted.
                $schoolFilter = $_GET['school'] ?? '';
                $yearFilter   = $_GET['year'] ?? '';

                $schoolOptions = mysqli_fetch_all(mysqli_query($conn, "SELECT school_id, school_name FROM school ORDER BY school_name"), MYSQLI_ASSOC);
                $yearOptions = array_column(mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT graduation_year FROM scan_log WHERE graduation_year IS NOT NULL ORDER BY graduation_year DESC")), 0);

                $sql = "
                    SELECT
                        scan_log.scan_status,
                        scan_log.scanned_at,
                        scan_log.graduation_year,
                        graduate.student_id,
                        graduate.first_name,
                        graduate.last_name,
                        school.school_name,
                        user.full_name AS operator_name
                    FROM scan_log
                    LEFT JOIN qr_code ON scan_log.qr_id = qr_code.qr_id
                    LEFT JOIN graduate ON qr_code.graduate_id = graduate.graduate_id
                    LEFT JOIN school ON scan_log.school_id = school.school_id
                    LEFT JOIN user ON scan_log.operator_id = user.user_id
                    WHERE 1 = 1
                ";
                $params = [];
                $types = '';
                if ($schoolFilter !== '') {
                    $sql .= " AND scan_log.school_id = ?";
                    $params[] = (int)$schoolFilter;
                    $types .= 'i';
                }
                if ($yearFilter !== '') {
                    $sql .= " AND scan_log.graduation_year = ?";
                    $params[] = (int)$yearFilter;
                    $types .= 'i';
                }
                $sql .= " ORDER BY scan_log.scanned_at DESC, scan_log.scan_id DESC";

                $stmt = mysqli_prepare($conn, $sql);
                if ($params) {
                    mysqli_stmt_bind_param($stmt, $types, ...$params);
                }
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                ?>

                <form method="GET" class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-6 py-4 md:flex-row md:items-end">
                    <input type="hidden" name="type" value="scan_logs">
                    <div>
                        <label class="gs-label">School</label>
                        <select name="school" class="gs-input md:w-64">
                            <option value="">All schools</option>
                            <?php foreach ($schoolOptions as $opt): ?>
                                <option value="<?= (int)$opt['school_id'] ?>" <?= $schoolFilter === (string)$opt['school_id'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['school_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="gs-label">Graduation Year</label>
                        <select name="year" class="gs-input md:w-44">
                            <option value="">All years</option>
                            <?php foreach ($yearOptions as $y): ?>
                                <option value="<?= (int)$y ?>" <?= $yearFilter === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="gs-button-primary">Apply Filter</button>
                        <a href="reports.php?type=scan_logs" class="gs-button-secondary py-3">Clear</a>
                    </div>
                </form>
                <table class="gs-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="gs-table-header px-6 py-4">Student ID</th>
                            <th class="gs-table-header px-6 py-4">Graduate Name</th>
                            <th class="gs-table-header px-6 py-4">School</th>
                            <th class="gs-table-header px-6 py-4">Year</th>
                            <th class="gs-table-header px-6 py-4">Status</th>
                            <th class="gs-table-header px-6 py-4">Scanned At</th>
                            <th class="gs-table-header px-6 py-4">Operator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php if (mysqli_num_rows($result) === 0): ?>
                            <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">No scan records found.</td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="transition hover:bg-gray-50">
                                <td class="gs-table-cell font-medium text-ascot-dark"><?= htmlspecialchars($row['student_id'] ?? 'N/A') ?></td>
                                <td class="gs-table-cell"><?= htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                <td class="gs-table-cell"><?= htmlspecialchars($row['school_name'] ?? 'N/A') ?></td>
                                <td class="gs-table-cell"><?= $row['graduation_year'] !== null ? (int)$row['graduation_year'] : '—' ?></td>
                                <td class="gs-table-cell">
                                    <?php
                                    $badgeClass = match($row['scan_status']) {
                                        'success' => 'gs-badge-success',
                                        'used' => 'gs-badge-warning',
                                        'expired' => 'gs-badge-neutral',
                                        'invalidated' => 'gs-badge-error',
                                        'not_found' => 'gs-badge-dark',
                                        default => 'gs-badge-neutral',
                                    };
                                    ?>
                                    <span class="gs-badge <?= $badgeClass ?>"><?= htmlspecialchars($row['scan_status']) ?></span>
                                </td>
                                <td class="gs-table-cell"><?= gs_format_datetime($row['scanned_at']) ?></td>
                                <td class="gs-table-cell"><?= htmlspecialchars($row['operator_name'] ?? 'Unknown') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            </div>
        </section>

    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
