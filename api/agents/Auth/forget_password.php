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
    //Validate API Token for Agent (forwho = 3)
    $token = $api_status_call->ValidateAPITokenSentIN(1, 2);

    // Extract and sanitize agent public key
    $agent_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($agent_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    //Validate send type (email or phone)
    $sendtype = isset($_POST["type"]) ? strtolower($utility_class_call->clean_user_data($_POST["type"], 1)) : '';
    if ($utility_class_call->input_is_invalid($sendtype) || !in_array($sendtype, ["email", "phone"])) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Fetch agent details
    $responseData = $db_call->selectRows(
        "agents",
        "id, email, phoneno, agency_name",
        [
            [
                ["column" => "agentpubkey", "operator" => "=", "value" => $agent_pubkey]
            ]
        ]
    );

    if ($utility_class_call->input_is_invalid($responseData)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    $agent = $responseData[0];
    $agent_id = $agent["id"];
    $agency_name = $agent["agency_name"];
    $email = $agent["email"];
    $phoneno = $agent["phoneno"];

    //Determine destination
    $destination = ($sendtype === "email") ? $email : $phoneno;
    if ($utility_class_call->input_is_invalid($destination)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Generate password reset OTP
    $verificationCode = random_int(100000, 999999);
    $expiryTimestamp = time() + (10 * 60); // 10 minutes expiry
    $expiryTime = date('Y-m-d H:i:s', $expiryTimestamp);

    //verification_type = 3 (Forgot Password)
    $verification_type = 3;
    $method_used = 1; // Email/SMS
    $forwho = 3; // Agent

    //Insert OTP record into system_otps
    $insert_otp = $db_call->insertRow(
        "system_otps",
        [
            "user_id"           => $agent_id,
            "useridentity"      => $destination,
            "token"             => $agent_pubkey,
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
        $greetingText = "Hello $agency_name,";
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
            "expires_in_seconds" => 600
        ];
        $api_status_call->respondOK([$maindata], API_User_Response::$password_reset_otp);
    } else {
        $api_status_call->respondInternalError(API_User_Response::$error_creating_record);
    }

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
