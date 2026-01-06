<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");
require "db.php";

/* ================= ONLY POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

/* ================= INPUTS ================= */
$type     = $_POST['type'] ?? '';     // email | phone
$value    = trim($_POST['value'] ?? '');
$otp      = trim($_POST['otp'] ?? '');
$password = trim($_POST['password'] ?? '');

/* ================= VALIDATION ================= */
if ($type === '' || $value === '' || $otp === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Type, value, OTP and password required"
    ]);
    exit;
}

if (!in_array($type, ['email', 'phone'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid type"
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
$field = ($type === "phone") ? "phone" : "email";

$stmt = $conn->prepare(
    "SELECT id, reset_otp, otp_expiry 
     FROM users 
     WHERE $field = ?"
);
$stmt->bind_param("s", $value);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => ucfirst($type)." not registered"
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
     SET password = ?, 
         reset_otp = NULL, 
         otp_expiry = NULL 
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
