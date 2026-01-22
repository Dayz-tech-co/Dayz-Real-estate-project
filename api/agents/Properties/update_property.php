<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\Mail_SMS_Responses;
use Config\API_User_Response;
use Config\Utility_Functions;
use Config\DB_Calls_Functions;

$mail_sms_call = new Mail_SMS_Responses();
$utility_class_call = new Utility_Functions();
$db_call_class = new DB_Calls_Functions();
$api_status_code_class_call = new API_Status_Code();

header("Content-type: application/json");

$api_method = "POST";

try {
    if ($_SERVER['REQUEST_METHOD'] !== $api_method) {
        $api_status_code_class_call->respondMethodNotAlowed();
    }

    //Validate Agent Token
    $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
    $agent_pubkey = $decodedToken->usertoken;

    //Sanitize Inputs
    $property_id   = $utility_class_call->clean_user_data($_POST['property_id'] ?? '', 1);
    $title         = $utility_class_call->clean_user_data($_POST['title'] ?? '', 1);
    $price         = $utility_class_call->clean_user_data($_POST['price'] ?? '', 1);
    $description   = $utility_class_call->clean_user_data($_POST['description'] ?? '', 1);
    $location      = $utility_class_call->clean_user_data($_POST['location'] ?? '', 1);
    $property_type = $utility_class_call->clean_user_data($_POST['property_type'] ?? '', 1);
    $property_category = $utility_class_call->clean_user_data($_POST['property_category'] ?? '', 1);

    //Validate Required Inputs
    if ($utility_class_call->input_is_invalid([$property_id, $title, $price, $description, $location, $property_type, $property_category])) {
        $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
    }

    if (!is_numeric($property_id)) {
        $api_status_code_class_call->respondBadRequest("Invalid property_id format");
    }

    //Verify Agent
    $getAgent = $db_call_class->selectRows(
        "agents",
        "id, agency_name, email, kyc_verified",
        [[
            ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
        ]]
    );

    if ($utility_class_call->input_is_invalid($getAgent)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    $agent_id     = $getAgent[0]['id'];
    $agency_name  = $getAgent[0]['agency_name'];
    $agent_email  = $getAgent[0]['email'];
    $kyc_verified = strtolower($getAgent[0]['kyc_verified']);

    if ($kyc_verified !== 'verified') {
        $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
    }

    //Verify Property Ownership & Current Status
    $property = $db_call_class->selectRows(
        "properties",
        "id, agent_id, status, title",
        [[
            ['column' => 'id', 'operator' => '=', 'value' => $property_id],
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
        ]]
    );

    if ($utility_class_call->input_is_invalid($property)) {
        $api_status_code_class_call->respondNotFound(API_User_Response::$propertynotfound);
    }

    $property = $property[0];
    $current_status = strtolower(trim($property['status'] ?? ''));

    //Restrict updates only to "rejected" or "pending"
    $allowed_statuses = ['rejected', 'pending'];
    if (!in_array($current_status, $allowed_statuses, true)) {
        $api_status_code_class_call->respondForbiddenAuthorized(API_User_Response::$propertycannotbeupdated);
    }

    //Prepare Update Data
    $slug = $utility_class_call->generateSlug($title);
    $updateData = [
        'title'             => $title,
        'slug'              => $slug,
        'price'             => $price,
        'description'       => $description,
        'location'          => $location,
        'property_type'     => $property_type,
        'property_category' => $property_category,
        'status'            => 'pending', // Reset to pending for re-review
        'updated_at'        => date('Y-m-d H:i:s')
    ];

    //Execute Update Safely
    $update = $db_call_class->updateRows(
        "properties",
        $updateData,
        [
            ['column' => 'id', 'operator' => '=', 'value' => $property_id],
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]
        ]
    );

    if (!$update) {
        $api_status_code_class_call->respondInternalError(API_User_Response::$error_updating_record);
    }

    //Send Mail Notification
    $system_name = $_ENV['APP_NAME'] ?? 'Property System';
    $subject = "Property Update Submitted for Review";
    $messageText = "Your updated property '$title' has been resubmitted for review.";
    $greetingText = "Hello $agency_name,";
    $mailText = "
        Your updated property titled <strong>$title</strong> has been successfully resubmitted for review on <strong>$system_name</strong>.<br><br>
        Our team will review it shortly and notify you once approved.
    ";
    $messageHTML = $mail_sms_call->generalMailTemplate($subject, $greetingText, $mailText, "");

    $mail_sms_call->sendUserMail($subject, $agent_email, $messageText, $messageHTML);

    //Final Success Response
    $api_status_code_class_call->respondOK([
        "property_id" => $property_id,
        "title" => $title,
        "status" => "pending",
        "message" => "Property updated successfully and resubmitted for review."
    ], API_User_Response::$profile_updated_successfully);

} catch (Exception $e) {
    $api_status_code_class_call->respondInternalError($e->getMessage());
}
