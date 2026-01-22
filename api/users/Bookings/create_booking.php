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
    $end_date = isset($data['end_date']) ? $utility_class_call->clean_user_data($data['end_date'], 1) : '';
    $notes = isset($data['notes']) ? $utility_class_call->clean_user_data($data['notes'], 1) : '';

    // Check required fields including end_date
    if (empty($property_id) || empty($visit_date) || empty($end_date)) {
        $api_status_call->respondBadRequest("Missing required fields: property_id, visit_date, and end_date");
    }

    // Validate property_id (only rentals can be booked, must be approved)
    $property = $db_call->selectRows("properties", "id, agent_id, property_category", [[
        ['column' => 'id', 'operator' => '=', 'value' => $property_id],
        ['column' => 'status', 'operator' => '=', 'value' => 'approved'],
        ['column' => 'property_category', 'operator' => '=', 'value' => 'rental']
    ]]);

    if ($utility_class_call->input_is_invalid($property)) {
        $api_status_call->respondBadRequest("Property not found or not available for booking (must be approved rental property)");
    }

    $agent_id = $property[0]['agent_id'];

    // Check for duplicate booking (user can't book same property on same date twice)
    $existingBooking = $db_call->selectRows("bookings", "id", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id],
        ['column' => 'property_id', 'operator' => '=', 'value' => $property_id],
        ['column' => 'visit_date', 'operator' => '=', 'value' => $visit_date],
        ['column' => 'status', 'operator' => '!=', 'value' => 'cancelled']
    ]]);

    if (!empty($existingBooking)) {
        $api_status_call->respondBadRequest("You already have a booking for this property on this date. Duplicate booking not allowed.");
    }

    // Validate dates
    $current_date = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date) || strtotime($visit_date) < strtotime($current_date)) {
        $api_status_call->respondBadRequest("Invalid or past start date");
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) || strtotime($end_date) <= strtotime($visit_date)) {
        $api_status_call->respondBadRequest("Invalid end date or end date must be after start date");
    }

    // Insert booking
    $now = date("Y-m-d H:i:s");
    $insertData = [
        "user_id"        => $user_id,
        "property_id"    => $property_id,
        "agent_id"       => $agent_id,
        "visit_date"     => $visit_date,
        "end_date"       => $end_date,
        "requested_date" => $now,
        "message"        => $notes,
        "booking_type"   => "rental",
        "status"         => "pending",
        "created_at"     => $now
    ];

    DB_Calls_Functions::beginTransaction();
    try {
        $insertedId = $db_call->insertRow("bookings", $insertData);

        if ($insertedId <= 0) {
            throw new \Exception("Failed to create booking");
        }

        // Write initial history - CRITICAL FIX: old_status must be null, not empty string
        $historyData = [
            "booking_id" => $insertedId,
            "old_status" => null,  // THIS WAS THE FIX - must be null, not empty string
            "new_status" => "pending",
            "note" => "Booking created by user",
            "changed_by" => $user_id,
            "changed_by_role" => "user",
            "created_at" => $now
        ];
        
        $historyIns = $db_call->insertRow("booking_status_history", $historyData);
        
        if (!$historyIns || $historyIns <= 0) {
            throw new \Exception("Failed to write booking history");
        }

        DB_Calls_Functions::commitTransaction();
    } catch (\Exception $e) {
        DB_Calls_Functions::rollbackTransaction();
        $api_status_call->respondInternalError("Failed to create booking: " . $e->getMessage());
    }

    $api_status_call->respondOK([
        "booking_id" => $insertedId,
        "user_id" => $user_id,
        "property_id" => $property_id,
        "visit_date" => $visit_date,
        "end_date" => $end_date,
        "status" => "pending"
    ], "Property booking created successfully");

} catch (Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}