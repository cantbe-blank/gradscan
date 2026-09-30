<?php

require_once __DIR__ . '/../config/config.php';
gs_require_role(['school_admin'], '../login.php');

$school_id = $_SESSION['school_id'];
$search = trim($_GET['q'] ?? '');

// Note: added address, honors, and graduation_year so the preview modal can
// show the full record without firing a second query per row.
if ($search !== '') {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, address, honors, graduation_year, photo,
                (SELECT q.qr_status FROM qr_code q WHERE q.graduate_id = graduate.graduate_id ORDER BY q.qr_id DESC LIMIT 1) AS qr_status
         FROM graduate
         WHERE school_id = ?
         AND (
             student_id LIKE ?
             OR first_name LIKE ?
             OR last_name LIKE ?
         )"
    );

    $likeSearch = '%' . $search . '%';

    mysqli_stmt_bind_param(
        $stmt,
        'isss',
        $school_id,
        $likeSearch,
        $likeSearch,
        $likeSearch
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, address, honors, graduation_year, photo,
                (SELECT q.qr_status FROM qr_code q WHERE q.graduate_id = graduate.graduate_id ORDER BY q.qr_id DESC LIMIT 1) AS qr_status
         FROM graduate
         WHERE school_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $school_id);
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$honorLabels = [
    'none' => 'No Honors',
    'cum laude' => 'Cum Laude',
    'magna cum laude' => 'Magna Cum Laude',
    'summa cum laude' => 'Summa Cum Laude',
];

$pageTitle = 'GradScan | Graduate Management';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">
                Graduate Management
            </h2>

            <p class="gs-topbar-subtitle">
                Manage graduate records
            </p>
        </div>

        <div class="flex items-center gap-5">

            <div class="text-right">
                <p class="gs-user-name">
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                </p>

                <p class="gs-user-role">
                    <?= htmlspecialchars($_SESSION['role']) ?>
                </p>
            </div>

            <a
                href="../logout.php"
                class="gs-button-danger"
            >
                Logout
            </a>

        </div>

    </header>


    <!-- Main Content -->
    <main class="gs-content">

        <!-- Page Header -->
        <section class="gs-page-header flex items-start justify-between gap-6">

            <div>
                <h1 class="gs-page-title">
                    Graduates
                </h1>

                <p class="gs-page-description">
                    View and manage graduates registered under your school.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">

                <a
                    href="qr_cards.php"
                    class="gs-button-secondary whitespace-nowrap py-3"
                >
                    Print QR Cards
                </a>

                <a
                    href="add_graduate.php"
                    class="gs-button-primary whitespace-nowrap"
                >
                    + Add Graduate
                </a>

            </div>

        </section>


        <?php if (isset($_GET['qr_reissued'])): ?>
            <div class="gs-alert gs-alert-success mb-6">
                A new QR code was issued. The previous code no longer works; give the graduate the new one.
            </div>
        <?php endif; ?>

        <!-- Search -->
        <section class="gs-card mb-6 p-5">

            <form
                method="GET"
                class="flex flex-col gap-3 md:flex-row"
            >

                <div class="flex-1">

                    <label
                        for="graduate-search"
                        class="sr-only"
                    >
                        Search graduates
                    </label>

                    <input
                        id="graduate-search"
                        type="text"
                        name="q"
                        class="gs-input"
                        placeholder="Search by name or student ID"
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>

                <div class="flex gap-2">

                    <button
                        type="submit"
                        class="gs-button-primary"
                    >
                        Search
                    </button>

                    <?php if ($search !== ''): ?>

                        <a
                            href="graduates.php"
                            class="gs-button-secondary"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- Graduate Table -->
        <section class="gs-table-wrapper">

            <div>

                <table class="gs-table">

                    <thead>

                        <tr class="bg-gray-50">

                            <th class="gs-table-header px-6 py-4">
                                Student ID
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Name
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Course
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Major
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Photo
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-200 bg-white">

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <?php
                                $fullName = trim(implode(' ', array_filter([
                                    $row['first_name'],
                                    $row['middle_name'],
                                    $row['last_name'],
                                    $row['suffix'],
                                ])));
                            ?>

                            <tr class="transition hover:bg-gray-50">

                                <td class="gs-table-cell font-medium text-ascot-dark">
                                    <?= htmlspecialchars($row['student_id']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($fullName) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['course']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['major']) ?>
                                </td>

                                <td class="gs-table-cell">

                                    <?php if ($row['photo']): ?>

                                        <img
                                            src="../<?= htmlspecialchars($row['photo']) ?>"
                                            alt="Graduate photo"
                                            class="h-12 w-12 rounded-lg border border-gray-200 object-cover"
                                        >

                                    <?php else: ?>

                                        <span class="text-sm text-gray-400">
                                            No photo
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="gs-table-cell">

                                    <div class="flex items-center gap-2">

                                        <button
                                            type="button"
                                            class="gs-button-view"
                                            onclick="openGraduatePreview(this)"
                                            data-id="<?= $row['graduate_id'] ?>"
                                            data-student-id="<?= htmlspecialchars($row['student_id']) ?>"
                                            data-first-name="<?= htmlspecialchars($row['first_name']) ?>"
                                            data-middle-name="<?= htmlspecialchars($row['middle_name']) ?>"
                                            data-last-name="<?= htmlspecialchars($row['last_name']) ?>"
                                            data-suffix="<?= htmlspecialchars($row['suffix']) ?>"
                                            data-course="<?= htmlspecialchars($row['course']) ?>"
                                            data-major="<?= htmlspecialchars($row['major']) ?>"
                                            data-address="<?= htmlspecialchars($row['address']) ?>"
                                            data-honors="<?= htmlspecialchars($honorLabels[$row['honors']] ?? $row['honors']) ?>"
                                            data-graduation-year="<?= htmlspecialchars($row['graduation_year']) ?>"
                                            data-photo="<?= $row['photo'] ? htmlspecialchars('../' . $row['photo']) : '' ?>"
                                            data-qr-status="<?= htmlspecialchars($row['qr_status'] ?? '') ?>"
                                        >
                                            View
                                        </button>

                                        <a
                                            href="edit_graduate.php?id=<?= $row['graduate_id'] ?>"
                                            class="gs-button-edit"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="delete_graduate.php?id=<?= $row['graduate_id'] ?>"
                                            class="gs-button-delete">
                                            Delete
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>


