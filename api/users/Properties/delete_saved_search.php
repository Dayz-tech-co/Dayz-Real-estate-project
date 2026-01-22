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

        $saved_search_id = isset($data['saved_search_id']) ? $utility_class_call->clean_user_data($data['saved_search_id'], 1) : '';
        $search_name = isset($data['search_name']) ? $utility_class_call->clean_user_data($data['search_name'], 0) : '';

        if ($utility_class_call->input_is_invalid($saved_search_id) && $utility_class_call->input_is_invalid($search_name)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid . " - Provide either saved_search_id or search_name");
        }

        $whereConditions = [
            ["column" => "user_id", "operator" => "=", "value" => $user_id]
        ];

        if (!$utility_class_call->input_is_invalid($saved_search_id)) {
            $whereConditions[] = ["column" => "id", "operator" => "=", "value" => $saved_search_id];
        } elseif (!$utility_class_call->input_is_invalid($search_name)) {
            $whereConditions[] = ["column" => "name", "operator" => "=", "value" => $search_name];
        }

        // Check if the saved search exists for this user
        $existing = $db_call_class->selectRows("saved_searches", "id, name", $whereConditions);

        if ($utility_class_call->input_is_invalid($existing)) {
            $api_status_code_class_call->respondNotFound([], "Saved search not found.");
        }

        // Delete the saved search
        $deleteResponse = $db_call_class->deleteRows("saved_searches", $whereConditions);

        if ($deleteResponse <= 0) {
            $api_status_code_class_call->respondInternalError("Failed to delete saved search.");
        }

        $payload = [
            "deleted_search_id" => $existing[0]['id'],
            "deleted_search_name" => $existing[0]['name'],
            "user_id" => $user_id,
            "message" => "Saved search deleted successfully."
        ];

        $api_status_code_class_call->respondOK($payload, "Saved search deleted successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
