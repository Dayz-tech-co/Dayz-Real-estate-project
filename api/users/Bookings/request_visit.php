<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;
use Config\Mail_SMS_Responses;

$api_status_call = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;
$mail_sms_call = new Mail_SMS_Responses;

header("Content-Type: application/json");

$apimethod = "POST";

if ($_SERVER['REQUEST_METHOD'] !== $apimethod) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    // Validate user token
    $decodedToken = $api_status_call->ValidateAPITokenSentIN(1, 3);
    
    $user_pubkey = $decodedToken->usertoken;

    // Fetch user info
    $getUser = $db_call->selectRows("users", "id, fname, lname, email, kyc_verified", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_call->respondUnauthorized();
    }

    // Check KYC verification
    if ($getUser[0]['kyc_verified'] !== 'verified') {
        $api_status_call->respondBadRequest([], API_User_Response::$kyc_required);
    }

    $user_id = $getUser[0]['id'];
    $fullname = $getUser[0]['fname'] . " " . $getUser[0]['lname'];
    $user_email = $getUser[0]['email'];

    // Collect request body - support JSON and form data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
    } else {
        $data = $_POST ?: [];
    }

    // Validate inputs
    $property_id = isset($data['property_id']) ? $utility_class_call->clean_user_data($data['property_id'], 1) : '';
    $visit_date = isset($data['visit_date']) ? $utility_class_call->clean_user_data($data['visit_date'], 1) : '';
    $scheduled_time = isset($data['scheduled_time']) ? $utility_class_call->clean_user_data($data['scheduled_time'], 1) : '';

    // Check required fields
    if (empty($property_id) || empty($visit_date)) {
        $api_status_call->respondBadRequest("Missing required fields: property_id and visit_date");
    }

    // Validate property_id (must be approved)
    $property = $db_call->selectRows("properties", "id, agent_id", [[
        ['column' => 'id', 'operator' => '=', 'value' => $property_id],
        ['column' => 'status', 'operator' => '=', 'value' => 'approved']
    ]]);

    if ($utility_class_call->input_is_invalid($property)) {
        $api_status_call->respondBadRequest("Property not found or not available for visit request (must be approved property)");
    }

    $agent_id = $property[0]['agent_id'];

    // Validate date (YYYY-MM-DD format)
    $current_date = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date) || strtotime($visit_date) < strtotime($current_date)) {
        $api_status_call->respondBadRequest("Invalid or past visit date");
    }

    $agent_id = $property[0]['agent_id'];

    // Validate date (YYYY-MM-DD format)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date) || strtotime($visit_date) < strtotime($current_date)) {
        $api_status_call->respondBadRequest("Invalid or past visit date");
    }

    // Insert booking request
    $insertData = [
        "user_id" => $user_id,
        "property_id" => $property_id,
        "agent_id" => $agent_id,
        "visit_date" => $visit_date,
        "scheduled_time" => $scheduled_time,
        "requested_date" => date("Y-m-d H:i:s"),
        "booking_type" => "sale",
        "status" => "pending",
        "created_at" => date("Y-m-d H:i:s")
    ];

    $insertedId = $db_call->insertRow("bookings", $insertData);

    if ($insertedId <= 0) {
        $api_status_call->respondInternalError("Failed to request visit");
    }

    // Optionally, send notification to agent

    $api_status_call->respondOK([
        "booking_id" => $insertedId,
        "user_id" => $user_id,
        "property_id" => $property_id,
        "visit_date" => $visit_date,
        "status" => "pending"
    ], "Visit request submitted successfully");

} catch (Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
?>
