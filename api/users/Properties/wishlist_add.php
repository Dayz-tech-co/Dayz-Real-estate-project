<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-Type: application/json");
$apimethod = "POST";

if (getenv("REQUEST_METHOD") !== $apimethod) {
    $api_status_code_class_call->respondMethodNotAlowed();
    exit;
}

try {
    // Validate API token for user (1,3)
    $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 3);
    $user_pubkey = $utility_class_call->clean_user_data($decodedToken->usertoken, 1);

    // Fetch user
    $getUser = $db_call_class->selectRows("users", "id, fname, lname, email", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    $user_id = $getUser[0]['id'];

    // Get property_id
    $property_id = isset($_POST['property_id']) ? $utility_class_call->clean_user_data($_POST['property_id'], 1) : '';

    if ($utility_class_call->input_is_invalid($property_id)) {
        $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Check if property exists
    $property_check = $db_call_class->selectRows("properties", "id", [[
        ['column' => 'id', 'operator' => '=', 'value' => $property_id]
    ]]);

    if ($utility_class_call->input_is_invalid($property_check)) {
        $api_status_code_class_call->respondNotFound([], API_User_Response::$propertynotfound);
    }

    // Check if already wishlisted
    $wishlist_check = $db_call_class->selectRows("wishlist", "id", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
    ], [
        ['column' => 'property_id', 'operator' => '=', 'value' => $property_id]
    ]]);

    if (!empty($wishlist_check)) {
        $api_status_code_class_call->respondBadRequest(API_User_Response::$alreadyInWishlist);
    }

    // Insert new wishlist entry
    $insert = $db_call_class->insertRow("wishlist", [
        "user_id" => $user_id,
        "property_id" => $property_id
    ]);

    if (!$insert) {
        $api_status_code_class_call->respondInternalError(API_User_Response::$unabletoaddtoWishlist);
    }

    $api_status_code_class_call->respondOK([], API_User_Response::$addedToWishlist);

} catch (\Exception $e) {
    $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
