<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="gs-sidebar">

    <!-- Brand -->
    <div class="gs-sidebar-brand">

        <a href="dashboard.php" class="flex items-center gap-3">

            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white p-1.5">
                <img
                    src="../photos/ascot_logo.png"
                    alt="ASCOT Logo"
                    class="h-full w-full object-contain"
                >
            </div>

            <div>
                <h1 class="text-lg font-bold tracking-tight">
                    GradScan
                </h1>

                <p class="text-xs text-green-200">
                    School Admin
                </p>
            </div>

        </a>

    </div>


    <!-- Navigation -->
    <nav class="gs-sidebar-nav">

        <p class="gs-sidebar-section">
            Management
        </p>


        <!-- Graduate Management -->
        <a
            href="graduates.php"
            class="gs-sidebar-link <?= $currentPage === 'graduates.php' ? 'gs-sidebar-link-active' : '' ?>"
        >

            <svg
                class="h-5 w-5 shrink-0"
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

            <span>Graduate Management</span>

        </a>


        <!-- Layout Management -->
        <a
            href="layouts.php"
            class="gs-sidebar-link <?= $currentPage === 'layouts.php' ? 'gs-sidebar-link-active' : '' ?>"
        >

            <svg
                class="h-5 w-5 shrink-0"
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

            <span>Layout Management</span>

        </a>


        <!-- Scan History -->
        <a
            href="scan_history.php"
            class="gs-sidebar-link <?= $currentPage === 'scan_history.php' ? 'gs-sidebar-link-active' : '' ?>"
        >

            <svg
                class="h-5 w-5 shrink-0"
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

            <span>Scan History</span>

        </a>

    </nav>

</aside>