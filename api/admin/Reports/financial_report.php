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
    // Admin validation
    $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
    $admin_pubkey = $decodedToken->usertoken;

    $getAdmin = $db_call_class->selectRows("admins", "id", [[
        ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getAdmin)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    // Get report parameters
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');
    $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'summary'; // summary, detailed, comparative

    // Validate date range
    if (strtotime($start_date) > strtotime($end_date)) {
        $api_status_code_class_call->respondBadRequest("Start date cannot be after end date");
    }

    // Financial Overview
    $financialMetrics = $db_call_class->selectRows("
        SELECT
            COUNT(*) AS total_transactions,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS gross_revenue,
            SUM(CASE WHEN status = 'completed' THEN IFNULL(commission, 0) ELSE 0 END) AS total_commission,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS avg_transaction_value,
            MIN(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS min_transaction_value,
            MAX(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS max_transaction_value,
            COUNT(DISTINCT user_id) AS unique_customers,
            COUNT(DISTINCT agent_id) AS unique_agents
        FROM transactions
        WHERE created_at BETWEEN ? AND ?
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Daily Transaction Trends
    $dailyTrends = $db_call_class->selectRows("
        SELECT
            DATE(created_at) AS date,
            COUNT(*) AS transaction_count,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS daily_revenue,
            SUM(CASE WHEN status = 'completed' THEN IFNULL(commission, 0) ELSE 0 END) AS daily_commission
        FROM transactions
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Payment Method Breakdown
    $paymentMethods = $db_call_class->selectRows("
        SELECT
            IFNULL(payment_method, 'Unknown') AS payment_method,
            COUNT(*) AS transaction_count,
            SUM(amount) AS total_amount,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS successful_count
        FROM transactions
        WHERE created_at BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Agent Performance - Top 10 by Revenue
    $topAgents = $db_call_class->selectRows("
        SELECT
            a.id AS agent_id,
            a.agency_name,
            COUNT(t.id) AS transaction_count,
            SUM(t.amount) AS total_revenue,
            SUM(t.agent_amount) AS agent_earnings,
            AVG(t.amount) AS avg_transaction_value
        FROM transactions t
        LEFT JOIN agents a ON t.agent_id = a.id
        WHERE t.created_at BETWEEN ? AND ? AND t.status = 'completed'
        GROUP BY a.id, a.agency_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Property Category Performance
    $propertyPerformance = $db_call_class->selectRows("
        SELECT
            p.property_category,
            COUNT(t.id) AS transactions,
            SUM(t.amount) AS total_value,
            AVG(t.amount) AS avg_transaction_value
        FROM transactions t
        LEFT JOIN properties p ON t.property_id = p.id
        WHERE t.created_at BETWEEN ? AND ? AND t.status = 'completed'
        GROUP BY p.property_category
        ORDER BY total_value DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Geographic Performance
    $geographicData = $db_call_class->selectRows("
        SELECT
            p.city,
            p.state,
            COUNT(t.id) AS transactions,
            SUM(t.amount) AS total_value,
            AVG(t.amount) AS avg_transaction_value
        FROM transactions t
        LEFT JOIN properties p ON t.property_id = p.id
        WHERE t.created_at BETWEEN ? AND ? AND t.status = 'completed' AND p.city IS NOT NULL
        GROUP BY p.city, p.state
        ORDER BY total_value DESC
        LIMIT 20
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Transaction Status Breakdown
    $transactionStatus = $db_call_class->selectRows("
        SELECT
            status,
            COUNT(*) AS count,
            SUM(amount) AS total_amount,
            AVG(amount) AS avg_amount
        FROM transactions
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
        ORDER BY count DESC
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Growth Metrics - Compare with previous period
    $previousStartDate = date('Y-m-d', strtotime($start_date . ' -1 month'));
    $previousEndDate = date('Y-m-d', strtotime($end_date . ' -1 month'));

    $previousPeriodData = $db_call_class->selectRows("
        SELECT
            COUNT(*) AS prev_transactions,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS prev_revenue
        FROM transactions
        WHERE created_at BETWEEN ? AND ?
    ", [$previousStartDate . ' 00:00:00', $previousEndDate . ' 23:59:59']);

    $current = $financialMetrics[0] ?? [];
    $previous = $previousPeriodData[0] ?? [];

    $growthMetrics = [];
    if (!empty($previous)) {
        $currentRevenue = $current['gross_revenue'] ?? 0;
        $prevRevenue = $previous['prev_revenue'] ?? 0;
        $currentTrans = $current['total_transactions'] ?? 0;
        $prevTrans = $previous['prev_transactions'] ?? 0;

        $growthMetrics = [
            'revenue_growth_percentage' => $prevRevenue > 0 ?
                round((($currentRevenue - $prevRevenue) / $prevRevenue) * 100, 2) : 0,
            'transaction_growth_percentage' => $prevTrans > 0 ?
                round((($currentTrans - $prevTrans) / $prevTrans) * 100, 2) : 0,
            'comparison_period' => "{$previousStartDate} to {$previousEndDate}"
        ];
    }

    // Prepare comprehensive report
    $financialReport = [
        'report_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'date_range' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'total_days' => ceil((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1
        ],
        'executive_summary' => [
            'total_transactions' => $current['total_transactions'] ?? 0,
            'gross_revenue' => round($current['gross_revenue'] ?? 0, 2),
            'total_commission' => round($current['total_commission'] ?? 0, 2),
            'net_revenue' => round(($current['gross_revenue'] ?? 0) - ($current['total_commission'] ?? 0), 2),
            'unique_customers' => $current['unique_customers'] ?? 0,
            'unique_agents' => $current['unique_agents'] ?? 0,
            'avg_transaction_value' => round($current['avg_transaction_value'] ?? 0, 2),
            'transaction_range' => [
                'min' => round($current['min_transaction_value'] ?? 0, 2),
                'max' => round($current['max_transaction_value'] ?? 0, 2)
            ]
        ],
        'timeline_analysis' => [
            'daily_trends' => array_map(function($day) {
                return [
                    'date' => $day['date'],
                    'transactions' => $day['transaction_count'],
                    'revenue' => round($day['daily_revenue'], 2),
                    'commission' => round($day['daily_commission'], 2)
                ];
            }, $dailyTrends),
            'peak_day' => $this->findPeakDay($dailyTrends),
            'period_summary' => [
                'total_days_with_activity' => count($dailyTrends),
                'avg_daily_transactions' => averageArray(array_column($dailyTrends, 'transaction_count'), 1),
                'avg_daily_revenue' => round($current['total_transactions'] ?? 0 > 0 ?
                    ($current['gross_revenue'] ?? 0) / count($dailyTrends) : 0, 2)
            ]
        ],
        'geographic_performance' => array_map(function($geo) {
            return [
                'location' => "{$geo['city']}, {$geo['state']}",
                'transactions' => $geo['transactions'],
                'total_value' => round($geo['total_value'], 2),
                'avg_transaction' => round($geo['avg_transaction_value'], 2)
            ];
        }, $geographicData),
        'top_performing_agents' => array_map(function($agent) {
            return [
                'agent_id' => $agent['agent_id'],
                'agency_name' => $agent['agency_name'],
                'transaction_count' => $agent['transaction_count'],
                'total_revenue' => round($agent['total_revenue'], 2),
                'agent_earnings' => round($agent['agent_earnings'], 2),
                'avg_transaction' => round($agent['avg_transaction_value'], 2)
            ];
        }, $topAgents),
        'market_analysis' => [
            'property_categories' => array_map(function($cat) {
                return [
                    'category' => $cat['property_category'] ?: 'Uncategorized',
                    'transactions' => $cat['transactions'],
                    'total_value' => round($cat['total_value'], 2),
                    'avg_transaction' => round($cat['avg_transaction_value'], 2)
                ];
            }, $propertyPerformance),
            'payment_methods' => array_map(function($pm) {
                return [
                    'method' => $pm['payment_method'],
                    'transaction_count' => $pm['transaction_count'],
                    'total_amount' => round($pm['total_amount'], 2),
                    'success_rate' => round($pm['transaction_count'] > 0 ?
                        ($pm['successful_count'] / $pm['transaction_count']) * 100 : 0, 1)
                ];
            }, $paymentMethods),
            'transaction_statuses' => array_map(function($status) {
                return [
                    'status' => $status['status'],
                    'count' => $status['count'],
                    'total_amount' => round($status['total_amount'], 2),
                    'avg_amount' => round($status['avg_amount'], 2)
                ];
            }, $transactionStatus)
        ],
        'period_comparison' => $growthMetrics
    ];

    $api_status_code_class_call->respondOK($financialReport, "Financial report generated successfully");

} catch (Exception $e) {
    $api_status_code_class_call->respondInternalError($e->getMessage());
}

// Helper function to find peak day
function findPeakDay($dailyTrends) {
    if (empty($dailyTrends)) return null;
    return array_reduce($dailyTrends, function($carry, $item) {
        return $item['daily_revenue'] > ($carry['revenue'] ?? 0) ? $item : $carry;
    });
}

function averageArray($array, $decimals = 2) {
    if (empty($array)) return 0;
    return round(array_sum($array) / count($array), $decimals);
}
