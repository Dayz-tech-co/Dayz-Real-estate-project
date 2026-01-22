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

header("Content-type: application/json");

if (getenv("REQUEST_METHOD") !== $apimethod) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    //Validate API Token for User (1, 3)
    $token = $api_status_call->ValidateAPITokenSentIN(1, 3);

    // Extract and sanitize user public key
    $user_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($user_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    // Validate send type (email or phone)
    $sendtype = isset($_POST["type"]) ? strtolower($utility_class_call->clean_user_data($_POST["type"], 1)) : '';
    if ($utility_class_call->input_is_invalid($sendtype) || !in_array($sendtype, ["email", "phone"])) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Fetch user details
    $responseData = $db_call->selectRows(
        "users",
        "id, fname, email, phoneno, email_verified, phone_verified",
        [
            [
                ["column" => "userpubkey", "operator" => "=", "value" => $user_pubkey]
            ]
        ]
    );

    if ($utility_class_call->input_is_invalid($responseData)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    $user = $responseData[0];
    $user_id = $user["id"];
    $fullname = $user["fname"];
    $email = $user["email"];
    $phoneno = $user["phoneno"];


    // 🚨 Check if already verified
    if (
        ($sendtype === "email" && $user["email_verified"] === "verified") ||
        ($sendtype === "phone" && $user["phone_verified"] === "verified")
    ) {
        $api_status_call->respondOK([], ucfirst($sendtype) . " already verified");
        exit;
    }


    //Determine destination
    $destination = ($sendtype === "email") ? $email : $phoneno;
    if ($utility_class_call->input_is_invalid($destination)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Generate verification code (6-digit OTP)
    $verificationCode = random_int(100000, 999999);

    // Expiry (5 minutes)
    $expiryTimestamp = time() + (5 * 60);
    $expiryTime = date('Y-m-d H:i:s', $expiryTimestamp);

    //Insert OTP record
    $insert_otp = $db_call->insertRow(
        "system_otps",
        [
            "user_id"           => $user_id,
            "useridentity"      => $destination,
            "token"             => $user_pubkey,
            "verification_type" => ($sendtype === "email") ? 1 : 2, // 1=Email, 2=SMS
            "otp"               => $verificationCode,
            "otp_expiration"    => $expiryTime,
            "status"            => 1, // active
            "method_used"       => 1,
            "forwho"            => 2  //users
        ]
    );

    if (!$insert_otp) {
        $api_status_call->respondInternalError(API_User_Response::$error_creating_record);
    }

    // Send verification (Email or SMS)
    $systemname = $_ENV['APP_NAME'];
    $sent = false;

    if ($sendtype === "email") {
        $subject = Mail_SMS_Responses::sendOTPSubject($systemname);
        $messageText = Mail_SMS_Responses::sendOTPText($verificationCode);
        $messagetitle = $subject;
        $greetingText = "Hello $fullname,";
        $mailText = "Here’s your verification code to continue using your $systemname account.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, $verificationCode);

        $sent = $mail_sms_call->sendUserMail($subject, $destination, $messageText, $messageHTML);
    } else {
        // Placeholder for SMS
        $subject = "OTP Verification";
        $msgintext = Mail_SMS_Responses::sendOTPText($verificationCode);
        $messageinhtml = "
            <h3>OTP Verification</h3>
            <p>Your verification code is <strong>$verificationCode</strong>.</p>
            <p>Please use this code to complete your verification process.</p>
            <br>
            <p>Thank you,<br>The " . $_ENV['APP_NAME'] . " Team</p>
        ";
        $sent = $mail_sms_call->sendUserMail($subject, $destination, $msgintext, $messageinhtml);
    }

    //Final response
    if ($sent) {
        $maindata = [
            "sent_to" => $destination,
            "type" => $sendtype,
            "expires_in_seconds" => 300
        ];
        $api_status_call->respondOK([$maindata], API_User_Response::$verificationSent);
    } else {
        $api_status_call->respondInternalError(API_User_Response::$error_creating_record);
    }
} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
