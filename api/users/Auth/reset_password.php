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
    // Collect and sanitize inputs
    $otp         = isset($_POST['otp']) ? $utility_class_call->clean_user_data($_POST['otp'], 1) : '';
    $newpassword = isset($_POST['newpassword']) ? $utility_class_call->clean_user_data($_POST['newpassword'], 1) : '';

    if ($utility_class_call->input_is_invalid($otp) || $utility_class_call->input_is_invalid($newpassword)) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Check OTP validity in system_otps table (forwho = 2 for users)
    $otp_check = $db_call->selectRows(
        "system_otps",
        "user_id, useridentity, otp, otp_expiration, status, verification_type, forwho",
        [
            [
                ["column" => "otp", "operator" => "=", "value" => $otp],
                ["column" => "verification_type", "operator" => "=", "value" => 3],
                ["column" => "status", "operator" => "=", "value" => 1]
            ]
        ]
    );

    if (empty($otp_check)) {
        $api_status_call->respondUnauthorized(API_User_Response::$invalidOtporExpired);
    }

    $otp_data = $otp_check[0];

    // Check OTP expiration
    if (strtotime($otp_data['otp_expiration']) < time()) {
        $api_status_call->respondUnauthorized(API_User_Response::$invalidOtporExpired);
    }

    $user_id = $otp_data['user_id'];

    // Hash new password securely
    $hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);

    // Update user password
    $update_password = $db_call->updateRows(
        "users",
        ["password" => $hashed_password],
        [
            ["column" => "id", "operator" => "=", "value" => $user_id]
        ]
    );

    if (!$update_password) {
        $api_status_call->respondInternalError(API_User_Response::$error_updating_record);
    }

    // Mark OTP as used
    $db_call->updateRows(
        "system_otps",
        ["status" => 0],
        [
            ["column" => "otp", "operator" => "=", "value" => $otp]
        ]
    );

    // Fetch user info for email
    $user_data = $db_call->selectRows(
        "users",
        "fname, email",
        [
            [
                ["column" => "id", "operator" => "=", "value" => $user_id]
            ]
        ]
    );

    if (!empty($user_data)) {
        $fname = $user_data[0]['fname'];
        $user_email = $user_data[0]['email'];

        // Prepare success email
        $systemname = $_ENV['APP_NAME'];
        $subject = "Password Reset Successful";
        $messageText = "Your password has been successfully updated for your $systemname account.";
        $messagetitle = $subject;
        $greetingText = "Hello $fname,";
        $mailText = "You have successfully updated your password for your <strong>$systemname</strong> account.<br><br>
                     If you did not make this change, please contact support immediately.";
        $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

        // Send confirmation mail
        $mail_sms_call->sendUserMail($subject, $user_email, $messageText, $messageHTML);
    }

    // Final success response
    $api_status_call->respondOK([], API_User_Response::$password_reset_successfully);

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
