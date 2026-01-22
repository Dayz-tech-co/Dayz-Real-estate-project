<?php

require __DIR__ . '/../../../vendor/autoload.php';
use Config\API_Status_Code;
use Config\API_User_Response;

require_once __DIR__ . "/../../../bootstrap.php";

// Init Classes
$api_status_code_class_call = new Config\API_Status_Code;
$db_call_class = new Config\DB_Calls_Functions;
$utility_class_call = new Config\Utility_Functions;

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate Admin
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1,1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        //Get filters
        $type = isset($_GET['type']) ? strtolower($utility_class_call->clean_user_data($_GET['type'])) : 'monthly';
        $month = isset($_GET['month']) ? (int)$utility_class_call->clean_user_data($_GET['month']) : null;
        $year  = isset($_GET['year']) ? (int)$utility_class_call->clean_user_data($_GET['year']) : date('Y');

        if ($type !== 'monthly' && $type !== 'annual') {
            $api_status_code_class_call->respondBadRequest($type, API_User_Response::$invalidreporttype);
        }

        //Build WHERE condition dynamically
        $whereConditions = [
            ['column' => 'status', 'operator' => '=', 'value' => 'completed']
        ];

        if ($type === 'monthly' && $month !== null) {
            $whereConditions[] = ['column' => 'MONTH(created_at)', 'operator' => '=', 'value' => $month];
            $whereConditions[] = ['column' => 'YEAR(created_at)', 'operator' => '=', 'value' => $year];
        } elseif ($type === 'annual') {
            $whereConditions[] = ['column' => 'YEAR(created_at)', 'operator' => '=', 'value' => $year];
        }

        //Fetch all transactions in that period
        $transactions = $db_call_class->selectRows(
            "transactions",
            "id, user_id, agent_id, property_id, amount, commission_amount, transaction_type, created_at",
            [$whereConditions]
        );

        // If empty
        if ($utility_class_call->input_is_invalid($transactions)) {
            $api_status_code_class_call->respondOK([], API_User_Response::$periodrecordnotfound);
        }

        //Calculate summaries
        $totalTransactions = count($transactions);
        $totalSales = 0;
        $totalRentals = 0;
        $grossAmount = 0;
        $totalCommission = 0;

        foreach ($transactions as $txn) {
            $grossAmount += (float)$txn['amount'];
            $totalCommission += (float)$txn['commission_amount'];
            if (strtolower($txn['transaction_type']) === 'buy') $totalSales++;
            if (strtolower($txn['transaction_type']) === 'rent') $totalRentals++;

            // Track agent earnings
            $agent_id = $txn['agent_id'];
            if (!isset($agentEarnings[$agent_id])) {
                $agentEarnings[$agent_id] = 0;
            }
            $agentEarnings[$agent_id] += (float)$txn['amount'];
        }

        // Sort and pick top 5 agents
        arsort($agentEarnings);
        $topAgents = array_slice($agentEarnings, 0, 5, true);

        // Fetch their details
        $topAgentsData = [];
        foreach ($topAgents as $agentId => $amount) {
            $agentData = $db_call_class->selectRows(
                "agents",
                "fname, lname, email",
                [[['column' => 'id', 'operator' => '=', 'value' => $agentId]]]
            );

            $topAgentsData[] = [
                'agent_id' => $agentId,
                'agent_name' => $agentData[0]['agency_name'] ,
                'agent_email' => $agentData[0]['email'],
                'total_earnings' => $amount
            ];


        }



        // Build response
        $report = [
            'report_type' => ucfirst($type),
            'month' => $type === 'monthly' ? date("F", mktime(0, 0, 0, $month, 1)) : null,
            'year' => $year,
            'total_transactions' => $totalTransactions,
            'total_sales' => $totalSales,
            'total_rentals' => $totalRentals,
            'gross_amount' => $grossAmount,
            'commission_earned' => $totalCommission,
             'top_5_agents' => $topAgentsData
        ];

        // Respond
        $api_status_code_class_call->respondOK($report, API_User_Response::$reportfetchedsuccessfully);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
