<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '../../../config/config.php';

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT user.*, school.school_name, school.school_code
    FROM user
    LEFT JOIN school ON user.school_id = school.school_id
    WHERE user.role = 'school_admin'
";

if ($search !== '') {
    $sql .= " AND (user.username LIKE ? OR user.full_name LIKE ?)";
    $likeSearch = '%' . $search . '%';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $likeSearch, $likeSearch);
} else {
    $stmt = mysqli_prepare($conn, $sql);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$root = '../../';
$pageTitle = 'GradScan | Manage School Admins';
$topbarTitle = 'Manage School Admins';
$activeNav = 'admins';

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
                    School Admins
                </h1>

                <p class="gs-page-description">
                    Manage the admin accounts assigned to each school.
                </p>
            </div>

            <a
                href="create_school_admin.php"
                class="gs-button-primary whitespace-nowrap"
            >
                + Create Admin Account
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
                        for="account-search"
                        class="sr-only"
                    >
                        Search accounts
                    </label>

                    <input
                        id="account-search"
                        type="text"
                        name="q"
                        class="gs-input"
                        placeholder="Search by username or name"
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
                            href="school_admins.php"
                            class="gs-button-secondary py-3"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- Account Table -->
        <section class="gs-table-wrapper">

            <table class="gs-table">

                <thead>
                    <tr class="bg-gray-50">
                        <th class="gs-table-header px-6 py-4">Username</th>
                        <th class="gs-table-header px-6 py-4">Full Name</th>
                        <th class="gs-table-header px-6 py-4">Assigned School</th>
                        <th class="gs-table-header px-6 py-4">Status</th>
                        <th class="gs-table-header px-6 py-4">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    <?php if (mysqli_num_rows($result) === 0): ?>

                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">
                                No school admins found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <tr class="transition hover:bg-gray-50">

                                <td class="gs-table-cell font-medium text-ascot-dark">
                                    <?= htmlspecialchars($row['username']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['full_name']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?php if ($row['school_name']): ?>
                                        <?= htmlspecialchars($row['school_name'] . ' (' . $row['school_code'] . ')') ?>
                                    <?php else: ?>
                                        <span class="italic text-gray-400">Unassigned</span>
                                    <?php endif; ?>
                                </td>

                                <td class="gs-table-cell">
                                    <span class="gs-badge capitalize <?= $row['status'] === 'active' ? 'gs-badge-success' : 'gs-badge-neutral' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>

                                <td class="gs-table-cell">
                                    <div class="flex items-center gap-2">

                                        <a
                                            href="edit_school_admin.php?id=<?= $row['user_id'] ?>"
                                            class="gs-button-edit"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="../deactivate_account.php?id=<?= $row['user_id'] ?>"
                                            class="<?= $row['status'] === 'active' ? 'gs-button-delete' : 'gs-button-view' ?>"
                                        >
                                            <?= $row['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
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
