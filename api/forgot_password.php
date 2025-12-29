<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");
require "db.php";

/* ================= PHPMailer ================= */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ✅ FIXED PATH (NO src/) */
require __DIR__ . "/PHPMailer/Exception.php";
require __DIR__ . "/PHPMailer/PHPMailer.php";
require __DIR__ . "/PHPMailer/SMTP.php";

/* ================= ONLY POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    echo json_encode(["status"=>"error","message"=>"Email required"]);
    exit;
}

/* ================= CHECK EMAIL ================= */
$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status"=>"error","message"=>"Email not registered"]);
    exit;
}

/* ================= TOKEN ================= */
$token  = bin2hex(random_bytes(32));
$expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

$upd = $conn->prepare(
    "UPDATE users SET reset_token=?, reset_token_expiry=? WHERE email=?"
);
$upd->bind_param("sss", $token, $expiry, $email);
$upd->execute();

/* ================= RESET LINK ================= */
/* CHANGE IP IF NEEDED */
$resetLink = "https://legalchain.app/reset?token=" . $token;


/* ================= SEND EMAIL ================= */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "ravulavenkat759@gmail.com";   // ✅ your gmail
    $mail->Password   = "ktcdyfevweqoowbi";            // ✅ app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom("ravulavenkat759@gmail.com", "LegalChain");
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Reset Your LegalChain Password";
$mail->Body = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Reset Your Password</title>
</head>

<body style="margin:0;padding:0;background-color:#f3f5f7;font-family:Arial,Helvetica,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
      <td align="center">

        <!-- MAIN CONTAINER -->
        <table width="620" cellpadding="0" cellspacing="0"
               style="background:#ffffff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.08);overflow:hidden;">

          <!-- HEADER -->
          <tr>
            <td style="background:#004D40;padding:24px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;font-size:26px;">LegalChain</h1>
              <p style="margin:6px 0 0;color:#cfd8dc;font-size:14px;">
                Smart Legal Case Management Platform
              </p>
            </td>
          </tr>

          <!-- CONTENT -->
          <tr>
            <td style="padding:32px;color:#333333;">

              <h2 style="margin-top:0;font-size:22px;">Reset Your Password</h2>

              <p style="font-size:15px;line-height:1.6;">
                We received a request to reset the password for your
                <strong>LegalChain</strong> account.
              </p>

              <p style="font-size:15px;line-height:1.6;">
                LegalChain helps lawyers and clients manage cases, documents,
                hearings, and legal communication securely in one place.
              </p>

              <p style="font-size:15px;line-height:1.6;">
                Click the button below to create a new password:
              </p>

              <!-- BUTTON -->
              <p style="text-align:center;margin:32px 0;">
                <a href="'.$resetLink.'"
                   style="
                     background:#004D40;
                     color:#ffffff;
                     padding:14px 36px;
                     text-decoration:none;
                     font-size:16px;
                     font-weight:bold;
                     border-radius:8px;
                     display:inline-block;">
                  Reset Password
                </a>
              </p>

              <p style="font-size:14px;color:#555;">
                ⏳ This reset link will expire in <strong>15 minutes</strong>.
              </p>

              <p style="font-size:14px;color:#555;">
                If you did not request this password reset, you can safely ignore
                this email. Your account will remain secure.
              </p>

              <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

              <!-- FOOTER -->
              <p style="font-size:12px;color:#888;text-align:center;">
                © '.date("Y").' LegalChain. All rights reserved.
              </p>

              <p style="font-size:12px;color:#888;text-align:center;">
                This email was sent automatically. Please do not reply.
              </p>

            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
';
    $mail->send();

    echo json_encode([
        "status"=>"success",
        "message"=>"Reset link sent to email"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status"=>"error",
        "message"=>"Email sending failed",
        "error"=>$mail->ErrorInfo
    ]);
}