<!-- Graduate Preview Modal -->
<div
    id="graduatePreviewModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4"
    onclick="if (event.target === this) closeGraduatePreview()"
>

    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">

        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-bold text-ascot-dark">Graduate Details</h3>
            <button
                type="button"
                onclick="closeGraduatePreview()"
                class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                aria-label="Close"
            >
                &#10005;
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-[200px_1fr]">

            <!-- Photo: 2:3 ratio -->
            <div class="aspect-[2/3] w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                <img
                    id="previewPhoto"
                    src=""
                    alt="Graduate photo"
                    class="h-full w-full object-cover"
                >
                <div id="previewNoPhoto" class="hidden h-full w-full items-center justify-center text-sm text-gray-400">
                    No photo
                </div>
            </div>

            <!-- Details -->
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">

                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Full Name</dt>
                    <dd id="previewFullName" class="text-base font-semibold text-ascot-dark"></dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Student ID</dt>
                    <dd id="previewStudentId" class="text-sm text-gray-800"></dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Graduation Year</dt>
                    <dd id="previewGraduationYear" class="text-sm text-gray-800"></dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Course</dt>
                    <dd id="previewCourse" class="text-sm text-gray-800"></dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Major</dt>
                    <dd id="previewMajor" class="text-sm text-gray-800"></dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Honors</dt>
                    <dd id="previewHonors" class="text-sm text-gray-800"></dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Address</dt>
                    <dd id="previewAddress" class="text-sm text-gray-800"></dd>
                </div>

            </dl>

        </div>

        <!-- QR code -->
        <div class="border-t border-gray-200 px-6 py-5">

            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">

                <div class="mx-auto aspect-square w-40 shrink-0 rounded-xl border border-gray-200 bg-white p-2 sm:mx-0 sm:w-44">
                    <img id="previewQr" src="" alt="Graduate QR code" class="h-full w-full">
                    <div id="previewNoQr" class="hidden h-full w-full items-center justify-center text-center text-xs text-gray-400">
                        No QR code issued yet
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">QR Code</p>
                        <span id="previewQrStatus" class="gs-badge capitalize"></span>
                    </div>

                    <p id="previewQrHint" class="mt-1 text-xs leading-5 text-gray-500"></p>

                    <div id="previewQrActions" class="mt-3 flex flex-wrap gap-2">
                        <a id="previewQrPng" href="#" class="gs-button-view">Download PNG</a>
                        <button type="button" id="previewQrPhone" class="gs-button-view">Phone Image</button>
                        <a id="previewQrPrint" href="#" target="_blank" class="gs-button-view">Print Card</a>
                    </div>

                    <form method="POST" action="qr_regenerate.php" class="mt-3"
                          onsubmit="return confirm('Issue a new QR code? The current code will stop working.');">
                        <input type="hidden" name="id" id="previewQrId" value="">
                        <button type="submit" id="previewQrReissue" class="gs-button-edit">Issue New Code</button>
                    </form>
                </div>

            </div>

        </div>

        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
            <button
                type="button"
                onclick="closeGraduatePreview()"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
            >
                Close
            </button>
            <a
                id="previewEditLink"
                href="#"
                class="rounded-lg bg-ascot-green px-4 py-2 text-sm font-semibold text-white transition hover:bg-ascot-dark"
            >
                Edit Graduate
            </a>
        </div>

    </div>

