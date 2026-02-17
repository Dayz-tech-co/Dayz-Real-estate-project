<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

// Init classes
$api_status_call = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

$api_method = "GET";
header("Content-type: application/json; charset=UTF-8;");

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== $api_method) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    // === 1) Admin validation ===
    $decodedToken = $api_status_call->ValidateAPITokenSentIN(1, 1);
    $admin_pubkey = $decodedToken->usertoken;

    $getAdmin = $db_call->selectRows(
        "admins",
        "id, fname, lname, email",
        [[['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]]]
    );

    if ($utility_class_call->input_is_invalid($getAdmin)) {
        $api_status_call->respondUnauthorized();
    }

    // === 2) Key summary metrics ===
    $total_users = (int)($db_call->selectRows("users", ["COUNT(*) AS total_users"])[0]['total_users'] ?? 0);
    $total_agents = (int)($db_call->selectRows("agents", ["COUNT(*) AS total_agents"])[0]['total_agents'] ?? 0);
    $total_properties = (int)($db_call->selectRows("properties", ["COUNT(*) AS total_properties"])[0]['total_properties'] ?? 0);

    $pending_properties = (int)($db_call->selectRows(
        "properties",
        ["COUNT(*) AS pending_properties"],
        [[['column' => 'status', 'operator' => '=', 'value' => 'pending']]]
    )[0]['pending_properties'] ?? 0);

    $pending_agents = (int)($db_call->selectRows(
        "agents",
        ["COUNT(*) AS pending_agents"],
        [[['column' => 'status', 'operator' => '=', 'value' => 'pending']]]
    )[0]['pending_agents'] ?? 0);

    $pending_users = (int)($db_call->selectRows(
        "users",
        ["COUNT(*) AS pending_users"],
        [[
            ['column' => 'kyc_verified', 'operator' => '=', 'value' => 'pending'],
            ['column' => 'status', 'operator' => '=', 'value' => 'pending']
        ]],
        "OR" // top-level operator between the two conditions
    )[0]['pending_users'] ?? 0);

    $total_transactions = (int)($db_call->selectRows("transactions", ["COUNT(*) AS total_transactions"])[0]['total_transactions'] ?? 0);
    $completed_transactions = (int)($db_call->selectRows(
        "transactions",
        ["COUNT(*) AS completed_transactions"],
        [[['column' => 'status', 'operator' => '=', 'value' => 'completed']]]
    )[0]['completed_transactions'] ?? 0);

    $failed_transactions = (int)($db_call->selectRows(
        "transactions",
        ["COUNT(*) AS failed_transactions"],
        [[['column' => 'status', 'operator' => '=', 'value' => 'failed']]]
    )[0]['failed_transactions'] ?? 0);

    $refunded_transactions = (int)($db_call->selectRows(
        "transactions",
        ["COUNT(*) AS refunded_transactions"],
        [[['column' => 'status', 'operator' => '=', 'value' => 'refunded']]]
    )[0]['refunded_transactions'] ?? 0);

    // Financial totals (completed transactions only)
    $financials = $db_call->selectRows(
        "transactions",
        [
            "COALESCE(SUM(amount), 0) AS gross_revenue",
            "COALESCE(SUM(commission), 0) AS total_commission"
        ],
        [[['column' => 'status', 'operator' => '=', 'value' => 'completed']]]
    );

    $gross_revenue = (float)($financials[0]['gross_revenue'] ?? 0.00);
    $total_commission = (float)($financials[0]['total_commission'] ?? 0.00);

    // === 3) Recent items (quick lists) ===
    $recentTransactions = $db_call->selectRows(
        "transactions t",
        [
            "t.id",
            "t.transaction_id",
            "t.amount",
            "t.transaction_type",
            "t.status",
            "t.created_at",
            "u.id AS user_id",
            "u.fullname AS user_name",
            "a.id AS agent_id",
            "a.agency_name",
            "p.id AS property_id",
            "p.title AS property_title",
            "t.commission",
            "t.agent_amount"
        ],
        [], // no where conditions
        [
            'limit' => 5,
            'pageno' => 1,
            'joins' => [
                ['type' => 'LEFT', 'table' => 'users u', 'condition' => 't.user_id = u.id'],
                ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 't.agent_id = a.id'],
                ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 't.property_id = p.id']
            ],
            'orderBy' => 't.created_at',
            'orderDirection' => 'DESC'
        ]
    );

    $recentPendingProperties = $db_call->selectRows(
        "properties p",
        ["p.id", "p.title", "p.property_type", "p.price", "p.city", "p.state", "p.status", "p.created_at", "a.id AS agent_id", "a.agency_name"],
        [[['column' => 'p.status', 'operator' => '=', 'value' => 'pending']]],
        [
            'limit' => 5,
            'pageno' => 1,
            'joins' => [
                ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 'p.agent_id = a.id']
            ],
            'orderBy' => 'p.created_at',
            'orderDirection' => 'DESC'
        ]
    );

    $recentPendingAgents = $db_call->selectRows(
        "agents",
        ["id", "agency_name", "email", "phoneno", "status", "created_at"],
        [[['column' => 'status', 'operator' => '=', 'value' => 'pending']]],
        [
            'limit' => 5,
            'pageno' => 1,
            'orderBy' => 'created_at',
            'orderDirection' => 'DESC'
        ]
    );

    // === 4) Compose dashboard payload ===
    $payload = [
        'summary' => [
            'users' => [
                'total' => $total_users,
                'pending' => $pending_users,
            ],
            'agents' => [
                'total' => $total_agents,
                'pending' => $pending_agents,
            ],
            'properties' => [
                'total' => $total_properties,
                'pending' => $pending_properties,
            ],
            'transactions' => [
                'total' => $total_transactions,
                'completed' => $completed_transactions,
                'failed' => $failed_transactions,
                'refunded' => $refunded_transactions,
            ],
            'financials' => [
                'gross_revenue' => number_format($gross_revenue, 2, '.', ''),
                'total_commission' => number_format($total_commission, 2, '.', '')
            ]
        ],
        'recent' => [
            'transactions' => $recentTransactions,
            'pending_properties' => $recentPendingProperties,
            'pending_agents' => $recentPendingAgents
        ]
    ];

    // === 5) Send response ===
    $api_status_call->respondOK($payload, "Admin dashboard summary retrieved.");

} catch (\Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
