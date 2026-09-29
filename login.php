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

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-3 pr-11 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-ascot-green focus:ring-2 focus:ring-green-100"
                                >
                                <button
                                    type="button"
                                    id="togglePassword"
                                    aria-label="Toggle password visibility"
                                    class="text-gray-400 hover:text-gray-600 focus:outline-none"
                                    style="position:absolute; top:50%; right:0.75rem; transform:translateY(-50%); display:flex; align-items:center; background:none; border:none; padding:0; cursor:pointer;"
                                    onclick="
                                        const inp = document.getElementById('password');
                                        const isHidden = inp.type === 'password';
                                        inp.type = isHidden ? 'text' : 'password';
                                        document.getElementById('eyeOpen').classList.toggle('hidden', !isHidden);
                                        document.getElementById('eyeClosed').classList.toggle('hidden', isHidden);
                                    "
                                >
                                    <!-- Eye open (shown by default) -->
                                    <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <!-- Eye closed (hidden by default) -->
                                    <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.33-3.836M6.53 6.53A9.97 9.97 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.357 2.604M6.53 6.53L3 3m3.53 3.53l11.94 11.94M17.47 17.47L21 21" />
                                    </svg>
                                </button>
                            </div>
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