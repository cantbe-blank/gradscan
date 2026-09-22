<?php

require_once 'config/config.php';

$username = 'admin';
$password = 'admin123';
$full_name = 'School Administrator';
$role = 'school_admin';
$status = 'active';

// Get the school ID
$school_query = mysqli_query($conn, "SELECT school_id FROM school LIMIT 1");

if (!$school_query || mysqli_num_rows($school_query) === 0) {
    die("No school found. Create a school first.");
}

$school = mysqli_fetch_assoc($school_query);
$school_id = $school['school_id'];

// Hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Create the user
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO user 
    (school_id, username, password_hash, full_name, role, status)
    VALUES (?, ?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "isssss",
    $school_id,
    $username,
    $password_hash,
    $full_name,
    $role,
    $status
);

if (mysqli_stmt_execute($stmt)) {
    echo "Admin account created successfully!<br><br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "Role: school_admin";
} else {
    echo "Error: " . mysqli_error($conn);
}

?>