</div>

<script>
    function openGraduatePreview(btn) {
        const d = btn.dataset;

        const fullName = [d.firstName, d.middleName, d.lastName, d.suffix]
            .filter(Boolean)
            .join(' ');

        document.getElementById('previewFullName').textContent = fullName;
        document.getElementById('previewStudentId').textContent = d.studentId;
        document.getElementById('previewGraduationYear').textContent = d.graduationYear || '—';
        document.getElementById('previewCourse').textContent = d.course || '—';
        document.getElementById('previewMajor').textContent = d.major || '—';
        document.getElementById('previewHonors').textContent = d.honors || '—';
        document.getElementById('previewAddress').textContent = d.address || '—';
        document.getElementById('previewEditLink').href = 'edit_graduate.php?id=' + d.id;

        const photoImg = document.getElementById('previewPhoto');
        const noPhoto = document.getElementById('previewNoPhoto');

        if (d.photo) {
            photoImg.src = d.photo;
            photoImg.classList.remove('hidden');
            noPhoto.classList.add('hidden');
            noPhoto.classList.remove('flex');
        } else {
            photoImg.classList.add('hidden');
            noPhoto.classList.remove('hidden');
            noPhoto.classList.add('flex');
        }

        showQrPanel(d, fullName);

        const modal = document.getElementById('graduatePreviewModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // --- QR panel ---
    const QR_STATUS = {
        active:      ['gs-badge-success', 'Ready to scan. Printed cards show it at 30 mm; the phone image keeps it about 2–3 cm wide on screen.'],
        used:        ['gs-badge-neutral', 'Already scanned at the ceremony. Issue a new code only if they need to be scanned again.'],
        invalidated: ['gs-badge-error', 'This code was replaced and no longer works. Issue a new code.'],
        expired:     ['gs-badge-neutral', 'This code has expired. Issue a new code.'],
    };
    let currentQr = null;

    function showQrPanel(d, fullName) {
        const status = d.qrStatus;
        const img = document.getElementById('previewQr');
        const none = document.getElementById('previewNoQr');
        const badge = document.getElementById('previewQrStatus');
        const actions = document.getElementById('previewQrActions');

        document.getElementById('previewQrId').value = d.id;
        currentQr = { id: d.id, name: fullName, studentId: d.studentId, course: d.course, year: d.graduationYear };

        if (!status) {
            img.classList.add('hidden');
            none.classList.remove('hidden');
            none.classList.add('flex');
            badge.className = 'gs-badge gs-badge-warning';
            badge.textContent = 'none';
            document.getElementById('previewQrHint').textContent = 'This graduate has no QR code yet.';
            actions.classList.add('hidden');
            document.getElementById('previewQrReissue').textContent = 'Issue QR Code';
            return;
        }

        // The version parameter stops the browser reusing an image from before a reissue
        img.src = 'qr.php?id=' + encodeURIComponent(d.id) + '&v=' + Date.now();
        img.classList.remove('hidden');
        none.classList.add('hidden');
        none.classList.remove('flex');
        actions.classList.remove('hidden');

        const [cls, hint] = QR_STATUS[status] || ['gs-badge-neutral', ''];
        badge.className = 'gs-badge capitalize ' + cls;
        badge.textContent = status;
        document.getElementById('previewQrHint').textContent = hint;
        document.getElementById('previewQrReissue').textContent = 'Issue New Code';

        document.getElementById('previewQrPng').href = 'qr.php?id=' + encodeURIComponent(d.id) + '&format=png&download=1';
        document.getElementById('previewQrPrint').href = 'qr_cards.php?id=' + encodeURIComponent(d.id) + '&include_used=1';
    }

    // Phone image: a portrait card where the QR fills about 40% of the width,
    // so on a typical phone held at full screen it shows about 2.6 cm wide,
    // which is what the desk scanner reads most reliably.
    document.getElementById('previewQrPhone').addEventListener('click', async function () {
        if (!currentQr) return;

        const qr = new Image();
        qr.src = 'qr.php?id=' + encodeURIComponent(currentQr.id) + '&format=png&v=' + Date.now();
        try {
            await qr.decode();
            await document.fonts.ready;
        } catch (e) {
            alert('Could not load the QR code. Please try again.');
            return;
        }

        const W = 1080, H = 1920, Q = 440;
        const c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        const ctx = c.getContext('2d');

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#074422';
        ctx.fillRect(0, 0, W, 260);

        ctx.textAlign = 'center';
        ctx.fillStyle = '#ffffff';
        ctx.font = '700 64px Poppins, sans-serif';
        ctx.fillText('GradScan', W / 2, 150);
        ctx.fillStyle = '#FFDD21';
        ctx.font = '500 34px Poppins, sans-serif';
        ctx.fillText('Graduation QR Code', W / 2, 210);

        ctx.fillStyle = '#1f2937';
        ctx.font = '600 56px Poppins, sans-serif';
        wrapText(ctx, currentQr.name, W / 2, 420, W - 160, 68);

        ctx.fillStyle = '#4b5563';
        ctx.font = '400 40px Poppins, sans-serif';
        ctx.fillText(currentQr.studentId, W / 2, 620);
        ctx.fillText((currentQr.course || '') + (currentQr.year ? ' · Class of ' + currentQr.year : ''), W / 2, 680);

        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(qr, (W - Q) / 2, 820, Q, Q);

        ctx.fillStyle = '#6b7280';
        ctx.font = '400 34px Poppins, sans-serif';
        wrapText(ctx, 'Open this image at full screen and hold the phone flat over the scanner. Do not zoom in.', W / 2, 1420, W - 200, 48);

        const link = document.createElement('a');
        link.download = 'QR_' + String(currentQr.studentId).replace(/[^A-Za-z0-9_-]/g, '_') + '_phone.png';
        link.href = c.toDataURL('image/png');
        link.click();
    });

    function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
        let line = '';
        for (const word of String(text).split(' ')) {
            const test = line ? line + ' ' + word : word;
            if (ctx.measureText(test).width > maxWidth && line) {
                ctx.fillText(line, x, y);
                line = word;
                y += lineHeight;
            } else {
                line = test;
            }
        }
        if (line) ctx.fillText(line, x, y);
    }

    function closeGraduatePreview() {
        const modal = document.getElementById('graduatePreviewModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeGraduatePreview();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
