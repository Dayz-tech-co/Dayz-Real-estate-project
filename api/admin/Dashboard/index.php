<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init classes (consistent with your other admin endpoints)
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

        $getAdmin = $db_call_class->selectRows("admins", "id, fname, lname, email", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // -------------------------
        // 2) Key summary metrics
        // -------------------------
        // Total counts
        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_users FROM users");
        $total_users = (int)($res[0]['total_users'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_agents FROM agents");
        $total_agents = (int)($res[0]['total_agents'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_properties FROM properties");
        $total_properties = (int)($res[0]['total_properties'] ?? 0);

        // Pending counts (for admin action)
        $res = $db_call_class->selectRows("SELECT COUNT(*) AS pending_properties FROM properties WHERE status = 'pending'");
        $pending_properties = (int)($res[0]['pending_properties'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS pending_agents FROM agents WHERE status = 'pending'");
        $pending_agents = (int)($res[0]['pending_agents'] ?? 0);

        // For users, we treat pending as kyc_verified = 'pending' OR status = 'pending' if you use status column
        // adjust when you prefer a different definition
        $res = $db_call_class->selectRows("
            SELECT COUNT(*) AS pending_users 
            FROM users 
            WHERE (kyc_verified = 'pending' OR status = 'pending')
        ");
        $pending_users = (int)($res[0]['pending_users'] ?? 0);

        // Transaction overview
        $res = $db_call_class->selectRows("SELECT COUNT(*) AS total_transactions FROM transactions");
        $total_transactions = (int)($res[0]['total_transactions'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS completed_transactions FROM transactions WHERE status = 'completed'");
        $completed_transactions = (int)($res[0]['completed_transactions'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS failed_transactions FROM transactions WHERE status = 'failed'");
        $failed_transactions = (int)($res[0]['failed_transactions'] ?? 0);

        $res = $db_call_class->selectRows("SELECT COUNT(*) AS refunded_transactions FROM transactions WHERE status = 'refunded'");
        $refunded_transactions = (int)($res[0]['refunded_transactions'] ?? 0);

        // Financial totals (only completed transactions)
        // using `commission` column added earlier; if column name differs, change it
        $res = $db_call_class->selectRows("
            SELECT 
                COALESCE(SUM(amount), 0) AS gross_revenue,
                COALESCE(SUM(commission), 0) AS total_commission
            FROM transactions
            WHERE status = 'completed'
        ");
        $gross_revenue = (float)($res[0]['gross_revenue'] ?? 0.00);
        $total_commission = (float)($res[0]['total_commission'] ?? 0.00);

        // -------------------------
        // 3) Recent items (quick lists)
        // -------------------------
        // Recent 5 transactions (most recent)
        $recentTransactions = $db_call_class->selectRows(
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

        // Recent pending properties (limit 5)
        $recentPendingProperties = $db_call_class->selectRows(
            "properties p",
            [
                "p.id",
                "p.title",
                "p.property_type",
                "p.price",
                "p.city",
                "p.state",
                "p.status",
                "p.created_at",
                "a.id AS agent_id",
                "a.agency_name"
            ],
            [[
                ['column' => 'p.status', 'operator' => '=', 'value' => 'pending']
            ]],
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

        // Recent pending agents (limit 5)
        $recentPendingAgents = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, phoneno, status, created_at",
            [[
                ['column' => 'status', 'operator' => '=', 'value' => 'pending']
            ]],
            [
                'limit' => 5,
                'pageno' => 1,
                'orderBy' => 'created_at',
                'orderDirection' => 'DESC'
            ]
        );

        // -------------------------
        // 4) Compose dashboard payload
        // -------------------------
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

        // -------------------------
        // 5) Send response
        // -------------------------
        $api_status_code_class_call->respondOK($payload, "Admin dashboard summary retrieved.");

    } catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError(
            $utility_class_call->get_details_from_exception($e)
        );
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
