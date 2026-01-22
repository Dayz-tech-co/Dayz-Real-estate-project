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

header("Content-type: application/json");

$api_method = "GET";

if ($_SERVER['REQUEST_METHOD'] === $api_method) {
    try {
        // Validate Admin Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // Confirm admin exists
        $getAdmin = $db_call_class->selectRows(
            "admins",
            "id, fname, email",
            [[
                ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
            ]],
            ['limit' => 1]
        );

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // Filters & pagination
        $status   = isset($_GET['status']) ? $utility_class_call->clean_user_data(strtolower($_GET['status']), 1) : '';
        $city     = isset($_GET['city']) ? $utility_class_call->clean_user_data(strtolower($_GET['city']), 1) : '';
        $state    = isset($_GET['state']) ? $utility_class_call->clean_user_data(strtolower($_GET['state']), 1) : '';
        $agent_id = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : '';
        $page     = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit    = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
        $offset   = ($page - 1) * $limit;
            

        // Allowed statuses
    $allowedStatuses = ['pending', 'approved', 'rejected', 'flagged'];

    // Validate status
    if ($status !== null && !in_array(strtolower($status), $allowedStatuses)) {
        throw new Exception("Invalid status filter. Allowed: " . implode(", ", $allowedStatuses));
    }
        // Build where conditions in the same style used in create_property
        // (an array that contains a group array of conditions)
        $whereConditions = [[]]; // empty group = no WHERE filters
        if (!empty($status)) {
            $whereConditions[0][] = ['column' => 'status', 'operator' => '=', 'value' => $status];
        }
        if (!empty($city)) {
            // Use LOWER(...) in the selectRows call so comparisons are consistent
            $whereConditions[0][] = ['column' => 'LOWER(city)', 'operator' => '=', 'value' => strtolower($city)];
        }
        if (!empty($state)) {
            $whereConditions[0][] = ['column' => 'LOWER(state)', 'operator' => '=', 'value' => strtolower($state)];
        }
        if (!empty($agent_id)) {
            $whereConditions[0][] = ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id];
        }

        // Options for selectRows (limit/offset/order)
        $options = [
            'limit' => $limit,
            'offset' => $offset,
            'orderBy' => 'created_at',
            'orderDirection' => 'DESC'
        ];

        // Columns to fetch
        $selectColumns = "id, agency_name, title, slug, description, property_type, property_category, bed, bath, balc, hall, kitc, floor, asize, price, feature, featured, city, state, location, agent_id, images, status, created_at";

        // Fetch properties
        $properties = $db_call_class->selectRows(
            "properties",
            $selectColumns,
            $whereConditions,
            $options
        );

        // If selectRows returns empty or invalid, normalize to empty array
        if ($utility_class_call->input_is_invalid($properties)) {
            $properties = [];
        }

        // Attach agent brief info to each property (keeping in line with your patterns)
        foreach ($properties as &$prop) {
            // protect against missing agent_id
            $aid = isset($prop['agent_id']) ? (int)$prop['agent_id'] : 0;
            if ($aid > 0) {
                $agent = $db_call_class->selectRows(
                    "agents",
                    "id, agency_name, email, status",
                    [[
                        ['column' => 'id', 'operator' => '=', 'value' => $aid]
                    ]],
                    ['limit' => 1]
                );
                $prop['agent'] = !$utility_class_call->input_is_invalid($agent) ? $agent[0] : null;
            } else {
                $prop['agent'] = null;
            }

            // decode images JSON if set
            if (!empty($prop['images'])) {
                $decoded = json_decode($prop['images'], true);
                $prop['images'] = is_array($decoded) ? $decoded : [];
            } else {
                $prop['images'] = [];
            }
        }
        unset($prop); // break reference

        // Count for returned page (note: this is count of fetched rows; if you want total count across DB, see note below)
        $count = count($properties);

        // Final response payload
        $payload = [
            "page" => $page,
            "limit" => $limit,
            "count" => $count,
            "properties" => $properties
        ];

        $api_status_code_class_call->respondOK($payload, API_User_Response::$propertiesfetched);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
