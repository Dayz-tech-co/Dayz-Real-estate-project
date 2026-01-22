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
        $fullname = $getUser[0]['fname'] . " " . $getUser[0]['lname'];

        // Collect request body (JSON)
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $property_ids = $data['property_ids'] ?? '';

        if ($utility_class_call->input_is_invalid($property_ids) || !is_array($property_ids) || count($property_ids) < 2 || count($property_ids) > 4) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
        }

        // Sanitize and filter property IDs
        $filtered_ids = [];
        foreach ($property_ids as $id) {
            $clean_id = $utility_class_call->clean_user_data($id, 1); // Assuming this sanitizes to integer
            if (is_numeric($clean_id) && $clean_id > 0) {
                $filtered_ids[] = (int)$clean_id;
            }
        }

        if (count($filtered_ids) < 2 || count($filtered_ids) > 4) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
        }

        // Fetch properties
        $placeholders = str_repeat('?,', count($filtered_ids) - 1) . '?';
        $sql = "
            SELECT
                id, title, description, price, property_category, property_type, bed, bath, balc, hall, kitc, floor, asize,
                city, state, location, images, feature, created_at, updated_at,
                (SELECT agency_name FROM agents WHERE id = properties.agent_id) as agency_name
            FROM properties
            WHERE status = 'approved'
              AND deleted_at IS NULL
              AND id IN ($placeholders)
            ORDER BY FIELD(id, " . implode(',', $filtered_ids) . ")
        ";

        $properties = $db_call_class->selectRows($sql, $filtered_ids);

        if ($utility_class_call->input_is_invalid($properties) || count($properties) !== count($filtered_ids)) {
            $api_status_code_class_call->respondNotFound([], API_User_Response::$propertynotfound);
        }

        // Decode images and split features
        foreach ($properties as &$p) {
            $p['images'] = $utility_class_call->input_is_invalid($p['images']) ? [] : json_decode($p['images'], true);
            $p['features'] = $utility_class_call->input_is_invalid($p['feature']) ? [] : explode(',', $p['feature']);
            unset($p['feature']); // Remove original feature field
        }

        $payload = [
            "user_id" => $user_id,
            "fullname" => $fullname,
            "properties" => $properties,
            "compared_properties_count" => count($properties)
        ];

        $api_status_code_class_call->respondOK($payload, "Property comparison data retrieved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
