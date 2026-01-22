<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_User_Response;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;
use PHPMailer\PHPMailer\PHPMailer;

$apimethod = "POST";
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;

if (getenv('REQUEST_METHOD') === $apimethod) {
    try {
        $verifytype = 3;
        $email = isset($_POST['email']) ? $utility_class_call->clean_user_data($_POST['email']) : '';

        // look for user
        $getuserattached = $db_call_class->selectRows(
            "admins",
            "email,id",
            [
                [
                    'column' => 'email',
                    'operator' => '=',
                    'value' => $email
                ]
            ]
        );

        if ($utility_class_call->input_is_invalid($getuserattached)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$userNotFound);
            exit;
        }

        $userdata = $getuserattached[0];
        $userid = $userdata['id'];

        // check if OTP already sent within 1 minute
        $getoldotpsent = $db_call_class->selectRows(
            "system_otps",
            "created_at",
            [[
                ['column' => 'TIMESTAMPDIFF(MINUTE, created_at, NOW())', 'operator' => '<', 'value' => 1],
                ['column' => 'user_id', 'operator' => '=', 'value' => $userid],
                ['column' => 'verification_type', 'operator' => '=', 'value' => $verifytype],
                'operator' => 'AND'
            ]]
        );

        if (!$utility_class_call->input_is_invalid($getoldotpsent)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$otpsentalready);
            exit;
        }

        // prepare OTP and token
        $otp = $db_call_class->createUniqueRandomStringForATableCol(4, "system_otps", "otp", "", true, false, false);
        $token = $db_call_class->createUniqueRandomStringForATableCol(18, "system_otps", "token", "", true, true, true);

        $createdOtpId = $db_call_class->insertRow("system_otps", [
            "user_id" => $userid,
            "useridentity" => $email,
            "token" => $token,
            "verification_type" => $verifytype,
            "otp" => $otp,
            "forwho" => 1,
            "method_used" => 1,
            "status" => 0,
        ]);
        if ($createdOtpId > 0) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'sandbox.smtp.mailtrap.io';
                $mail->SMTPAuth = true;
                $mail->Port = 2525;
                $mail->Username = 'e1d9d85837a4ed';   // your Mailtrap username
                $mail->Password = 'd8803778400680';   // your Mailtrap password
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

                $mail->setFrom('noreply@botsproject.com', 'Botspay Test');
                $mail->addAddress($email, 'User');

                $mail->isHTML(true);
                $mail->Subject = "Your OTP Code";
                $mail->Body    = "Hello,<br><br>Your OTP code is: <b>{$otp}</b><br><br>Token: {$token}";
                $mail->AltBody = "Your OTP code is: {$otp} | Token: {$token}";

                if ($mail->send()) {
                    echo "<pre>✅ Mail sent successfully (check Mailtrap inbox)!</pre>";
                } else {
                    echo "<pre>❌ Mailer Error: " . $mail->ErrorInfo . "</pre>";
                }
            } catch (\Exception $e) {
                echo "<pre>❌ Exception: " . $e->getMessage() . "</pre>";
            }
            exit;
        } else {
            $api_status_code_class_call->respondInternalError("Could not save OTP record");
            exit;
        }
    } catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
