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
$role       = strtolower(trim($_POST['role'] ?? ''));
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$password  = trim($_POST['password'] ?? '');

$bar_id   = trim($_POST['bar_id'] ?? '');
$country  = trim($_POST['country'] ?? '');
$state    = trim($_POST['state'] ?? '');
$district = trim($_POST['district'] ?? '');
$address  = trim($_POST['address'] ?? '');

/* ================= BASIC VALIDATION ================= */
if ($role === '' || $full_name === '' || $email === '' || $phone === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "All required fields must be filled"
    ]);
    exit;
}

if (!in_array($role, ["client", "lawyer"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Role must be client or lawyer"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email format"
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

/* ================= LAWYER VALIDATION ================= */
if ($role === "lawyer") {
    if ($bar_id === '' || $country === '' || $state === '' || $district === '' || $address === '') {
        echo json_encode([
            "status" => "error",
            "message" => "All professional fields are required for lawyer"
        ]);
        exit;
    }

    if (!isset($_FILES['document'])) {
        echo json_encode([
            "status" => "error",
            "message" => "Document upload required for lawyer"
        ]);
        exit;
    }
}

/* ================= CHECK DUPLICATE ================= */
$check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$check->bind_param("ss", $email, $phone);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email or phone already registered"
    ]);
    exit;
}

/* ================= FILE UPLOAD ================= */
$documentPath = null;

if ($role === "lawyer") {

    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
    $fileName = $_FILES['document']['name'];
    $fileTmp  = $_FILES['document']['tmp_name'];
    $fileSize = $_FILES['document']['size'];

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        echo json_encode([
            "status" => "error",
            "message" => "Only PDF, JPG, JPEG, PNG allowed"
        ]);
        exit;
    }

    if ($fileSize > 5 * 1024 * 1024) {
        echo json_encode([
            "status" => "error",
            "message" => "File must be less than 5MB"
        ]);
        exit;
    }

    $newName = uniqid("doc_") . "." . $ext;
    $uploadDir = "uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $documentPath = $uploadDir . $newName;

    if (!move_uploaded_file($fileTmp, $documentPath)) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to upload document"
        ]);
        exit;
    }
}

/* ================= INSERT ================= */
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO users 
    (role, full_name, email, phone, password, bar_id, country, state, district, address, document) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "sssssssssss",
    $role,
    $full_name,
    $email,
    $phone,
    $hashedPassword,
    $bar_id,
    $country,
    $state,
    $district,
    $address,
    $documentPath
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => $role === "lawyer"
            ? "Registration successful. Verification pending."
            : "Registration successful"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database insert failed"
    ]);
}
?>
