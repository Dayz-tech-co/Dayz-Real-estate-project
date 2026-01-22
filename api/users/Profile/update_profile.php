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
    //Validate User API Token 
    $token = $api_status_call->ValidateAPITokenSentIN(1, 3);

    // Extract user public key
    $user_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($user_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    // Fetch user data
    $user_data = $db_call->selectRows(
        "users",
        "id, email, fname, lname",
        [
            [
                ["column" => "userpubkey", "operator" => "=", "value" => $user_pubkey]
            ]
        ]
    );

    if (empty($user_data)) {
        $api_status_call->respondUnauthorized(API_User_Response::$invalidUserDetail);
    }

    $user_id = $user_data[0]['id'];
    $old_email = strtolower(trim($user_data[0]['email']));
    $fname = $user_data[0]['fname'];
    $lname = $user_data[0]['lname'];

    //Clean user input
    $fname_new    = isset($_POST["fname"]) ? $utility_class_call->clean_user_data($_POST["fname"], 1) : '';
    $lname_new    = isset($_POST["lname"]) ? $utility_class_call->clean_user_data($_POST["lname"], 1) : '';
    $email        = isset($_POST["email"]) ? strtolower(trim($utility_class_call->clean_user_data($_POST["email"], 1))) : '';
    $phoneno      = isset($_POST["phoneno"]) ? $utility_class_call->clean_user_data($_POST["phoneno"], 1) : '';
    $country      = isset($_POST["country"]) ? $utility_class_call->clean_user_data($_POST["country"], 1) : '';
    $address      = isset($_POST["address"]) ? $utility_class_call->clean_user_data($_POST["address"], 1) : '';
    $state        = isset($_POST["state"]) ? $utility_class_call->clean_user_data($_POST["state"], 1) : '';
    $city         = isset($_POST["city"]) ? $utility_class_call->clean_user_data($_POST["city"], 1) : '';

    //Check if at least one field was sent
    if (
        $utility_class_call->input_is_invalid($fname_new) &&
        $utility_class_call->input_is_invalid($lname_new) &&
        $utility_class_call->input_is_invalid($email) &&
        $utility_class_call->input_is_invalid($phoneno) &&
        $utility_class_call->input_is_invalid($country) &&
        $utility_class_call->input_is_invalid($address) &&
        $utility_class_call->input_is_invalid($state) &&
        $utility_class_call->input_is_invalid($city)
    ) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Handle email change safely
    $email_changed = false;
    if (
        !$utility_class_call->input_is_invalid($email) &&
        $email !== $old_email
    ) {
        // Check if email already exists for another user
        $check_email = $db_call->selectRows(
            "users",
            "id",
            [
                [
                    ["column" => "email", "operator" => "=", "value" => $email]
                ]
            ]
        );

        if (!empty($check_email)) {
            $api_status_call->respondBadRequest(API_User_Response::$already_created_record);
        }
        $email_changed = true;
    }

    //Build dynamic update fields
    $update_fields = [];
    if (!$utility_class_call->input_is_invalid($fname_new)) $update_fields['fname'] = $fname_new;
    if (!$utility_class_call->input_is_invalid($lname_new)) $update_fields['lname'] = $lname_new;
    if ($email_changed) $update_fields['email'] = $email;
    if (!$utility_class_call->input_is_invalid($phoneno)) $update_fields['phoneno'] = $phoneno;
    if (!$utility_class_call->input_is_invalid($country)) $update_fields['country'] = $country;
    if (!$utility_class_call->input_is_invalid($address)) $update_fields['address'] = $address;
    if (!$utility_class_call->input_is_invalid($state)) $update_fields['state'] = $state;
    if (!$utility_class_call->input_is_invalid($city)) $update_fields['city'] = $city;

    // No valid field check
    if (empty($update_fields)) {
        $api_status_call->respondBadRequest(API_User_Response::$no_valid_update_field);
    }

    //Perform update
    $update_user = $db_call->updateRows(
        "users",
        $update_fields,
        [
            ["column" => "id", "operator" => "=", "value" => $user_id]
        ]
    );

    if (!$update_user) {
        $api_status_call->respondInternalError(API_User_Response::$error_updating_record);
    }

    //Notify user via mail
    $systemname = $_ENV['APP_NAME'];
    $subject = "Profile Updated Successfully";
    $messageText = "Your $systemname profile details have been successfully updated.";
    $messagetitle = $subject;
    $greetingText = "Hello $fname $lname,";
    $mailText = "We wanted to let you know that your profile information on <strong>$systemname</strong> has been successfully updated.<br><br>
                 If this update was not made by you, please contact our support team immediately.";
    $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

    $mail_sms_call->sendUserMail($subject, $old_email, $messageText, $messageHTML);

    $api_status_call->respondOK([], API_User_Response::$profile_updated_successfully);

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
?>
