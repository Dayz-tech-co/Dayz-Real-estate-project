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
    $user_pubkey = isset($decodedToken->usertoken) ? $utility_class_call->clean_user_data($decodedToken->usertoken, 1) : '';

    // Fetch user record
    $getUser = $db_call_class->selectRows("users", "id, fname, lname, email, kyc_verified", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    $user_id = $getUser[0]['id'];
    $fullname = trim($getUser[0]['fname'] . ' ' . $getUser[0]['lname']);
    $user_email = $getUser[0]['email'];

    // Get property_id from POST
    $property_id = isset($_POST['property_id']) ? $utility_class_call->clean_user_data($_POST['property_id'], 1) : '';

    if ($utility_class_call->input_is_invalid($property_id)) {
        $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
    }

    // Fetch property (ensure not deleted)
    $propertyRows = $db_call_class->selectRows(
        "properties",
        "*",
        [[
            ['column' => 'id', 'operator' => '=', 'value' => $property_id],
            'operator' => 'AND'
        ], [
            ['column' => 'deleted_at', 'operator' => 'IS', 'value' => null]
        ]]
    );

    if ($utility_class_call->input_is_invalid($propertyRows)) {
        $api_status_code_class_call->respondNotFound([], API_User_Response::$propertynotfound);
    }

    $property = $propertyRows[0];

    // Decode images (stored as JSON/text)
    $images = [];
    if (!empty($property['images'])) {
        $decoded = json_decode($property['images'], true);
        if (is_array($decoded)) {
            $images = $decoded;
        } else {
            // fallback if images stored as comma-separated
            $images = array_filter(array_map('trim', explode(',', $property['images'])));
        }
    }

    // Features might be JSON/text as well
    $features = [];
    if (!empty($property['feature'])) {
        $decodedF = json_decode($property['feature'], true);
        if (is_array($decodedF)) {
            $features = $decodedF;
        } else {
            $features = $property['feature'];
        }
    }

    // Fetch agent (include agency_name)
    $agentRows = $db_call_class->selectRows(
        "agents",
        "id, agency_name, email, phoneno, profile_pic, country, city, state",
        [[
            ['column' => 'id', 'operator' => '=', 'value' => $property['agent_id']]
        ]]
    );

    if ($utility_class_call->input_is_invalid($agentRows)) {
        $api_status_code_class_call->respondNotFound([], API_User_Response::$agentnotfound);
    }

    $agent = $agentRows[0];

    // Prepare response
    $maindata = [
        "property" => [
            "id" => $property['id'],
            "agent_id" => $property['agent_id'],
            "agency_name" => $property['agency_name'],
            "title" => $property['title'],
            "slug" => $property['slug'],
            "description" => $property['description'],
            "price" => $property['price'],
            "property_category" => $property['property_category'],
            "property_type" => $property['property_type'],
            "bed" => $property['bed'],
            "bath" => $property['bath'],
            "balc" => $property['balc'],
            "hall" => $property['hall'],
            "kitc" => $property['kitc'],
            "floor" => $property['floor'],
            "asize" => $property['asize'],
            "city" => $property['city'],
            "state" => $property['state'],
            "location" => $property['location'],
            "feature" => $features,
            "images" => $images,
            "thumbnail" => $property['thumbnail'],
            "featured" => (int)$property['featured'],
            "verified" => (int)$property['verified'],
            "status" => $property['status'],
            "sold_status" => $property['sold_status'],
            "created_at" => $property['created_at'],
            "updated_at" => $property['updated_at']
        ],
        "agent" => [
            "id" => $agent['id'],
            "agency_name" => $agent['agency_name'],
            "email" => $agent['email'],
            "phoneno" => $agent['phoneno'],
            "profile_pic" => $agent['profile_pic'],
            "country" => $agent['country'],
            "city" => $agent['city'],
            "state" => $agent['state']
        ]
    ];

    $api_status_code_class_call->respondOK($maindata, API_User_Response::$propertyFetched);

} catch (\Exception $e) {
    $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
