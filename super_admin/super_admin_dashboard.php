<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$root = '../';
$pageTitle = 'GradScan | Super Admin Dashboard';
$topbarTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="gs-main">

    <?php require __DIR__ . '/includes/topbar.php'; ?>


    <main class="gs-content">

        <section class="gs-page-header">

            <h1 class="gs-page-title">
                Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>.
            </h1>

            <p class="gs-page-description">
                Manage schools, accounts, and campus-wide reports from here.
            </p>

        </section>


        <!-- Dashboard Cards -->
        <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">

            <?php
            $cards = [
                ['schools', 'Manage Schools', 'Add, edit, and remove schools.'],
                ['admins', 'Manage School Admins', 'Create and manage school admin accounts.'],
                ['operators', 'Manage Operators', 'Create and manage ceremony operator accounts.'],
                ['reports', 'View Reports', 'See school totals and campus-wide scan logs.'],
            ];
            ?>

            <?php foreach ($cards as [$key, $title, $description]): ?>

                <a href="<?= $navItems[$key]['href'] ?>" class="gs-dashboard-card group">

                    <div class="gs-card-icon">

                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            viewBox="0 0 24 24"
                        >
                            <?= $navItems[$key]['icon'] ?>
                        </svg>

                    </div>

                    <h3 class="font-semibold text-gray-800 group-hover:text-ascot-green">
                        <?= $title ?>
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-gray-500">
                        <?= $description ?>
                    </p>

                </a>

            <?php endforeach; ?>

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
