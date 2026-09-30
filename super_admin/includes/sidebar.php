<?php
// $root: relative path to the project root; $activeNav: dashboard|schools|admins|operators|reports
$sa = $root . 'super_admin/';

$navItems = [
    'schools' => [
        'href'  => $sa . 'schools/super_admin_schools.php',
        'label' => 'Manage Schools',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>',
    ],
    'admins' => [
        'href'  => $sa . 'admins/school_admins.php',
        'label' => 'Manage School Admins',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 0 0-8 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path stroke-linecap="round" d="m12 14-1 3 1 2 1-2-1-3Z"/>',
    ],
    'operators' => [
        'href'  => $sa . 'operators/operators.php',
        'label' => 'Manage Operators',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/>',
    ],
    'reports' => [
        'href'  => $sa . 'reports.php',
        'label' => 'View Reports',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ],
];
?>
<aside class="gs-sidebar">

    <!-- Brand -->
    <div class="gs-sidebar-brand">

        <a href="<?= $sa ?>super_admin_dashboard.php" class="flex items-center gap-3">

            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white p-1.5">
                <img
                    src="<?= $root ?>photos/ascot_logo.png"
                    alt="ASCOT Logo"
                    class="h-full w-full object-contain"
                >
            </div>

            <div>
                <h1 class="text-lg font-bold tracking-tight">
                    GradScan
                </h1>

                <p class="text-xs text-green-200">
                    Super Admin
                </p>
            </div>

        </a>

    </div>


    <!-- Navigation -->
    <nav class="gs-sidebar-nav">

        <p class="gs-sidebar-section">
            Management
        </p>

        <?php foreach ($navItems as $key => $item): ?>

            <a
                href="<?= $item['href'] ?>"
                class="gs-sidebar-link <?= ($activeNav ?? '') === $key ? 'gs-sidebar-link-active' : '' ?>"
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    viewBox="0 0 24 24"
                >
                    <?= $item['icon'] ?>
                </svg>

                <span><?= $item['label'] ?></span>

            </a>

        <?php endforeach; ?>

    </nav>

</aside>
