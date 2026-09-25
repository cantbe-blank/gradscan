<?php

require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$search = trim($_GET['q'] ?? '');

if ($search !== '') {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, photo
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
        "SELECT graduate_id, school_id, student_id, first_name, middle_name, last_name, suffix, course, major, photo
         FROM graduate
         WHERE school_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $school_id);
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

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

            <div class="overflow-x-auto">

                <table class="gs-table">

                    <thead>

                        <tr class="bg-gray-50">

                            <th class="gs-table-header px-6 py-4">
                                Student ID
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                First Name
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Middle Name
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Last Name
                            </th>

                            <th class="gs-table-header px-6 py-4">
                                Suffix
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

                            <tr class="transition hover:bg-gray-50">

                                <td class="gs-table-cell font-medium text-ascot-dark">
                                    <?= htmlspecialchars($row['student_id']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['first_name']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['middle_name']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['last_name']) ?>
                                </td>

                                <td class="gs-table-cell">
                                    <?= htmlspecialchars($row['suffix']) ?>
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

<?php require_once 'includes/footer.php'; ?>