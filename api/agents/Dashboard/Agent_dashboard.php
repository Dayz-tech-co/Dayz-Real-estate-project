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

$api_method = "POST";
header("Content-Type: application/json");

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // Validate Agent Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
        $agent_pubkey = $decodedToken->usertoken;

        // Fetch Agent
        $getAgent = $db_call_class->selectRows(
            "agents",
            "id, agency_name, email, status, kyc_verified",
            [[
                ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
            ]]
        );

        if ($utility_class_call->input_is_invalid($getAgent)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $agent = $getAgent[0];
        $agent_id = $agent['id'];

        // Ensure KYC Verified
        if (strtolower($agent['kyc_verified']) !== 'verified') {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$kycnotverified);
        }

        // === Properties & Performance Summary ===
        // Get all properties for this agent
        $allProperties = $db_call_class->selectRows(
            "properties",
            "id, title, property_type, property_category, price, city, state, status, verified, sold_status, created_at, featured",
            [[
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
                ['column' => 'deleted_at', 'operator' => 'IS NULL']
            ]]
        );

        // === Property Status Breakdown ===
        $propertyStats = [
            'total' => count($allProperties),
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'flagged' => 0,
            'featured' => 0,
            'available' => 0,
            'sold' => 0,
            'rented' => 0
        ];

        foreach ($allProperties as $prop) {
            $status = strtolower($prop['status']);
            $soldStatus = strtolower($prop['sold_status'] ?? 'available');

            if ($status == 'approved') $propertyStats['approved']++;
            elseif ($status == 'pending') $propertyStats['pending']++;
            elseif ($status == 'rejected') $propertyStats['rejected']++;
            elseif ($status == 'flagged') $propertyStats['flagged']++;

            if ($prop['featured'] == 1) $propertyStats['featured']++;

            if ($soldStatus == 'sold') $propertyStats['sold']++;
            elseif ($soldStatus == 'rented') $propertyStats['rented']++;
            else $propertyStats['available']++;
        }

        // === Financial Performance ===
        $total_sales = 0;
        $total_rentals = 0;
        $total_agent_earnings = 0;

        // Aggregate transactions data
        $txnStats = $db_call_class->selectRows("
            SELECT
                transaction_type,
                COUNT(*) AS transaction_count,
                SUM(amount) AS total_amount,
                SUM(agent_amount) AS total_agent_earning,
                COUNT(DISTINCT user_id) AS unique_buyers
            FROM transactions
            WHERE agent_id = ? AND status = 'completed'
            GROUP BY transaction_type
        ", [$agent_id]);

        foreach ($txnStats as $txn) {
            if ($txn['transaction_type'] === 'buy') {
                $total_sales = (float)$txn['total_amount'];
            } elseif ($txn['transaction_type'] === 'rent') {
                $total_rentals = (float)$txn['total_amount'];
            }
            $total_agent_earnings += (float)$txn['total_agent_earning'];
        }

        // === Monthly Performance Trend ===
        $monthlyStats = $db_call_class->selectRows("
            SELECT
                YEAR(created_at) AS year,
                MONTH(created_at) AS month,
                transaction_type,
                COUNT(*) AS transactions,
                SUM(amount) AS revenue
            FROM transactions
            WHERE agent_id = ? AND status = 'completed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at), transaction_type
            ORDER BY year DESC, month DESC
            LIMIT 12
        ", [$agent_id]);

        // Format monthly data for charts
        $monthlyPerformance = [];
        foreach ($monthlyStats as $stat) {
            $period = $stat['year'] . '-' . str_pad($stat['month'], 2, '0', STR_PAD_LEFT);
            if (!isset($monthlyPerformance[$period])) {
                $monthlyPerformance[$period] = [
                    'period' => $period,
                    'sales' => 0,
                    'rentals' => 0,
                    'transactions' => 0
                ];
            }
            if ($stat['transaction_type'] == 'buy') {
                $monthlyPerformance[$period]['sales'] = (float)$stat['revenue'];
            } else {
                $monthlyPerformance[$period]['rentals'] = (float)$stat['revenue'];
            }
            $monthlyPerformance[$period]['transactions'] += (int)$stat['transactions'];
        }

        // === Booking/Visit Requests ===
        $visitRequests = $db_call_class->selectRows("
            SELECT
                b.id, b.visit_date, b.end_date, b.status, b.created_at,
                p.title AS property_title,
                u.fname, u.lname, u.email, u.phoneno
            FROM bookings b
            JOIN properties p ON b.property_id = p.id
            JOIN users u ON b.user_id = u.id
            WHERE p.agent_id = ?
            ORDER BY b.created_at DESC
            LIMIT 10
        ", [$agent_id]);

        $bookingStats = [
            'total_requests' => count($visitRequests),
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'cancelled' => 0
        ];

        foreach ($visitRequests as $booking) {
            $status = strtolower($booking['status']);
            if ($status == 'pending') $bookingStats['pending']++;
            elseif ($status == 'confirmed') $bookingStats['confirmed']++;
            elseif ($status == 'completed') $bookingStats['completed']++;
            elseif ($status == 'cancelled') $bookingStats['cancelled']++;
        }

        // === Top Performing Properties ===
        $topProperties = $db_call_class->selectRows("
            SELECT
                p.id, p.title, p.property_type, p.price, p.city, p.state,
                COUNT(t.id) AS total_transactions,
                SUM(t.amount) AS total_revenue,
                SUM(t.agent_amount) AS agent_revenue
            FROM properties p
            LEFT JOIN transactions t ON t.property_id = p.id AND t.status = 'completed'
            WHERE p.agent_id = ? AND p.status = 'approved'
            GROUP BY p.id
            ORDER BY total_revenue DESC
            LIMIT 5
        ", [$agent_id]);

        // === Buyers Summary ===
        $topBuyers = $db_call_class->selectRows("
            SELECT
                u.id AS user_id, u.fname, u.lname, u.email, u.phoneno, u.city, u.state, u.kyc_verified,
                COUNT(t.id) AS total_transactions,
                SUM(t.amount) AS total_spent,
                MAX(t.created_at) AS last_transaction
            FROM users u
            LEFT JOIN transactions t ON t.user_id = u.id
            LEFT JOIN properties p ON p.id = t.property_id
            WHERE p.agent_id = ?
            GROUP BY u.id
            ORDER BY total_spent DESC
            LIMIT 10
        ", [$agent_id]);

        // === Agent Performance Rankings ===
        $agentRankings = [];
        if (true) { // Enable performance comparison
            $agentTotalEarnings = $db_call_class->selectRows("
                SELECT agent_id, SUM(agent_amount) AS total_earnings
                FROM transactions
                WHERE status = 'completed'
                GROUP BY agent_id
                ORDER BY total_earnings DESC
                LIMIT 20
            ");

            $currentAgentEarning = $total_agent_earnings;
            $rank = 0;
            $found = false;

            foreach ($agentTotalEarnings as $earning) {
                $rank++;
                if ($earning['agent_id'] == $agent_id) {
                    $found = true;
                    break;
                }
            }

            $agentRankings = [
                'rank' => $found ? $rank : 'Unranked',
                'total_agents' => count($agentTotalEarnings),
                'top_agent_earnings' => !empty($agentTotalEarnings) ? (float)$agentTotalEarnings[0]['total_earnings'] : 0
            ];
        }

        // === Notifications Count ===
        $unreadNotifications = $db_call_class->selectRows(
            "notifications",
            "COUNT(*) AS count",
            [[
                ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
                ['column' => 'is_read', 'operator' => '=', 'value' => 0]
            ]]
        );
        $unread_count = (int)($unreadNotifications[0]['count'] ?? 0);

        // === Dashboard Response ===
        $dashboard = [
            "agent_info" => [
                "id" => $agent_id,
                "agency_name" => $agent['agency_name'],
                "email" => $agent['email'],
                "kyc_verified" => $agent['kyc_verified']
            ],
            "performance_summary" => [
                "total_sales" => $total_sales,
                "total_rentals" => $total_rentals,
                "total_agent_earnings" => $total_agent_earnings,
                "total_properties" => count($properties)
            ],
            "properties" => $properties_report,
            "top_buyers" => $buyers
        ];

        $api_status_code_class_call->respondOK($dashboard, "Dashboard data retrieved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
