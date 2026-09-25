<?php

require_once 'config/config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, school_id, password_hash, role, full_name
         FROM `user`
         WHERE username = ?"
    );

    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password_hash'])) {

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['school_id'] = $user['school_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        if ($user['role'] === 'operator') {
            header('Location: operator/dashboard.php');
        } elseif ($user['role'] === 'super_admin') {
            header('Location: super_admin/super_admin_dashboard.php');
        } else {
            header('Location: school_admin/dashboard.php');
        }
        exit;

    } else {
        $error = 'Invalid username or password';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Tailwind -->
    <link rel="stylesheet" href="frontend/dist/output.css">

    <title>GradScan - Login</title>
</head>

<body class="min-h-screen bg-slate-100 font-poppins text-ascot-text">

    <main class="flex min-h-screen items-center justify-center px-6 py-12">

        <div class="w-full max-w-md">

            <!-- Login Card -->
            <div class="overflow-hidden rounded-2xl bg-white shadow-xl">

                <!-- Green Header -->
                <div class="bg-ascot-dark px-8 py-8 text-center">

                    <!-- ASCOT Logo -->
                    <div class="mx-auto mb-5 flex h-24 w-24 items-center justify-center rounded-full bg-white p-3 shadow-md">
                        <img
                            src="photos/ascot_logo.png"
                            alt="ASCOT Logo"
                            class="h-full w-full object-contain"
                        >
                    </div>

                    <h1 class="text-3xl font-bold tracking-tight text-white">
                        GradScan
                    </h1>

                    <p class="mt-2 text-sm font-medium text-green-100">
                        Graduation Management System
                    </p>

                </div>

                <!-- Login Form Area -->
                <div class="px-8 py-8">

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-ascot-dark">
                            Welcome back
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Sign in to continue to GradScan.
                        </p>
                    </div>

                    <!-- Error Message -->
                    <?php if (isset($error)): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" class="space-y-5">

                        <!-- Username -->
                        <div>
                            <label
                                for="username"
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                Username
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                required
                                autocomplete="username"
                                placeholder="Enter your username"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-ascot-green focus:ring-2 focus:ring-green-100"
                            >
                        </div>

                        <!-- Password -->
                        <div>
                            <label
                                for="password"
                                class="mb-2 block text-sm font-semibold text-gray-700"
                            >
                                Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-ascot-green focus:ring-2 focus:ring-green-100"
                            >
                        </div>

                        <!-- Login Button -->
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-ascot-green px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-ascot-dark focus:outline-none focus:ring-2 focus:ring-ascot-green focus:ring-offset-2"
                        >
                            Login
                        </button>

                    </form>

                </div>

            </div>

            <!-- Footer -->
            <p class="mt-6 text-center text-xs text-gray-500">
                GradScan · Graduation Management System
            </p>

        </div>

    </main>

</body>
</html>