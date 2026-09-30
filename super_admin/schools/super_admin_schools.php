<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM school WHERE school_name LIKE ? OR school_code LIKE ?");
    $likeSearch = '%' . $search . '%';
    mysqli_stmt_bind_param($stmt, 'ss', $likeSearch, $likeSearch);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM school");
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$root = '../../';
$pageTitle = 'GradScan | Manage Schools';
$topbarTitle = 'Manage Schools';
$activeNav = 'schools';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/../includes/topbar.php'; ?>


    <main class="gs-content">

        <!-- Page Header -->
        <section class="gs-page-header flex items-start justify-between gap-6">

            <div>
                <h1 class="gs-page-title">
                    Schools
                </h1>

                <p class="gs-page-description">
                    View and manage the schools registered in GradScan.
                </p>
            </div>

            <a
                href="add_school.php"
                class="gs-button-primary whitespace-nowrap"
            >
                + Add School
            </a>

        </section>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="gs-alert gs-alert-success mb-6">
                School deleted successfully.
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
                        for="school-search"
                        class="sr-only"
                    >
                        Search schools
                    </label>

                    <input
                        id="school-search"
                        type="text"
                        name="q"
                        class="gs-input"
                        placeholder="Search by name or code"
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
                            href="super_admin_schools.php"
                            class="gs-button-secondary py-3"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- School Table -->
        <section class="gs-table-wrapper">

            <table class="gs-table">

                <thead>
                    <tr class="bg-gray-50">
                        <th class="gs-table-header px-6 py-4">School Name</th>
                        <th class="gs-table-header px-6 py-4">School Code</th>
                        <th class="gs-table-header px-6 py-4">Status</th>
                        <th class="gs-table-header px-6 py-4">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    <?php if (mysqli_num_rows($result) === 0): ?>

                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">
                                No schools found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <tr class="transition hover:bg-gray-50">

                                <td class="gs-table-cell font-medium text-ascot-dark">
                                    <?= htmlspecialchars($row['school_name']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['school_code']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <span class="gs-badge capitalize <?= $row['status'] === 'active' ? 'gs-badge-success' : 'gs-badge-neutral' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>

                                <td class="gs-table-cell">
                                    <div class="flex items-center gap-2">

                                        <a
                                            href="edit_school.php?id=<?= $row['school_id'] ?>"
                                            class="gs-button-edit"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="delete_school.php?id=<?= $row['school_id'] ?>"
                                            class="gs-button-delete"
                                        >
                                            Delete
                                        </a>

                                    </div>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
