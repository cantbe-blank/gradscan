<!-- Top Navigation -->
<header class="gs-topbar">

    <div>
        <h2 class="gs-topbar-title">
            <?= htmlspecialchars($topbarTitle ?? 'Dashboard') ?>
        </h2>

        <p class="gs-topbar-subtitle">
            Super Administration
        </p>
    </div>


    <!-- User -->
    <div class="flex items-center gap-5">

        <div class="text-right">

            <p class="gs-user-name">
                <?= htmlspecialchars($_SESSION['full_name']) ?>
            </p>

            <p class="gs-user-role">
                Super Admin
            </p>

        </div>

        <a
            href="<?= $root ?>logout.php"
            class="gs-button-danger"
        >
            Logout
        </a>

    </div>

</header>
