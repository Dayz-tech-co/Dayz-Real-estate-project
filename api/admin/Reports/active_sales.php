<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init classes
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class              = new Config\DB_Calls_Functions;
$utility_class_call         = new Config\Utility_Functions;

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // -------------------------
        // 1) Admin validation
        // -------------------------
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // -------------------------
        // 2) Compute stats via SQL (using db helper)
        // -------------------------
        // Active users: KYC verified OR has at least one completed transaction
        $sqlActiveUsers = "
            SELECT COUNT(DISTINCT u.id) AS total_active_users
            FROM users u
            LEFT JOIN transactions t ON u.id = t.user_id AND t.status = 'completed'
            WHERE u.kyc_verified = 'verified' OR t.id IS NOT NULL
        ";
        $res = $db_call_class->selectRows($sqlActiveUsers);
        $active_users = (int)($res[0]['total_active_users'] ?? 0);

        // Active agents: KYC verified OR has at least one approved property
        $sqlActiveAgents = "
            SELECT COUNT(DISTINCT a.id) AS total_active_agents
            FROM agents a
            LEFT JOIN properties p ON a.id = p.agent_id AND p.status = 'approved'
            WHERE a.kyc_verified = 'verified' OR p.id IS NOT NULL
        ";
        $res = $db_call_class->selectRows($sqlActiveAgents);
        $active_agents = (int)($res[0]['total_active_agents'] ?? 0);

        // Active properties: properties currently approved
        $sqlActiveProperties = "
            SELECT COUNT(*) AS total_active_properties
            FROM properties
            WHERE status = 'approved'
        ";
        $res = $db_call_class->selectRows($sqlActiveProperties);
        $active_properties = (int)($res[0]['total_active_properties'] ?? 0);

        // Totals (overall)
        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_users FROM users");
        $total_users = (int)($res[0]['total_users'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_agents FROM agents");
        $total_agents = (int)($res[0]['total_agents'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_properties FROM properties");
        $total_properties = (int)($res[0]['total_properties'] ?? 0);

        // -------------------------
        // 3) Build response
        // -------------------------
        $data = [
            'active_users' => $active_users,
            'active_agents' => $active_agents,
            'active_properties' => $active_properties,
            'totals' => [
                'total_users' => $total_users,
                'total_agents' => $total_agents,
                'total_properties' => $total_properties
            ]
        ];

        $api_status_code_class_call->respondOK($data, API_User_Response::$activitiesfetchedsuccessfully);

    } catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError(
            $utility_class_call->get_details_from_exception($e)
        );
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
