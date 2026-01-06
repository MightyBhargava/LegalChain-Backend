<?php
require_once "db.php";
header("Content-Type: application/json");

/* ✅ Read form-urlencoded data */
$email = $_POST['email'] ?? '';
$google_uid = $_POST['google_uid'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$role = $_POST['role'] ?? 'client';

/* ✅ Validation */
if (empty($email) || empty($google_uid)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid Google data"
    ]);
    exit;
}

/* 1️⃣ Check if user already exists */
$stmt = $conn->prepare(
    "SELECT * FROM users WHERE google_uid = ? OR email = ? LIMIT 1"
);
$stmt->bind_param("ss", $google_uid, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $user = $result->fetch_assoc();

    /* 🔄 Link Google UID if missing */
if (empty($user['google_uid'])) {
    $update = $conn->prepare(
        "UPDATE users 
         SET google_uid = ?, auth_provider = 'google' 
         WHERE id = ?"
    );
    $update->bind_param("si", $google_uid, $user['id']);
    $update->execute();

    // 🔄 Re-fetch updated user
    $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt2->bind_param("i", $user['id']);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();
}

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "user" => [
            "id" => (string)$user['id'],
            "email" => $user['email'],
            "full_name" => $user['full_name'],
            "role" => $user['role'],
            "google_uid" => $google_uid
        ]
    ]);
    exit;
}

/* 2️⃣ New Google user → insert */
$insert = $conn->prepare(
    "INSERT INTO users 
    (email, full_name, google_uid, auth_provider, role, status) 
    VALUES (?, ?, ?, 'google', ?, 'active')"
);

$insert->bind_param("ssss", $email, $full_name, $google_uid, $role);

if (!$insert->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "User creation failed"
    ]);
    exit;
}

/* 3️⃣ Return newly created user */
$user_id = $conn->insert_id;

echo json_encode([
    "status" => "success",
    "message" => "Google signup successful",
    "user" => [
        "id" => (string)$user_id,
        "email" => $email,
        "full_name" => $full_name,
        "role" => $role,
        "google_uid" => $google_uid
    ]
]);
