<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");
require "db.php";

/* ONLY POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

/* INPUTS */
$token    = trim($_POST['token'] ?? '');
$password = trim($_POST['password'] ?? '');

/* VALIDATION */
if ($token === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Token and password required"
    ]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 6 characters"
    ]);
    exit;
}

/* 1️⃣ CHECK TOKEN */
$check = $conn->prepare(
    "SELECT id, reset_token_expiry 
     FROM users 
     WHERE reset_token = ?"
);
$check->bind_param("s", $token);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or used token"
    ]);
    exit;
}

$user = $res->fetch_assoc();

/* 2️⃣ CHECK EXPIRY */
if (strtotime($user['reset_token_expiry']) < time()) {
    echo json_encode([
        "status" => "error",
        "message" => "Reset link expired"
    ]);
    exit;
}

/* 3️⃣ UPDATE PASSWORD */
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$update = $conn->prepare(
    "UPDATE users 
     SET password = ?, 
         reset_token = NULL, 
         reset_token_expiry = NULL 
     WHERE id = ?"
);
$update->bind_param("si", $hashedPassword, $user['id']);

if (!$update->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Password update failed"
    ]);
    exit;
}

/* SUCCESS */
echo json_encode([
    "status" => "success",
    "message" => "Password reset successful. Please login."
]);
