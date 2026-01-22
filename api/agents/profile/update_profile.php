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
    // Validate Agent API Token (forwho = 3)
    $token = $api_status_call->ValidateAPITokenSentIN(1, 2);

    // Extract agent public key
    $agent_pubkey = isset($token->usertoken) ? $utility_class_call->clean_user_data($token->usertoken, 1) : '';
    if ($utility_class_call->input_is_invalid($agent_pubkey)) {
        $api_status_call->respondBadRequest(API_User_Response::$invalidUserDetail);
    }

    // Fetch agent data
    $agent_data = $db_call->selectRows(
        "agents",
        "id, email, agency_name",
        [
            [
                ["column" => "agentpubkey", "operator" => "=", "value" => $agent_pubkey]
            ]
        ]
    );

    if (empty($agent_data)) {
        $api_status_call->respondUnauthorized(API_User_Response::$invalidUserDetail);
    }

    $agent_id = $agent_data[0]['id'];
    $old_email = strtolower(trim($agent_data[0]['email']));
    $agency_name = $agent_data[0]['agency_name'];

    // Sanitize POST data
    $agency_name_new   = isset($_POST["agency_name"]) ? $utility_class_call->clean_user_data($_POST["agency_name"], 1) : '';
    $email             = isset($_POST["email"]) ? strtolower(trim($utility_class_call->clean_user_data($_POST["email"], 1))) : '';
    $phoneno           = isset($_POST["phoneno"]) ? $utility_class_call->clean_user_data($_POST["phoneno"], 1) : '';
    $business_address  = isset($_POST["business_address"]) ? $utility_class_call->clean_user_data($_POST["business_address"], 1) : '';
    $city              = isset($_POST["city"]) ? $utility_class_call->clean_user_data($_POST["city"], 1) : '';
    $state             = isset($_POST["state"]) ? $utility_class_call->clean_user_data($_POST["state"], 1) : '';
    $postal_code       = isset($_POST["postal_code"]) ? $utility_class_call->clean_user_data($_POST["postal_code"], 1) : '';
    $streetname        = isset($_POST["streetname"]) ? $utility_class_call->clean_user_data($_POST["streetname"], 1) : '';
    $country           = isset($_POST["country"]) ? $utility_class_call->clean_user_data($_POST["country"], 1) : '';

    // Validate at least one field
    if (
        $utility_class_call->input_is_invalid($agency_name_new) &&
        $utility_class_call->input_is_invalid($email) &&
        $utility_class_call->input_is_invalid($phoneno) &&
        $utility_class_call->input_is_invalid($business_address) &&
        $utility_class_call->input_is_invalid($city) &&
        $utility_class_call->input_is_invalid($state) &&
        $utility_class_call->input_is_invalid($postal_code) &&
        $utility_class_call->input_is_invalid($streetname) &&
        $utility_class_call->input_is_invalid($country)
    ) {
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    //Handle email change properly
    $email_changed = false;
    if (
        !$utility_class_call->input_is_invalid($email) &&
        $email !== $old_email
    ) {
        // Check if email already exists for another agent
        $check_email = $db_call->selectRows(
            "agents",
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

    //Build update array dynamically
    $update_fields = [];
    if (!$utility_class_call->input_is_invalid($agency_name_new)) $update_fields['agency_name'] = $agency_name_new;
    if ($email_changed) $update_fields['email'] = $email; // only if changed
    if (!$utility_class_call->input_is_invalid($phoneno)) $update_fields['phoneno'] = $phoneno;
    if (!$utility_class_call->input_is_invalid($business_address)) $update_fields['business_address'] = $business_address;
    if (!$utility_class_call->input_is_invalid($city)) $update_fields['city'] = $city;
    if (!$utility_class_call->input_is_invalid($state)) $update_fields['state'] = $state;
    if (!$utility_class_call->input_is_invalid($postal_code)) $update_fields['postal_code'] = $postal_code;
    if (!$utility_class_call->input_is_invalid($streetname)) $update_fields['streetname'] = $streetname;
    if (!$utility_class_call->input_is_invalid($country)) $update_fields['country'] = $country;

    // Skip update if no valid fields
    if (empty($update_fields)) {
        $api_status_call->respondBadRequest(API_User_Response::$no_valid_update_field);
    }

    // Update agent record
    $update_agent = $db_call->updateRows(
        "agents",
        $update_fields,
        [
            ["column" => "id", "operator" => "=", "value" => $agent_id]
        ]
    );

    if (!$update_agent) {
        $api_status_call->respondInternalError(API_User_Response::$error_updating_record);
    }

    // Send confirmation mail
    $systemname = $_ENV['APP_NAME'];
    $subject = "Profile Updated Successfully";
    $messageText = "Your $systemname profile details have been successfully updated.";
    $messagetitle = $subject;
    $greetingText = "Hello $agency_name,";
    $mailText = "We wanted to let you know that your profile information on <strong>$systemname</strong> has been successfully updated.<br><br>
                 If this update was not made by you, please contact our support team immediately.";
    $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "");

    $mail_sms_call->sendUserMail($subject, $old_email, $messageText, $messageHTML);

    $api_status_call->respondOK([], API_User_Response::$profile_updated_successfully);

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
