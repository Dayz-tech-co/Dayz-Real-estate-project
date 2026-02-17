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
    //Validate API Token for User (1, 3)
    $token = $api_status_call->ValidateAPITokenSentIN(1, 3);

    $user_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($user_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    //Collect POST data
    $code = isset($_POST["code"]) ? $utility_class_call->clean_user_data($_POST["code"], 1) : '';
    $type = isset($_POST["type"]) ? strtolower($utility_class_call->clean_user_data($_POST["type"], 1)) : '';

    if ($utility_class_call->input_is_invalid($code)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Fetch user record
    $user_data = $db_call->selectRows(
        "users",
        "id, fname, lname, email, phoneno, email_verified, phone_verified",
        [
            [
                ["column" => "userpubkey", "operator" => "=", "value" => $user_pubkey]
            ]
        ]
    );

    if ($utility_class_call->input_is_invalid($user_data)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    $user = $user_data[0];
    $user_id = $user["id"];
    $fname = $user["fname"];
    $lname = $user["lname"];
    $fullname = trim($fname . " " . $lname);
    $user_email = $user["email"];
    $user_phone = $user["phoneno"];
    $already_email_verified = strtolower((string)$user["email_verified"]);
    $already_phone_verified = strtolower((string)$user["phone_verified"]);

    //CASE 1: already verified
    if ($type === "email" && $already_email_verified === "verified") {
        $subject = $_ENV['APP_NAME'] . " - Email Already Verified";
        $messageText = "Your email is already verified. No further action required.";
        $messageHTML = "
            <h3>Email Already Verified</h3>
            <p>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
            <p>Your email address is already verified for " . $_ENV['APP_NAME'] . ".</p>";

        $mail_sms_call->sendUserMail($subject, $user_email, $messageText, $messageHTML);

        $api_status_call->respondOK([[
            "fullname" => $fullname,
            "verified_type" => "email",
            "email_verified" => "verified",
            "phone_verified" => $already_phone_verified
        ]], "Email already verified");
        exit;
    }

    if ($type === "phone" && $already_phone_verified === "verified") {
        $api_status_call->respondOK([[
            "fullname" => $fullname,
            "verified_type" => "phone",
            "email_verified" => $already_email_verified,
            "phone_verified" => "verified"
        ]], "Phone already verified");
        exit;
    }

    //CASE 2: missing data
    if ($type === "email" && $utility_class_call->input_is_invalid($user_email)) {
        $api_status_call->respondBadRequest(API_User_Response::$user_email_not_found);
    }
    if ($type === "phone" && $utility_class_call->input_is_invalid($user_phone)) {
        $api_status_call->respondBadRequest(API_User_Response::$user_phone_not_found);
    }

    //Verification type setup
    $verification_type = ($type === "email") ? 1 : 2;
    $useridentity = ($type === "email") ? $user_email : $user_phone;

    //Fetch OTP record
    $otp_record = $db_call->selectRows(
        "system_otps",
        "id, otp, status, otp_expiration, useridentity, forwho, verification_type",
        [
            [
                ["column" => "useridentity", "operator" => "=", "value" => $useridentity],
                ["column" => "otp", "operator" => "=", "value" => $code],
                ["column" => "forwho", "operator" => "=", "value" => 2], // users
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
    $otp_id = $otp["id"];
    $otp_expiration = $otp["otp_expiration"];

    if ($utility_class_call->input_is_invalid($otp_expiration) || time() > strtotime($otp_expiration)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidOtporExpired);
    }

    //Mark OTP as consumed
    $db_call->updateRows(
        "system_otps",
        ["status" => 0, "updated_at" => date("Y-m-d H:i:s")],
        [
            [
                ["column" => "id", "operator" => "=", "value" => $otp_id]
            ]
        ]
    );

    // Determine correct field and value
    $verify_field = ($type === "email") ? "email_verified" : "phone_verified";
    $verify_value = "verified"; // ENUM uses string, not int

    // Update verification status in users table
    $db_call->updateRows("users", [
        $verify_field => $verify_value
    ], [
        [
            "column" => "id",
            "operator" => "=",
            "value" => $user_id
        ]
    ]);

    // Send verification success email
    $subject = ($_ENV['APP_NAME'] . " - " . ucfirst($type) . " Verified");
    $messageText = "Your $type has been successfully verified.";
    $messageHTML = "
    <h3>Verification Successful</h3>
    <p>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
    <p>Your " . ($type === "email" ? "email" : "phone number") . " has been successfully verified for " . $_ENV['APP_NAME'] . ".</p>
    <p>Welcome aboard!</p>
";

    $sent = $mail_sms_call->sendUserMail($subject, $user_email, $messageText, $messageHTML);

    if (!$sent) {
        $utility_class_call->log_to_file("verify_code_mail_error.log", [
            "to" => $user_email,
            "user" => $fullname,
            "subject" => $subject,
            "error" => "Verification mail failed to send."
        ]);
    }

    // Final response
    $api_status_call->respondOK([[
        "fullname" => $fullname,
        "verified_type" => $type,
        "email_verified" => ($type === "email") ? "verified" : $already_email_verified,
        "phone_verified" => ($type === "phone") ? "verified" : $already_phone_verified
    ]], API_User_Response::$emailVerifiedSuccessFully);
} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
