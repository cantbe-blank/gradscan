<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'GradScan | Operator Dashboard';

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
                Ceremony Operator
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
                Scan graduate QR codes and show them on the audience screen.
            </p>

        </section>


        <section class="gs-card mx-auto max-w-2xl px-8 py-12 text-center">

            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-50 text-ascot-green">

                <svg
                    class="h-10 w-10"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"
                    />
                </svg>

            </div>

            <h3 class="text-xl font-semibold text-gray-800">
                Ready to scan graduate QR codes
            </h3>

            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500">
                Plug the SM8070 QR scanner into a USB port and connect the audience
                display (projector or second monitor) before starting.
            </p>

            <a href="scanner.php" class="gs-button-primary mt-8 inline-flex items-center gap-2 px-6">

                <svg
                    class="h-5 w-5"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path d="M8 5.14v13.72a1 1 0 0 0 1.52.85l10.6-6.86a1 1 0 0 0 0-1.7L9.52 4.29A1 1 0 0 0 8 5.14Z" />
                </svg>

                Start Scanner

            </a>

        </section>

    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
