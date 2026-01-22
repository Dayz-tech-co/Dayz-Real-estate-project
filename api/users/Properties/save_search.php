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

$api_method = "POST";

if (getenv("REQUEST_METHOD") === $api_method) {
    try {
        // Validate User Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 3);
        $user_pubkey = $decodedToken->usertoken;

        $getUser = $db_call_class->selectRows("users", "id, fname, lname, email", [[
            ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getUser)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $user_id = $getUser[0]['id'];

        // Collect request body (JSON)
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $search_name = isset($data['search_name']) ? $utility_class_call->clean_user_data($data['search_name'], 0) : '';
        $search_criteria = isset($data['search_criteria']) ? $data['search_criteria'] : [];

        if ($utility_class_call->input_is_invalid($search_name) || empty($search_criteria)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
        }

        // Validate search_name length
        if (strlen($search_name) > 100) {
            $api_status_code_class_call->respondBadRequest("Search name too long.");
        }

        // Check if saved search name already exists for this user
        $existing = $db_call_class->selectRows("saved_searches", "id", [[
            ['column' => 'user_id', 'operator' => '=', 'value' => $user_id],
            ['column' => 'name', 'operator' => '=', 'value' => $search_name]
        ]]);

        if (!$utility_class_call->input_is_invalid($existing)) {
            $api_status_code_class_call->respondBadRequest("Saved search name already exists.");
        }

        // Insert the saved search
        $insertData = [
            "user_id" => $user_id,
            "name" => $search_name,
            "criteria" => json_encode($search_criteria),
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        ];

        $insertedId = $db_call_class->insertRow("saved_searches", $insertData);

        if ($insertedId <= 0) {
            $api_status_code_class_call->respondInternalError("Failed to save search.");
        }

        $payload = [
            "saved_search_id" => $insertedId,
            "search_name" => $search_name,
            "user_id" => $user_id,
            "message" => "Search saved successfully."
        ];

        $api_status_code_class_call->respondOK($payload, "Search saved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
