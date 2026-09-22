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
    <title>Login Page</title>
</head>
<body>
    <h1>Login</h1>
    <?php if (isset($error)): ?>
        <p style="color:red;"><?= $error ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>Username</label>
        <input type="text" name="username">
        <label for="">Password</label>
        <input type="password" name="password">
        <button type="submit">Login</button>
        <a href="./register.php">register</a>
    </form>
</body>
</html>