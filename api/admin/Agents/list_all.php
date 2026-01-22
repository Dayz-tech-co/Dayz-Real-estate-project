<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init classes
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $user_pubkey  = $decodedToken->usertoken;

        // Confirm it's an admin
        $getuserattached = $db_call_class->selectRows(
            "admins",
            "id",
            [[
                ['column' => 'adminpubkey', 'operator' => '=', 'value' => $user_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getuserattached)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // Params
        $status = isset($_GET['status']) ? $utility_class_call->clean_user_data(strtolower( $_GET['status'])) : '';
        $page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit  = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

        if ($page <= 0) $page = 1;
        if ($limit <= 0) $limit = 10;

        $validStatuses = ['pending', 'approved', 'rejected', 'suspended'];
        if (!in_array($status, $validStatuses)) {
            $api_status_code_class_call->respondBadRequest($status, $validStatuses, API_User_Response::$invalidstatusfilter);
        }

        $offset = ($page - 1) * $limit;

        //Get total count by status
        $totalAgentsData = $db_call_class->selectRows("agents", [
            [
                ['column' => 'status', 'operator' => '=', 'value' => $status]
            ]
        ]);
        $totalAgents = $totalAgentsData['count'] ?? 0;

        // Fetch rows with pagination
        $getAgents = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, phoneno, business_address, status, created_at",
            [
                [
                    ['column' => 'status', 'operator' => '=', 'value' => $status]
                ]
            ],
            $limit,
            $offset
        );

        if ($utility_class_call->input_is_invalid($getAgents)) {
            $api_status_code_class_call->respondNotFound($getAgents, API_User_Response:: $agentswithstatusnotfound.$status);
        }

        $totalPages = ceil($totalAgents / $limit);

        // Response
        $response = [
            "message"       => "Agents with status: $status",
            "status_filter" => $status,
            "page"          => $page,
            "limit"         => $limit,
            "total_agents"  => $totalAgents,
            "total_pages"   => $totalPages,
            "agents"        => $getAgents
        ];

        $api_status_code_class_call->respondOK($response, API_User_Response::$agentsfetchedsuccessfully);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

