<?php

require_once '../config/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'GradScan | Dashboard';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

?>

<div class="gs-main">

    <!-- Top Navigation -->
    <header class="gs-topbar">

        <div>
            <h2 class="gs-topbar-title">
                Dashboard
            </h2>

            <p class="gs-topbar-subtitle">
                School Administration
            </p>
        </div>


        <!-- User -->
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


    <!-- Content -->
    <main class="gs-content">

        <section class="gs-page-header">

            <h1 class="gs-page-title">
                Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>.
            </h1>

            <p class="gs-page-description">
                Manage graduates, layouts, and graduation scan records from here.
            </p>

        </section>


        <!-- Dashboard Cards -->
        <section class="grid gap-6 md:grid-cols-3">


            <!-- Graduate Management -->
            <a href="graduates.php" class="gs-dashboard-card group">

                <div class="gs-card-icon">

                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19a4 4 0 0 0-8 0M11 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10 8a4 4 0 0 0-5-3.87M17 3.13a4 4 0 0 1 0 7.75"
                        />
                    </svg>

                </div>

                <h3 class="font-semibold text-gray-800 group-hover:text-ascot-green">
                    Graduate Management
                </h3>

                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Add, edit, and manage graduate records.
                </p>

            </a>


            <!-- Layout Management -->
            <a href="layouts.php" class="gs-dashboard-card group">

                <div class="gs-card-icon">

                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24"
                    >
                        <rect
                            x="3"
                            y="4"
                            width="18"
                            height="16"
                            rx="2"
                        />

                        <path
                            stroke-linecap="round"
                            d="M3 9h18M8 9v11"
                        />
                    </svg>

                </div>

                <h3 class="font-semibold text-gray-800 group-hover:text-ascot-green">
                    Layout Management
                </h3>

                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Manage and customize graduation layouts.
                </p>

            </a>


            <!-- Scan History -->
            <a href="scan_history.php" class="gs-dashboard-card group">

                <div class="gs-card-icon">

                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 8v4l3 2"
                        />

                        <circle
                            cx="12"
                            cy="12"
                            r="9"
                        />
                    </svg>

                </div>

                <h3 class="font-semibold text-gray-800 group-hover:text-ascot-green">
                    Scan History
                </h3>

                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Review graduation scanning records and activity.
                </p>

            </a>

        </section>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>