<?php
header("Content-Type: application/json");
require "db.php";

// ---------- REQUIRED ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

// ---------- FORM DATA ----------
$role      = $_POST['role'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$email     = $_POST['email'] ?? '';
$password  = $_POST['password'] ?? '';

$bar_id    = $_POST['bar_id'] ?? null;
$country   = $_POST['country'] ?? null;
$state     = $_POST['state'] ?? null;
$district  = $_POST['district'] ?? null;
$address   = $_POST['address'] ?? null;

// ---------- PASSWORD HASH ----------
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// ---------- FILE UPLOAD ----------
$documentPath = null;

if ($role === "lawyer" && isset($_FILES['document'])) {

    $uploadDir = __DIR__ . "/uploads/";

    // create uploads folder if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES['document']['name']);
    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['document']['tmp_name'], $targetFile)) {
        $documentPath = "uploads/" . $fileName;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "File upload failed"
        ]);
        exit;
    }
}

// ---------- INSERT INTO DATABASE ----------
$sql = "INSERT INTO users 
(role, full_name, email, password, bar_id, country, state, district, address, document)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssssssssss",
    $role,
    $full_name,
    $email,
    $hashedPassword,
    $bar_id,
    $country,
    $state,
    $district,
    $address,
    $document
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Registration successful"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database insert failed"
    ]);
}
