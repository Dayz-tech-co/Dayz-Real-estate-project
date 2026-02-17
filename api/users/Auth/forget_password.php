<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\Mail_SMS_Responses;
use Config\API_User_Response;
use Config\Utility_Functions;

header("Content-Type: application/json");

$apimethod = "POST";

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;
$utility_class_call = new Utility_Functions;
$mail_sms_call = new Mail_SMS_Responses;

if (getenv("REQUEST_METHOD") !== $apimethod) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    // Validate send type (email or phone)
    $sendtype = isset($_POST["type"]) ? strtolower($utility_class_call->clean_user_data($_POST["type"], 1)) : '';
    if ($utility_class_call->input_is_invalid($sendtype) || !in_array($sendtype, ["email", "phone"])) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    $email = isset($_POST["email"]) ? strtolower(trim($utility_class_call->clean_user_data($_POST["email"], 1))) : '';
    $phoneno = isset($_POST["phoneno"]) ? $utility_class_call->clean_user_data($_POST["phoneno"], 1) : '';

    if ($sendtype === "email" && $utility_class_call->input_is_invalid($email)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }
    if ($sendtype === "phone" && $utility_class_call->input_is_invalid($phoneno)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Fetch user details
    $where = ($sendtype === "email")
        ? [[["column" => "email", "operator" => "=", "value" => $email]]]
        : [[["column" => "phoneno", "operator" => "=", "value" => $phoneno]]];

    $responseData = $db_call->selectRows(
        "users",
        "id, email, phoneno, fname, userpubkey",
        $where
    );

    if ($utility_class_call->input_is_invalid($responseData)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    $user = $responseData[0];
    $user_id = $user["id"];
    $fname = $user["fname"];
    $user_email = $user["email"];
    $user_phone = $user["phoneno"];
    $user_pubkey = $user["userpubkey"];

    // Determine destination
    $destination = ($sendtype === "email") ? $user_email : $user_phone;
    if ($utility_class_call->input_is_invalid($destination)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Generate password reset OTP
    $verificationCode = random_int(100000, 999999);
    $expiryTimestamp = time() + 60; // 60 seconds expiry
    $expiryTime = date('Y-m-d H:i:s', $expiryTimestamp);

    // verification_type = 3 (Forgot Password)
    $verification_type = 3;
    $method_used = 1; // Email/SMS
    $forwho = 2; // User

    // Insert OTP record into system_otps
    $insert_otp = $db_call->insertRow(
        "system_otps",
        [
            "user_id"           => $user_id,
            "useridentity"      => $destination,
            "token"             => $user_pubkey,
            "verification_type" => $verification_type,
            "otp"               => $verificationCode,
            "otp_expiration"    => $expiryTime,
            "status"            => 1,
            "method_used"       => $method_used,
            "forwho"            => $forwho
        ]
    );

    if (!$insert_otp) {
        $api_status_call->respondInternalError(API_User_Response::$error_creating_record);
    }

    // Send password reset OTP
    $systemname = $_ENV['APP_NAME'];
    $sent = false;

    if ($sendtype === "email") {
        $subject = "Password Reset Request";
        $messageText = "Use the OTP below to reset your password: {$verificationCode}";
        $messagetitle = $subject;
        $greetingText = "Hello $fname,";
        $mailText = "You requested to reset your password for your $systemname account.<br><br>This code will expire in 10 minutes.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, $verificationCode);

        $sent = $mail_sms_call->sendUserMail($subject, $destination, $messageText, $messageHTML);
    } else {
        $subject = "Password Reset OTP";
        $messageText = "Use this OTP to reset your password: {$verificationCode}";
        $messageHTML = "
            <h3>Password Reset</h3>
            <p>Your OTP is <strong>{$verificationCode}</strong>.</p>
            <p>It expires in 10 minutes.</p>
        ";
        $sent = $mail_sms_call->sendUserMail($subject, $destination, $messageText, $messageHTML);
    }

    // Final Response
    if ($sent) {
        $maindata = [
            "sent_to" => $destination,
            "type" => $sendtype,
            "expires_in_seconds" => 60
        ];
        $api_status_call->respondOK([$maindata], API_User_Response::$password_reset_otp);
    } else {
        $api_status_call->respondInternalError(API_User_Response::$error_creating_record);
    }

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
