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
                    Operator
                </p>
            </div>

        </a>

    </div>


    <!-- Navigation -->
    <nav class="gs-sidebar-nav">

        <p class="gs-sidebar-section">
            Ceremony
        </p>


        <!-- Dashboard -->
        <a
            href="dashboard.php"
            class="gs-sidebar-link <?= $currentPage === 'dashboard.php' ? 'gs-sidebar-link-active' : '' ?>"
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
                    d="M3 12l9-8 9 8M5 10v10h14V10"
                />
            </svg>

            <span>Dashboard</span>

        </a>


        <!-- Scanner -->
        <a
            href="scanner.php"
            class="gs-sidebar-link <?= $currentPage === 'scanner.php' ? 'gs-sidebar-link-active' : '' ?>"
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
                    d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"
                />
            </svg>

            <span>QR Scanner</span>

        </a>

    </nav>

</aside>
