<?php
header("Content-Type: application/json");
require "db.php";

/* ================= ONLY POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

/* ================= INPUT ================= */
$email    = trim($_POST['email'] ?? '');
$otp      = trim($_POST['otp'] ?? '');
$password = trim($_POST['password'] ?? '');

/* ================= VALIDATION ================= */
if ($email === '' || $otp === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Email, OTP and password required"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email"
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

/* ================= FETCH USER ================= */
$stmt = $conn->prepare(
    "SELECT id, reset_otp, otp_expiry 
     FROM users 
     WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Email not found"
    ]);
    exit;
}

$user = $res->fetch_assoc();

/* ================= VERIFY OTP ================= */
if ($user['reset_otp'] !== $otp) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid OTP"
    ]);
    exit;
}

/* ================= CHECK EXPIRY ================= */
if (strtotime($user['otp_expiry']) < time()) {
    echo json_encode([
        "status" => "error",
        "message" => "OTP expired"
    ]);
    exit;
}

/* ================= UPDATE PASSWORD ================= */
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$update = $conn->prepare(
    "UPDATE users 
     SET password = ?, reset_otp = NULL, otp_expiry = NULL 
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

/* ================= SUCCESS ================= */
echo json_encode([
    "status" => "success",
    "message" => "Password reset successful. Please login."
]);
