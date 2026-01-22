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

    // Fetch user record
    $getUser = $db_call_class->selectRows("users", "id, fname, lname, email", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    $user_id = $getUser[0]['id'];

    // Get property_id to remove
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
    if ($property_id <= 0) {
        $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Check if the property exists in user's wishlist
    $wishlistItem = $db_call_class->selectRows("wishlist", "id", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id],
        ['column' => 'property_id', 'operator' => '=', 'value' => $property_id]
    ]]);

    if (empty($wishlistItem)) {
        $api_status_code_class_call->respondNotFound([], API_User_Response::$wishlistItemNotFound );
    }

    // Remove the item
    $removed = $db_call_class->deleteRows("wishlist", [[
        ['column' => 'id', 'operator' => '=', 'value' => $wishlistItem[0]['id']]
    ]]);

    if ($removed) {
        $api_status_code_class_call->respondOK([], API_User_Response::$wishlistRemoved );
    } else {
        $api_status_code_class_call->respondInternalError(API_User_Response::$error_deleting_record);
    }

} catch (\Exception $e) {
    $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
