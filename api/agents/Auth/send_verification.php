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
    // Validate API Token for Agent (2)
    $token = $api_status_call->ValidateAPITokenSentIN(1, 2);

    // Extract and sanitize agent public key
    $agent_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($agent_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    // Validate send type (email or phone)
    $sendtype = isset($_POST["type"]) ? strtolower($utility_class_call->clean_user_data($_POST["type"], 1)) : '';
    if ($utility_class_call->input_is_invalid($sendtype) || !in_array($sendtype, ["email", "phone"])) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Fetch agent details
    $responseData = $db_call->selectRows(
        "agents",
        "id, email, phoneno, agency_name, emailverified, phoneverified",
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

    // Determine destination (email or phone)
    $destination = ($sendtype === "email") ? $email : $phoneno;
    if ($utility_class_call->input_is_invalid($destination)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Generate verification code (6-digit OTP)
    $verificationCode = random_int(100000, 999999);

    // store expiry as DATETIME string (5 minutes from now)
    $expiryTimestamp = time() + (5 * 60);
    $expiryTime = date('Y-m-d H:i:s', $expiryTimestamp);

    // Insert new OTP record into system_otps
    $insert_otp = $db_call->insertRow(
        "system_otps",
        [
            "user_id"            => $agent_id,
            "useridentity"       => $destination,
            "token"              => $agent_pubkey,
            "verification_type"  => ($sendtype === "email") ? 1 : 2, // 1 Email, 2 SMS
            "otp"                => $verificationCode,
            "otp_expiration"     => $expiryTime,
            "status"             => 1, // 1 = sent, 0 = unsent
            "method_used"        => 1, // 1 = SMS/WhatsApp/Email, 2 = Call
            "forwho"             => 3  // 3 = agent
        ]
    );

    if (!$insert_otp) {
        $api_status_call->respondInternalError(API_User_Response::$error_creating_record);
    }


    // Send verification via Email or SMS
    $systemname = $_ENV['APP_NAME'];
    $sent = false;

    if ($sendtype === "email") {
        $subject = Mail_SMS_Responses::sendOTPSubject($systemname);
        $messageText = Mail_SMS_Responses::sendOTPText($verificationCode);
        $messagetitle = $subject;
        $greetingText = "Hello $agency_name,";
        $mailText = "Here’s your verification code to continue using your $systemname agent account.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, $verificationCode);

        $sent = $mail_sms_call->sendUserMail($subject, $destination, $messageText, $messageHTML);
    } else {
        // For now, just reuse email method for phone (you can replace with SMS later)
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

    // Final response
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
