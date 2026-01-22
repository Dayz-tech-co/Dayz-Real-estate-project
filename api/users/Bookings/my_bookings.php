<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_call = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-Type: application/json");

$apimethod = "POST";

if ($_SERVER['REQUEST_METHOD'] !== $apimethod) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    // Validate user token (1,3)
    $decodedToken = $api_status_call->ValidateAPITokenSentIN(1, 3);
    $user_pubkey = $decodedToken->usertoken;

    // Fetch user
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

    // Fetch bookings
    $bookings = $db_call->selectRows("bookings", "*", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
    ]]);

    if ($utility_class_call->input_is_invalid($bookings)) {
        $api_status_call->respondNotFound([], API_User_Response::$bookingsnotfound);
    }

    $result = [];

    foreach ($bookings as $booking) {
        // Fetch property info
        $property = $db_call->selectRows("properties", "id, title, slug, price, property_type, property_category, city, state, thumbnail", [[
            ['column' => 'id', 'operator' => '=', 'value' => $booking['property_id']]
        ]]);

        // Fetch agent info
        $agent = $db_call->selectRows("agents", "id, agency_name, email, phoneno", [[
            ['column' => 'id', 'operator' => '=', 'value' => $booking['agent_id']]
        ]]);

        $result[] = [
            "booking_id"      => $booking['id'],
            "property_id"     => $booking['property_id'],
            "property_title"  => $property['title'] ?? '',
            "property_type"   => $property['property_type'] ?? '',
            "property_category" => $property['property_category'] ?? '',
            "price"           => $property['price'] ?? 0,
            "booking_status"  => $booking['status'],
            "visit_date"      => $booking['visit_date'] ?? null,
            "end_date"        => $booking['end_date'] ?? null,
            "created_at"      => $booking['created_at']
        ];
    }

    $api_status_call->respondOK($result, "Bookings fetched successfully");
} catch (Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
