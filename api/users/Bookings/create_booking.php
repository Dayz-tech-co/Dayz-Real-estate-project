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
    $additional_months_raw = isset($data['additional_months']) ? $utility_class_call->clean_user_data($data['additional_months'], 1) : '0';
    $notes = isset($data['notes']) ? $utility_class_call->clean_user_data($data['notes'], 1) : '';

    // Check required fields
    if (empty($property_id) || empty($visit_date)) {
        $api_status_call->respondBadRequest("Missing required fields: property_id and visit_date");
    }

    // Validate property_id (only approved listings can be booked)
    $property = $db_call->selectRows("properties", "id, agent_id, property_category, property_type, price", [[
        ['column' => 'id', 'operator' => '=', 'value' => $property_id],
        ['column' => 'status', 'operator' => '=', 'value' => 'approved']
    ]]);

    if ($utility_class_call->input_is_invalid($property)) {
        $api_status_call->respondBadRequest("Property not found or not available for booking");
    }

    $propertyCategory = strtolower(trim($property[0]['property_category'] ?? ''));
    $propertyType = strtolower(trim($property[0]['property_type'] ?? ''));
    $bookableCategories = ['rent', 'lease', 'rental']; // keep legacy rental support
    if (!in_array($propertyCategory, $bookableCategories, true)) {
        $api_status_call->respondBadRequest("Booking is only available for rental or lease properties.");
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

    // Validate start date
    $current_date = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date) || strtotime($visit_date) < strtotime($current_date)) {
        $api_status_call->respondBadRequest("Invalid or past start date");
    }

    $price = (float)($property[0]['price'] ?? 0);
    $startDateObj = \DateTime::createFromFormat('Y-m-d', $visit_date);
    if (!$startDateObj) {
        $api_status_call->respondBadRequest("Invalid booking dates.");
    }

    $dailyRate = 0.0;
    $durationDays = 0;
    $paymentAmount = 0.0;
    $bookingType = 'rental';
    $leaseBaseMonths = 3;
    $leaseExtraMonths = 0;
    $totalLeaseMonths = 0;
    $monthlyRate = 0.0;

    if ($propertyCategory === 'lease') {
        if ($additional_months_raw === '' || !preg_match('/^\d+$/', (string)$additional_months_raw)) {
            $api_status_call->respondBadRequest("additional_months must be a non-negative integer.");
        }

        $leaseExtraMonths = (int)$additional_months_raw;
        $totalLeaseMonths = $leaseBaseMonths + $leaseExtraMonths;

        $startForEnd = clone $startDateObj;
        $startForEnd->add(new \DateInterval('P' . $totalLeaseMonths . 'M'));
        $end_date = $startForEnd->format('Y-m-d');
        $endDateObj = \DateTime::createFromFormat('Y-m-d', $end_date);

        if (!$endDateObj) {
            $api_status_call->respondBadRequest("Unable to derive lease end date.");
        }

        $durationDays = (int)$startDateObj->diff($endDateObj)->days;
        if ($durationDays <= 0) {
            $api_status_call->respondBadRequest("Lease duration must be at least 3 months.");
        }

        $monthlyRate = round($price / $leaseBaseMonths, 2);
        $dailyRate = $monthlyRate;
        $paymentAmount = round($monthlyRate * $totalLeaseMonths, 2);
    } else {
        if (empty($end_date)) {
            $api_status_call->respondBadRequest("Missing required field: end_date");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) || strtotime($end_date) <= strtotime($visit_date)) {
            $api_status_call->respondBadRequest("Invalid end date or end date must be after start date");
        }

        $endDateObj = \DateTime::createFromFormat('Y-m-d', $end_date);
        if (!$endDateObj) {
            $api_status_call->respondBadRequest("Invalid booking dates.");
        }

        $durationDays = (int)$startDateObj->diff($endDateObj)->days;
        if ($durationDays <= 0) {
            $api_status_call->respondBadRequest("Booking duration must be at least 1 day.");
        }

        $dailyRate = $price;
        $paymentAmount = round($dailyRate * $durationDays, 2);
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
        "booking_type"   => $bookingType,
        "status"         => "pending",
        "payment_status" => "unpaid",
        "total_amount"   => $paymentAmount,
        "payment_amount" => $paymentAmount,
        "created_at"     => $now
    ];

    DB_Calls_Functions::beginTransaction();
    try {
        try {
            $insertedId = $db_call->insertRow("bookings", $insertData);
        } catch (\Exception $insertEx) {
            $insertError = $insertEx->getMessage();

            // Backward compatibility: some deployments may not yet have total_amount.
            if (strpos($insertError, 'Unknown column') !== false && strpos($insertError, 'total_amount') !== false) {
                unset($insertData['total_amount']);
                $insertedId = $db_call->insertRow("bookings", $insertData);
            } else {
                throw $insertEx;
            }
        }

        if ($insertedId <= 0) {
            throw new \Exception("Failed to create booking");
        }

        // History is optional: do not fail booking creation if history schema is unavailable/mismatched.
        if (DB_Calls_Functions::tableExists("booking_status_history")) {
            try {
                $historyData = [
                    "booking_id" => $insertedId,
                    "old_status" => "pending",
                    "new_status" => "pending",
                    "note" => "Booking created by user",
                    "changed_by" => $user_id,
                    "changed_by_role" => "user",
                    "created_at" => $now
                ];
                $db_call->insertRow("booking_status_history", $historyData);
            } catch (\Exception $historyEx) {
                // Ignore history logging failure to prevent blocking valid bookings.
            }
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
        "property_category" => $propertyCategory,
        "visit_date" => $visit_date,
        "end_date" => $end_date,
        "duration_days" => $durationDays,
        "daily_rate" => $dailyRate,
        "monthly_rate" => $monthlyRate,
        "base_months" => $leaseBaseMonths,
        "additional_months" => $leaseExtraMonths,
        "total_lease_months" => $totalLeaseMonths,
        "total_amount" => $paymentAmount,
        "status" => "pending"
    ], "Property booking created successfully");

} catch (Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
