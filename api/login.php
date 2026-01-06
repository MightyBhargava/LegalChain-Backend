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
$identifier = trim($_POST['identifier'] ?? ''); // email or phone
$password   = trim($_POST['password'] ?? '');
$role       = trim($_POST['role'] ?? '');       // client | lawyer

if ($identifier === '' || $password === '' || $role === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing credentials"
    ]);
    exit;
}

/* ================= EMAIL OR PHONE ================= */
$field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? "email" : "phone";

/* ================= FETCH USER ================= */
$sql = "
SELECT id, role, full_name, email, phone, password, bar_id, document
FROM users
WHERE $field = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid credentials"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ================= ROLE CHECK ================= */
$dbRole = strtolower($user['role']);
$inputRole = strtolower($role);

// lawyer/client is treated as lawyer
if ($dbRole === 'lawyer/client') {
    $dbRole = 'lawyer';
}

if ($dbRole !== $inputRole) {
    echo json_encode([
        "status" => "error",
        "message" => "Role mismatch"
    ]);
    exit;
}

/* ================= PASSWORD VERIFY ================= */
if (!password_verify($password, $user['password'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid password"
    ]);
    exit;
}

/* ================= ROLE-BASED RESPONSE ================= */
if ($dbRole === 'client') {

    echo json_encode([
        "status" => "success",
        "role"   => "client",
        "user"   => [
            "id"        => $user['id'],
            "full_name" => $user['full_name'],
            "email"     => $user['email'],
            "phone"     => $user['phone']
        ]
    ]);
    exit;
}

/* ================= LAWYER RESPONSE ================= */
if ($dbRole === 'lawyer') {

    echo json_encode([
        "status" => "success",
        "role"   => "lawyer",
        "user"   => [
            "id"        => $user['id'],
            "full_name" => $user['full_name'],
            "email"     => $user['email'],
            "phone"     => $user['phone'],
            "bar_id"    => $user['bar_id'],
            "document"  => $user['document']
        ]
    ]);
    exit;
}
