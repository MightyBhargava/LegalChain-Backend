<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");
require "db.php";

/* ================= PHPMailer (MUST BE AT TOP) ================= */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/PHPMailer/Exception.php";
require __DIR__ . "/PHPMailer/PHPMailer.php";
require __DIR__ . "/PHPMailer/SMTP.php";

/* ================= ONLY POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

/* ================= INPUT ================= */
$type  = trim($_POST['type'] ?? '');   // email | phone
$value = trim($_POST['value'] ?? '');

if ($type === '' || $value === '') {
    echo json_encode([
        "status"=>"error",
        "message"=>"Type and value required"
    ]);
    exit;
}

if (!in_array($type, ['email','phone'])) {
    echo json_encode([
        "status"=>"error",
        "message"=>"Type must be email or phone"
    ]);
    exit;
}

/* ================= CHECK USER ================= */
$field = ($type === 'phone') ? 'phone' : 'email';

$stmt = $conn->prepare("SELECT id FROM users WHERE $field=?");
$stmt->bind_param("s", $value);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status"=>"error",
        "message"=> ucfirst($type)." not registered"
    ]);
    exit;
}

$user = $res->fetch_assoc();

/* ================= GENERATE OTP ================= */
$otp    = rand(100000, 999999);
$expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

$upd = $conn->prepare(
    "UPDATE users SET reset_otp=?, otp_expiry=? WHERE id=?"
);
$upd->bind_param("ssi", $otp, $expiry, $user['id']);
$upd->execute();

/* =====================================================
   ================= EMAIL OTP =========================
   ===================================================== */
if ($type === 'email') {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = "smtp.gmail.com";
        $mail->SMTPAuth   = true;
        $mail->Username   = "ravulavenkat759@gmail.com";   // your email
        $mail->Password   = "ktcdyfevweqoowbi";            // app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom("ravulavenkat759@gmail.com", "LegalChain");
        $mail->addAddress($value);

        $mail->isHTML(true);
        $mail->Subject = "LegalChain – Password Reset OTP";

        /* ===== PROFESSIONAL EMAIL BODY ===== */
        $mail->Body = '
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f3f5f7;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0"
          style="background:#ffffff;border-radius:10px;
          box-shadow:0 6px 18px rgba(0,0,0,0.08);overflow:hidden;">
          
          <tr>
            <td style="background:#004D40;padding:24px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;">LegalChain</h1>
              <p style="margin:6px 0 0;color:#cfd8dc;">
                Smart Legal Case Management Platform
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:32px;color:#333;">
              <h2>Password Reset Request</h2>
              <p>Use the OTP below to reset your password:</p>

              <h1 style="color:#004D40;letter-spacing:6px;text-align:center;">
                '.$otp.'
              </h1>

              <p>This OTP is valid for <strong>5 minutes</strong>.</p>
              <p>If you did not request this, ignore this email.</p>

              <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

              <p style="font-size:12px;color:#888;text-align:center;">
                © '.date("Y").' LegalChain. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

        $mail->send();

        echo json_encode([
            "status"=>"success",
            "message"=>"OTP sent to email"
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Email sending failed"
        ]);
        exit;
    }
}

/* =====================================================
   ================= MOBILE OTP ========================
   ===================================================== */
if ($type === 'phone') {

    $apiKey = "Lxrg6e0ImybPSW4piw2EFYfkh8vX1n9TKqDCBAl5JsOjGtZza35V4iD6u107FEHnxUhp3Oyt8YjkfaZW";

    $message = "Your LegalChain OTP is $otp. Valid for 5 minutes.";

    $data = [
        "sender_id" => "TXTIND",
        "message"   => $message,
        "language"  => "english",
        "route"     => "q",
        "numbers"   => $value
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "authorization: $apiKey",
            "content-type: application/json"
        ],
    ]);

    curl_exec($curl);
    curl_close($curl);

    echo json_encode([
        "status"=>"success",
        "message"=>"OTP sent to mobile"
    ]);
    exit;
}
