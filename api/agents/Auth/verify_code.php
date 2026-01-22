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
    // Validate API Token for Agent
    $token = $api_status_call->ValidateAPITokenSentIN(1, 2);

    $agent_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($agent_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    // Get POST body
    $code = isset($_POST["code"]) ? $utility_class_call->clean_user_data($_POST["code"], 1) : '';
    $type = isset($_POST["type"]) ? strtolower($utility_class_call->clean_user_data($_POST["type"], 1)) : '';

    if ($utility_class_call->input_is_invalid($code) || $utility_class_call->input_is_invalid($type) || !in_array($type, ["email", "phone"])) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Fetch agent record
    $agent_data = $db_call->selectRows(
        "agents",
        "id, agency_name, email, phoneno, emailverified, phoneverified",
        [
            [
                ["column" => "Agentpubkey", "operator" => "=", "value" => $agent_pubkey]
            ]
        ]
    );

    if ($utility_class_call->input_is_invalid($agent_data)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    $agent = $agent_data[0];
    $agent_id = $agent["id"];
    $agency_name = $agent["agency_name"];
    $agent_email = $agent["email"];
    $agent_phone = $agent["phoneno"];
    $already_email_verified = (int)$agent["emailverified"];
    $already_phone_verified = (int)$agent["phoneverified"];

    // CASE 1: email or phone already verified
    if ($type === "email" && $already_email_verified === 1) {
        $subject = $_ENV['APP_NAME'] . " - Email Already Verified";
        $messageText = "Your email is already verified. No further action required.";
        $messageHTML = "
            <h3>Email Already Verified</h3>
            <p>Hello <strong>" . htmlspecialchars($agency_name) . "</strong>,</p>
            <p>Your email address is already verified for " . $_ENV['APP_NAME'] . ".</p>";

        $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

        $api_status_call->respondOK([
            [
                "agency_name" => $agency_name,
                "verified_type" => "email",
                "email_verified" => 1,
                "phone_verified" => $already_phone_verified
            ]
        ], "Email already verified");
    }

    if ($type === "phone" && $already_phone_verified === 1) {
        $api_status_call->respondOK([
            [
                "agency_name" => $agency_name,
                "verified_type" => "phone",
                "email_verified" => $already_email_verified,
                "phone_verified" => 1
            ]
        ], "Phone already verified");
    }

    // CASE 2: if agent email is missing (user not fully registered)
    if ($type === "email" && $utility_class_call->input_is_invalid($agent_email)) {
        $api_status_call->respondBadRequest(API_User_Response::$agent_email_not_found);
    }
    if ($type === "phone" && $utility_class_call->input_is_invalid($agent_phone)) {
        $api_status_call->respondBadRequest(API_User_Response::$agent_phone_not_found);
    }

    // Setup verification type
    $verification_type = ($type === "email") ? 1 : 2;
    $useridentity = ($type === "email") ? $agent_email : $agent_phone;

    // Fetch OTP
    $otp_record = $db_call->selectRows(
        "system_otps",
        "id, otp, status, otp_expiration, useridentity, forwho, verification_type",
        [
            [
                ["column" => "useridentity", "operator" => "=", "value" => $useridentity],
                ["column" => "otp", "operator" => "=", "value" => $code],
                ["column" => "forwho", "operator" => "=", "value" => 3],
                ["column" => "verification_type", "operator" => "=", "value" => $verification_type],
                ["column" => "status", "operator" => "=", "value" => 1]
            ]
        ],
        "ORDER BY id DESC LIMIT 1"
    );

    if ($utility_class_call->input_is_invalid($otp_record)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidOtporExpired);
    }

    $otp = $otp_record[0];
    $otp_id = $otp["id"]; // Add this line
    $otp_expiration = $otp["otp_expiration"];

    if ($utility_class_call->input_is_invalid($otp_expiration) || time() > strtotime($otp_expiration)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidOtporExpired);
    }

    // mark OTP as consumed
    $db_call->updateRows(
        "system_otps",
        ["status" => 0, "updated_at" => date("Y-m-d H:i:s")],
        [
            [
                ["column" => "id", "operator" => "=", "value" => $otp_id]
            ]
        ]
    );


    // Update verification status
    $db_call->updateRows("agents", [
        ($type === "email") ? "emailverified" : "phoneverified" => 1
    ], [
        [
            ["column" => "id", "operator" => "=", "value" => $agent_id]
        ]
    ]);

    // Send verification success mail
    $subject = ($_ENV['APP_NAME'] . " - " . ucfirst($type) . " Verified");
    $messageText = "Your $type has been successfully verified.";
    $messageHTML = "
        <h3>Verification Successful</h3>
        <p>Hello <strong>" . htmlspecialchars($agency_name) . "</strong>,</p>
        <p>Your " . ($type === "email" ? "email" : "phone number") . " has been successfully verified for " . $_ENV['APP_NAME'] . ".</p>
        <p>Welcome aboard!</p>";

    $sent = $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

    if (!$sent) {
        $utility_class_call->log_to_file("verify_code_mail_error.log", [
            "to" => $agent_email,
            "agency" => $agency_name,
            "subject" => $subject,
            "error" => "Verification mail failed to send."
        ]);
    }

    // Final response
    $api_status_call->respondOK([
        [
            "agency_name" => $agency_name,
            "verified_type" => $type,
            "email_verified" => ($type === "email") ? 1 : $already_email_verified,
            "phone_verified" => ($type === "phone") ? 1 : $already_phone_verified
        ]
    ], API_User_Response::$emailVerifiedSuccessFully);
} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
