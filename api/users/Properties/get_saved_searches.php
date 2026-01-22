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

        // Collect request body (JSON) for optional pagination
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $page = isset($data["page"]) ? max(1, (int)$data["page"]) : 1;
        $limit = isset($data["limit"]) ? max(1, min(50, (int)$data["limit"])) : 10;
        $offset = ($page - 1) * $limit;

        // Fetch saved searches for the user
        $searches = $db_call_class->selectRows(
            "saved_searches",
            "id, name, criteria, created_at, updated_at",
            [[
                ['column' => 'user_id', 'operator' => '=', 'value' => $user_id]
            ]]
        );

        if ($utility_class_call->input_is_invalid($searches)) {
            $searches = [];
        }

        // Decode criteria and paginate
        $total = count($searches);
        $paginatedSearches = array_slice($searches, $offset, $limit);

        // Decode JSON criteria
        foreach ($paginatedSearches as &$search) {
            $search['criteria'] = json_decode($search['criteria'], true);
        }

        $payload = [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "count" => count($paginatedSearches),
            "saved_searches" => $paginatedSearches
        ];

        $api_status_code_class_call->respondOK($payload, "Saved searches retrieved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
