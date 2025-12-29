<?php
header("Content-Type: application/json");
require "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';

if ($email === '' || $password === '' || $role === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing credentials"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, role, full_name, email, password, document
     FROM users
     WHERE email = ? AND role = ?"
);
$stmt->bind_param("ss", $email, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email or role"
    ]);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid password"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "user" => [
        "id"        => $user['id'],
        "role"      => $user['role'],
        "full_name" => $user['full_name'],
        "email"     => $user['email'],
        "document"  => $user['document']
    ]
]);
