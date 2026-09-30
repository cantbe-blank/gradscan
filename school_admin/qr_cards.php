<?php
/**
 * Printable QR cards for graduates, filtered by graduation year and course
 * (or one graduate with ?id=). Each card's QR prints at a fixed 30 mm, the
 * size the desk scanner reads most reliably, with the graduate's details.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/qr_helpers.php';
gs_require_role(['school_admin'], '../login.php');

$school_id = $_SESSION['school_id'];

$idFilter     = (int)($_GET['id'] ?? 0);
$yearFilter   = $_GET['year'] ?? '';
$courseFilter = $_GET['course'] ?? '';
$includeUsed  = !empty($_GET['include_used']);

$schoolStmt = mysqli_prepare($conn, "SELECT school_name FROM school WHERE school_id = ?");
mysqli_stmt_bind_param($schoolStmt, 'i', $school_id);
mysqli_stmt_execute($schoolStmt);
$schoolName = mysqli_fetch_row(mysqli_stmt_get_result($schoolStmt))[0] ?? '';

// Filter options
$optStmt = mysqli_prepare($conn, "SELECT DISTINCT graduation_year, course FROM graduate WHERE school_id = ? ORDER BY graduation_year DESC, course");
mysqli_stmt_bind_param($optStmt, 'i', $school_id);
mysqli_stmt_execute($optStmt);
$opts = mysqli_fetch_all(mysqli_stmt_get_result($optStmt), MYSQLI_ASSOC);
$yearOptions = array_values(array_unique(array_column($opts, 'graduation_year')));
$courseOptions = array_values(array_unique(array_column($opts, 'course')));
sort($courseOptions);

// Graduates with their newest QR code
$sql = "
    SELECT graduate.graduate_id, graduate.student_id, graduate.first_name, graduate.middle_name,
           graduate.last_name, graduate.suffix, graduate.course, graduate.major, graduate.graduation_year,
           qr_code.qr_token, qr_code.qr_status
    FROM graduate
    LEFT JOIN qr_code ON qr_code.qr_id = (
        SELECT MAX(q.qr_id) FROM qr_code q WHERE q.graduate_id = graduate.graduate_id
    )
    WHERE graduate.school_id = ?
";
$params = [$school_id];
$types = 'i';

if ($idFilter) {
    $sql .= " AND graduate.graduate_id = ?";
    $params[] = $idFilter;
    $types .= 'i';
}
if ($yearFilter !== '') {
    $sql .= " AND graduate.graduation_year = ?";
    $params[] = (int)$yearFilter;
    $types .= 'i';
}
if ($courseFilter !== '') {
    $sql .= " AND graduate.course = ?";
    $params[] = $courseFilter;
    $types .= 's';
}
$sql .= " ORDER BY graduate.last_name, graduate.first_name";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$graduates = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$cards = [];
$skipped = [];
foreach ($graduates as $g) {
    $printable = $g['qr_token'] !== null && ($g['qr_status'] === 'active' || ($includeUsed && $g['qr_status'] === 'used'));
    if ($printable) {
        $cards[] = $g;
    } else {
        $skipped[] = $g;
    }
}

function gs_card_name(array $g): string
{
    return trim(implode(' ', array_filter([$g['first_name'], $g['middle_name'], $g['last_name'], $g['suffix']])));
}

$pageTitle = 'GradScan | Print QR Cards';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<style>
    /* Each card's QR is a fixed physical size so it prints scanner-ready */
    .gs-qr-card-code { width: 30mm; height: 30mm; }

    @media print {
        @page { size: A4; margin: 10mm; }
        body { background: #fff !important; }
        .gs-sidebar, .gs-topbar, .gs-no-print { display: none !important; }
        .gs-main { margin-left: 0 !important; }
        .gs-content { padding: 0 !important; }
        .gs-qr-sheet { grid-template-columns: repeat(3, 1fr) !important; gap: 0 !important; }
        .gs-qr-card { break-inside: avoid; border: 1px dashed #9ca3af !important; border-radius: 0 !important; box-shadow: none !important; }
    }
</style>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">Print QR Cards</h2>
            <p class="gs-topbar-subtitle">Printable graduate QR codes</p>
        </div>

        <div class="flex items-center gap-5">

            <div class="text-right">
                <p class="gs-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
                <p class="gs-user-role"><?= htmlspecialchars($_SESSION['role']) ?></p>
            </div>

            <a href="../logout.php" class="gs-button-danger">Logout</a>

        </div>

    </header>


    <main class="gs-content">

        <div class="gs-no-print">

            <section class="gs-page-header flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="gs-page-title">QR Cards</h1>
                    <p class="gs-page-description">
                        Each QR prints at 30 mm, the size the scanner reads best. Cut along the dashed lines.
                    </p>
                </div>

                <div class="flex gap-2">
                    <a href="graduates.php" class="gs-button-secondary py-3 whitespace-nowrap">Back to Graduates</a>
                    <button type="button" onclick="window.print()" class="gs-button-primary whitespace-nowrap" <?= $cards ? '' : 'disabled' ?>>
                        Print <?= count($cards) ?> Card<?= count($cards) === 1 ? '' : 's' ?>
                    </button>
                </div>
            </section>

            <?php if (!$idFilter): ?>
            <section class="gs-card mb-6 p-5">
                <form method="GET" class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end">

                    <div>
                        <label class="gs-label">Graduation Year</label>
                        <select name="year" class="gs-input md:w-44">
                            <option value="">All years</option>
                            <?php foreach ($yearOptions as $y): ?>
                                <option value="<?= (int)$y ?>" <?= $yearFilter === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="gs-label">Course</label>
                        <select name="course" class="gs-input md:w-56">
                            <option value="">All courses</option>
                            <?php foreach ($courseOptions as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $courseFilter === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 pb-3 text-sm text-gray-700">
                        <input type="checkbox" name="include_used" value="1" class="h-4 w-4 rounded border-gray-300" <?= $includeUsed ? 'checked' : '' ?>>
                        Include already-scanned codes
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="gs-button-primary">Apply Filter</button>
                        <a href="qr_cards.php" class="gs-button-secondary py-3">Clear</a>
                    </div>

                </form>
            </section>
            <?php endif; ?>

            <?php if ($skipped): ?>
                <div class="gs-alert gs-alert-warning mb-6">
                    <strong><?= count($skipped) ?> graduate(s) have no printable code</strong>
                    (none issued yet, already scanned, or invalidated):
                    <?= htmlspecialchars(implode(', ', array_map('gs_card_name', array_slice($skipped, 0, 10)))) ?><?= count($skipped) > 10 ? ', …' : '' ?>.
                    Issue a new code from their View panel on the Graduates page.
                </div>
            <?php endif; ?>

        </div>

        <?php if (!$cards): ?>
            <div class="gs-card gs-no-print p-10 text-center text-sm text-gray-400">No QR cards to print for this filter.</div>
        <?php else: ?>

            <!-- Card sheet: responsive on screen, 3 per row on A4 when printed -->
            <section class="gs-qr-sheet grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($cards as $g): ?>
                    <article class="gs-qr-card flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">

                        <div class="gs-qr-card-code shrink-0">
                            <?= gs_qr_svg($g['qr_token']) ?>
                        </div>

                        <div class="min-w-0 text-gray-800">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-ascot-green"><?= htmlspecialchars($schoolName) ?></p>
                            <p class="mt-0.5 text-sm font-semibold leading-snug"><?= htmlspecialchars(gs_card_name($g)) ?></p>
                            <p class="text-xs text-gray-600"><?= htmlspecialchars($g['student_id']) ?></p>
                            <p class="text-xs text-gray-600"><?= htmlspecialchars($g['course'] . ($g['major'] ? ' — ' . $g['major'] : '')) ?></p>
                            <p class="text-xs text-gray-500">Class of <?= (int)$g['graduation_year'] ?></p>
                            <?php if ($g['qr_status'] !== 'active'): ?>
                                <p class="gs-no-print mt-1"><span class="gs-badge gs-badge-neutral capitalize"><?= htmlspecialchars($g['qr_status']) ?></span></p>
                            <?php endif; ?>
                        </div>

                    </article>
                <?php endforeach; ?>
            </section>

        <?php endif; ?>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
