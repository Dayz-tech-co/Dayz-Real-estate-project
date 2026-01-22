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

    // Fetch wishlist items for this user
    $wishlistItems = $db_call_class->selectRows("wishlist", "property_id", [[
        ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
    ]]);

    if (empty($wishlistItems)) {
        $api_status_code_class_call->respondNotFound([], API_User_Response::$wishlistEmpty );
    }

    $properties = [];
    foreach ($wishlistItems as $item) {
        $property_id = $item['property_id'];

        $property = $db_call_class->selectRows(
            "properties",
            "id, title, slug, description, price, property_category, property_type, city, state, images, thumbnail, verified, status, created_at, updated_at",
            [[
                ['column' => 'id', 'operator' => '=', 'value' => $property_id]
            ]]
        );

        if (!empty($property)) {
            // Optionally, include first image separately
            $prop = $property[0];
            $prop['images'] = json_decode($prop['images'] ?? "[]", true);
            $properties[] = $prop;
        }
    }

    $api_status_code_class_call->respondOK($properties, API_User_Response::$wishlistFetched);

} catch (\Exception $e) {
    $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
