<?php
header("Content-Type: application/json");

// 🔐 Groq API key
$GROQ_API_KEY = getenv("GROQ_API_KEY");

// Read input
$data = json_decode(file_get_contents("php://input"), true);
$question = trim($data["question"] ?? "");

if ($question === "") {
    echo json_encode([
        "success" => false,
        "reply" => "Question is required"
    ]);
    exit;
}

// Groq request payload
$payload = [
    "model" => "llama-3.1-8b-instant",
    "messages" => [
        ["role" => "system", "content" => "You are a professional Indian legal assistant."],
        ["role" => "user", "content" => $question]
    ],
    "temperature" => 0.3
];

// cURL call
$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $GROQ_API_KEY"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode([
        "success" => false,
        "reply" => "AI connection failed"
    ]);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

// ✅ THIS IS THE KEY FIX
$reply = $result["choices"][0]["message"]["content"] ?? null;

if (!$reply) {
    echo json_encode([
        "success" => false,
        "reply" => "AI did not respond"
    ]);
    exit;
}

// ✅ FINAL RESPONSE FOR ANDROID
echo json_encode([
    "success" => true,
    "reply" => trim($reply)
]);
