<?php

require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$search = trim($_GET['q'] ?? '');

// Note: added address, honors, and graduation_year so the preview modal can
// show the full record without firing a second query per row.
if ($search !== '') {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, address, honors, graduation_year, photo
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
        "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, address, honors, graduation_year, photo
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

            <a
                href="add_graduate.php"
                class="gs-button-primary whitespace-nowrap"
            >
                + Add Graduate
            </a>

        </section>


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

    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl">

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

        const modal = document.getElementById('graduatePreviewModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
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
