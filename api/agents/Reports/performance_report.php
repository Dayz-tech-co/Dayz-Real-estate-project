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

if (getenv('REQUEST_METHOD') !== $api_method) {
    $api_status_code_class_call->respondMethodNotAlowed();
    exit;
}

try {
    // Agent validation
    $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 2);
    $agent_pubkey = $decodedToken->usertoken;

    $getAgent = $db_call_class->selectRows("agents", "id, agency_name", [[
        ['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getAgent)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    $agent = $getAgent[0];
    $agent_id = $agent['id'];

    // Report parameters
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'comprehensive';

    // Validate date range
    if (strtotime($start_date) > strtotime($end_date)) {
        $api_status_code_class_call->respondBadRequest("Start date cannot be after end date");
    }

    // === Financial Performance ===
    $financialPerformance = $db_call_class->selectRows("
        SELECT
            COUNT(t.id) AS total_transactions,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN t.status = 'completed' THEN t.agent_amount ELSE 0 END) AS agent_earnings,
            SUM(CASE WHEN t.status = 'completed' THEN t.commission ELSE 0 END) AS platform_commission,
            AVG(CASE WHEN t.status = 'completed' THEN t.amount ELSE NULL END) AS avg_transaction_value,
            MAX(CASE WHEN t.status = 'completed' THEN t.amount ELSE NULL END) AS highest_transaction
        FROM transactions t
        LEFT JOIN properties p ON t.property_id = p.id
        WHERE p.agent_id = ? AND t.created_at BETWEEN ? AND ?
    ", [$agent_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Monthly Performance Trend ===
    $monthlyTrends = $db_call_class->selectRows("
        SELECT
            DATE_FORMAT(t.created_at, '%Y-%m') AS month,
            YEAR(t.created_at) AS year,
            MONTH(t.created_at) AS month_num,
            COUNT(t.id) AS transaction_count,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) AS monthly_revenue,
            SUM(CASE WHEN t.status = 'completed' THEN t.agent_amount ELSE 0 END) AS monthly_earnings
        FROM transactions t
        LEFT JOIN properties p ON t.property_id = p.id
        WHERE p.agent_id = ? AND t.created_at BETWEEN ? AND ? AND t.status = 'completed'
        GROUP BY YEAR(t.created_at), MONTH(t.created_at), DATE_FORMAT(t.created_at, '%Y-%m')
        ORDER BY year DESC, month_num DESC
    ", [$agent_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Property Performance Analysis ===
    $propertyPerformance = $db_call_class->selectRows("
        SELECT
            p.id AS property_id,
            p.title,
            p.property_category,
            p.property_type,
            p.price,
            p.city,
            p.state,
            p.status AS property_status,
            COUNT(t.id) AS sales_count,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) AS total_sales_revenue,
            SUM(CASE WHEN t.status = 'completed' THEN t.agent_amount ELSE 0 END) AS agent_share_from_property,
            AVG(CASE WHEN t.status = 'completed' THEN t.amount ELSE NULL END) AS avg_sale_price,
            COUNT(CASE WHEN t.status = 'completed' THEN 1 END) AS completed_transactions
        FROM properties p
        LEFT JOIN transactions t ON p.id = t.property_id AND t.created_at BETWEEN ? AND ?
        WHERE p.agent_id = ?
        GROUP BY p.id, p.title, p.property_category, p.property_type, p.price, p.city, p.state, p.status
        ORDER BY total_sales_revenue DESC, p.created_at DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $agent_id]);

    // === Customer/Buyer Analysis ===
    $customerAnalysis = $db_call_class->selectRows("
        SELECT
            u.id AS user_id,
            u.fname,
            u.lname,
            u.email,
            u.city,
            u.state,
            COUNT(t.id) AS total_purchases,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) AS total_spent,
            AVG(CASE WHEN t.status = 'completed' THEN t.amount ELSE NULL END) AS avg_purchase_value,
            MAX(t.created_at) AS last_purchase_date,
            GROUP_CONCAT(DISTINCT p.property_type SEPARATOR ', ') AS property_types_purchased
        FROM users u
        JOIN transactions t ON u.id = t.user_id AND t.status = 'completed'
        LEFT JOIN properties p ON t.property_id = p.id
        WHERE p.agent_id = ? AND t.created_at BETWEEN ? AND ?
        GROUP BY u.id, u.fname, u.lname, u.email, u.city, u.state
        ORDER BY total_spent DESC
        LIMIT 20
    ", [$agent_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Property Category Performance ===
    $categoryPerformance = $db_call_class->selectRows("
        SELECT
            p.property_category,
            COUNT(DISTINCT p.id) AS total_properties,
            COUNT(CASE WHEN t.status = 'completed' THEN t.id END) AS successful_sales,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) AS total_revenue,
            AVG(CASE WHEN t.status = 'completed' THEN t.amount ELSE NULL END) AS avg_sale_price
        FROM properties p
        LEFT JOIN transactions t ON p.id = t.property_id AND t.created_at BETWEEN ? AND ? AND t.status = 'completed'
        WHERE p.agent_id = ?
        GROUP BY p.property_category
        HAVING property_category IS NOT NULL
        ORDER BY total_revenue DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $agent_id]);

    // === Geographic Performance ===
    $geographicPerformance = $db_call_class->selectRows("
        SELECT
            p.city,
            p.state,
            COUNT(DISTINCT p.id) AS properties_listed,
            COUNT(CASE WHEN t.status = 'completed' THEN t.id END) AS successful_sales,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) AS total_revenue,
            AVG(CASE WHEN t.status = 'completed' THEN t.amount ELSE NULL END) AS avg_sale_price
        FROM properties p
        LEFT JOIN transactions t ON p.id = t.property_id AND t.created_at BETWEEN ? AND ? AND t.status = 'completed'
        WHERE p.agent_id = ? AND p.city IS NOT NULL
        GROUP BY p.city, p.state
        ORDER BY total_revenue DESC
        LIMIT 15
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $agent_id]);

    // === Agent Performance Benchmarking ===
    $agentBenchmarking = [];

    // Compare with all agents for ranking
    $allAgentsStats = $db_call_class->selectRows("
        SELECT
            a.id AS agent_id,
            a.agency_name,
            COUNT(t.id) AS transaction_count,
            SUM(t.agent_amount) AS total_earnings
        FROM agents a
        LEFT JOIN properties p ON a.id = p.agent_id
        LEFT JOIN transactions t ON p.id = t.property_id AND t.status = 'completed' AND t.created_at BETWEEN ? AND ?
        GROUP BY a.id, a.agency_name
        HAVING transaction_count > 0
        ORDER BY total_earnings DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Find current agent's rank and calculate percentiles
    $currentAgentStats = null;
    $rank = 0;
    $totalAgents = count($allAgentsStats);

    foreach ($allAgentsStats as $index => $agentStats) {
        if ($agentStats['agent_id'] == $agent_id) {
            $rank = $index + 1;
            $currentAgentStats = $agentStats;
            break;
        }
    }

    if ($rank > 0 && $totalAgents > 0) {
        $performancePercentile = (($totalAgents - $rank + 1) / $totalAgents) * 100;

        $agentBenchmarking = [
            'agent_rank' => [
                'rank' => $rank,
                'total_agents' => $totalAgents,
                'performance_percentile' => round($performancePercentile, 1)
            ],
            'comparative_analysis' => [
                'top_performer_earnings' => $allAgentsStats[0]['total_earnings'] ?? 0,
                'agent_earnings_vs_top' => !empty($allAgentsStats) && ($allAgentsStats[0]['total_earnings'] ?? 0) > 0 ?
                    round((($currentAgentStats['total_earnings'] ?? 0) / $allAgentsStats[0]['total_earnings']) * 100, 1) : 0,
                'market_position' => $rank === 1 ? 'Market Leader' : ($rank <= 5 ? 'Top Performer' : 'Growing Agent')
            ]
        ];
    }

    // === Performance Insights & Recommendations ===
    $insights = [];

    // Check for seasonal trends
    $seasonalTrends = [];
    foreach ($monthlyTrends as $trend) {
        $monthStr = $trend['month'];
        $seasonalTrends[$monthStr] = [
            'transactions' => $trend['transaction_count'],
            'revenue' => $trend['monthly_revenue']
        ];
    }

    // Identify best performing month
    $bestMonth = !empty($seasonalTrends) ? max($seasonalTrends) : null;

    // Generate insights
    $financial = $financialPerformance[0] ?? [];
    $totalTransactions = $financial['total_transactions'] ?? 0;
    $totalRevenue = $financial['total_revenue'] ?? 0;

    if ($totalTransactions > 0) {
        // Calculate conversion rates for listed vs sold properties
        $totalProperties = count($propertyPerformance);
        $soldProperties = count(array_filter($propertyPerformance, fn($p) => $p['completed_transactions'] > 0));

        $insights[] = [
            'type' => 'conversion_rate',
            'title' => 'Property Conversion Rate',
            'value' => round(($soldProperties / $totalProperties) * 100, 1) . '%',
            'description' => "You have {$soldProperties} sold properties out of {$totalProperties} listed properties."
        ];

        // Average time between listing and sale indication
        $insights[] = [
            'type' => 'performance',
            'title' => 'Performance Summary',
            'value' => 'Strong Performance',
            'description' => "Generated ₦" . number_format($totalRevenue, 2) . " from {$totalTransactions} transactions in the selected period."
        ];

        if (!empty($bestMonth)) {
            $insights[] = [
                'type' => 'seasonal',
                'title' => 'Peak Performance Month',
                'value' => $bestMonth['month'] ?? 'N/A',
                'description' => "Your best performing month showed " . ($bestMonth['transactions'] ?? 0) . " transactions."
            ];
        }
    }

    // === Comprehensive Performance Report ===
    $performanceReport = [
        'report_info' => [
            'agent_id' => $agent_id,
            'agency_name' => $agent['agency_name'],
            'generated_at' => date('Y-m-d H:i:s'),
            'date_range' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'report_type' => $report_type,
            'period_days' => ceil((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1
        ],
        'financial_summary' => [
            'total_transactions' => $financial['total_transactions'] ?? 0,
            'total_revenue' => round($financial['total_revenue'] ?? 0, 2),
            'agent_earnings' => round($financial['agent_earnings'] ?? 0, 2),
            'platform_commission' => round($financial['platform_commission'] ?? 0, 2),
            'avg_transaction_value' => round($financial['avg_transaction_value'] ?? 0, 2),
            'highest_transaction' => round($financial['highest_transaction'] ?? 0, 2)
        ],
        'monthly_performance' => array_map(function($month) {
            return [
                'period' => $month['month'],
                'transactions' => $month['transaction_count'],
                'revenue' => round($month['monthly_revenue'], 2),
                'agent_earnings' => round($month['monthly_earnings'], 2),
                'avg_transaction' => round($month['monthly_revenue'] / ($month['transaction_count'] ?: 1), 2)
            ];
        }, $monthlyTrends),
        'property_performance' => array_map(function($property) {
            return [
                'property_id' => $property['property_id'],
                'title' => $property['title'],
                'category' => $property['property_category'],
                'type' => $property['property_type'],
                'location' => $property['city'] . ', ' . $property['state'],
                'status' => $property['property_status'],
                'sales_count' => $property['sales_count'],
                'total_revenue' => round($property['total_sales_revenue'], 2),
                'agent_share' => round($property['agent_share_from_property'], 2),
                'avg_sale_price' => round($property['avg_sale_price'], 2),
                'performance_score' => $property['completed_transactions'] > 0 ? 'Strong Seller' : 'Needs Attention'
            ];
        }, $propertyPerformance),
        'customer_insights' => array_map(function($customer) {
            return [
                'customer_id' => $customer['user_id'],
                'name' => $customer['fname'] . ' ' . $customer['lname'],
                'email' => $customer['email'],
                'location' => $customer['city'] . ', ' . $customer['state'],
                'total_purchases' => $customer['total_purchases'],
                'total_spent' => round($customer['total_spent'], 2),
                'avg_purchase' => round($customer['avg_purchase_value'], 2),
                'last_purchase' => $customer['last_purchase_date'] ?? null,
                'interests' => $customer['property_types_purchased']
            ];
        }, $customerAnalysis),
        'market_analysis' => [
            'categories' => array_map(function($cat) {
                return [
                    'category' => $cat['property_category'],
                    'listed_properties' => $cat['total_properties'],
                    'successful_sales' => $cat['successful_sales'],
                    'total_revenue' => round($cat['total_revenue'], 2),
                    'avg_sale_price' => round($cat['avg_sale_price'], 2)
                ];
            }, $categoryPerformance),
            'geographic_performance' => array_map(function($geo) {
                return [
                    'location' => $geo['city'] . ', ' . $geo['state'],
                    'listed_properties' => $geo['properties_listed'],
                    'successful_sales' => $geo['successful_sales'],
                    'total_revenue' => round($geo['total_revenue'], 2),
                    'avg_sale_price' => round($geo['avg_sale_price'], 2)
                ];
            }, $geographicPerformance)
        ],
        'market_positioning' => $agentBenchmarking,
        'insights_recommendations' => $insights
    ];

    $api_status_code_class_call->respondOK($performanceReport, "Agent performance report generated successfully");

} catch (Exception $e) {
    $api_status_code_class_call->respondInternalError($e->getMessage());
}
