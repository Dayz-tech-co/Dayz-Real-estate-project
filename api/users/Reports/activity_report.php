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
    // User validation
    $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 3);
    $user_pubkey = $decodedToken->usertoken;

    $getUser = $db_call_class->selectRows("users", "id, fname, lname, email, city, state, created_at", [[
        ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
    ]]);

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_code_class_call->respondUnauthorized();
    }

    $user = $getUser[0];
    $user_id = $user['id'];

    // Report parameters
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'personal';

    // Validate date range
    if (strtotime($start_date) > strtotime($end_date)) {
        $api_status_code_class_call->respondBadRequest("Start date cannot be after end date");
    }

    // === Activity Summary ===
    $activitySummary = [
        'account_creation_date' => $user['created_at'],
        'report_period' => ceil((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1 . ' days',
        'user_profile' => [
            'name' => $user['fname'] . ' ' . $user['lname'],
            'email' => $user['email'],
            'location' => ($user['city'] && $user['state']) ? $user['city'] . ', ' . $user['state'] : 'Not specified'
        ]
    ];

    // === Property Interactions ===
    // Property searches/views (from API call logs)
    $propertySearches = $db_call_class->selectRows("
        SELECT COUNT(*) AS total_searches
        FROM apicalllog
        WHERE user_id = ? AND apimethod = 'GET' AND apilink LIKE '%search%' AND created_at BETWEEN ? AND ?
    ", [$user_pubkey, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    $propertyViews = $db_call_class->selectRows("
        SELECT COUNT(*) AS total_views
        FROM apicalllog
        WHERE user_id = ? AND apimethod = 'GET' AND apilink LIKE '%property%' AND apilink NOT LIKE '%search%' AND created_at BETWEEN ? AND ?
    ", [$user_pubkey, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Transaction History ===
    $transactionActivity = $db_call_class->selectRows("
        SELECT
            COUNT(*) AS total_transactions,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS total_spent,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS avg_transaction_value,
            MAX(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS highest_transaction,
            MIN(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS lowest_transaction,
            COUNT(DISTINCT agent_id) AS unique_agents_dealt_with,
            GROUP_CONCAT(DISTINCT status) AS transaction_statuses
        FROM transactions
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Detailed Transaction History ===
    $transactionHistory = $db_call_class->selectRows("
        SELECT
            t.id,
            t.transaction_id,
            t.amount,
            t.transaction_type,
            t.payment_method,
            t.status,
            t.created_at,
            p.id AS property_id,
            p.title AS property_title,
            p.price AS property_price,
            p.city,
            p.state,
            a.agency_name
        FROM transactions t
        LEFT JOIN properties p ON t.property_id = p.id
        LEFT JOIN agents a ON t.agent_id = a.id
        WHERE t.user_id = ? AND t.created_at BETWEEN ? AND ?
        ORDER BY t.created_at DESC
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Wishlist Activity ===
    $wishlistActivity = $db_call_class->selectRows("
        SELECT
            COUNT(w.id) AS current_wishlist_count,
            COUNT(CASE WHEN w.created_at BETWEEN ? AND ? THEN 1 END) AS items_added_this_period
        FROM wishlist w
        WHERE w.user_id = ?
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $user_id]);

    // Recent wishlist additions
    $recentWishlist = $db_call_class->selectRows("
        SELECT
            w.created_at AS added_date,
            p.id AS property_id,
            p.title,
            p.price,
            p.city,
            p.state,
            p.property_type,
            p.property_category
        FROM wishlist w
        LEFT JOIN properties p ON w.property_id = p.id
        WHERE w.user_id = ? AND w.created_at BETWEEN ? AND ?
        ORDER BY w.created_at DESC
        LIMIT 10
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Booking/Activity History ===
    $bookingActivity = $db_call_class->selectRows("
        SELECT
            COUNT(*) AS total_bookings_request,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_bookings,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_visits,
            COUNT(DISTINCT agent_id) AS unique_agents_contacted
        FROM bookings
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // Recent bookings
    $recentBookings = $db_call_class->selectRows("
        SELECT
            b.id AS booking_id,
            b.visit_date,
            b.end_date,
            b.status,
            b.created_at,
            p.id AS property_id,
            p.title AS property_title,
            p.price,
            p.city AS property_city,
            p.state AS property_state,
            a.agency_name
        FROM bookings b
        LEFT JOIN properties p ON b.property_id = p.id
        LEFT JOIN agents a ON p.agent_id = a.id
        WHERE b.user_id = ? AND b.created_at BETWEEN ? AND ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Notification Activity ===
    $notificationActivity = $db_call_class->selectRows("
        SELECT
            COUNT(*) AS total_notifications,
            COUNT(CASE WHEN is_read = 0 THEN 1 END) AS unread_notifications,
            GROUP_CONCAT(DISTINCT type) AS notification_types
        FROM notifications
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Spending Analysis & Trends ===
    $monthlySpending = $db_call_class->selectRows("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS transactions_count,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS monthly_spent,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) AS avg_monthly_transaction
        FROM transactions
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Property Interest Analysis ===
    $propertyInterests = $db_call_class->selectRows("
        SELECT
            p.city,
            p.state,
            COUNT(DISTINCT p.id) AS properties_viewed,
            COUNT(DISTINCT w.property_id) AS wishlist_additions,
            COUNT(DISTINCT t.property_id) AS properties_purchased
        FROM apicalllog a
        LEFT JOIN properties p ON a.apilink LIKE CONCAT('%property/', p.id)
        LEFT JOIN wishlist w ON w.user_id = a.user_id AND w.property_id = p.id AND w.created_at BETWEEN ? AND ?
        LEFT JOIN transactions t ON t.user_id = a.user_id AND t.property_id = p.id AND t.created_at BETWEEN ? AND ?
        WHERE a.user_id = ? AND a.apimethod = 'GET' AND a.created_at BETWEEN ? AND ?
        GROUP BY p.city, p.state
        HAVING properties_viewed > 0
        ORDER BY properties_viewed DESC
        LIMIT 10
    ", [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59', $user_pubkey, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

    // === Behavioral Insights ===
    $insights = [];

    $totalTransactions = $transactionHistory ? count($transactionHistory) : 0;
    $totalSpent = 0;
    $completedTransactions = 0;

    foreach ($transactionHistory as $transaction) {
        if ($transaction['status'] === 'completed') {
            $totalSpent += $transaction['amount'];
            $completedTransactions++;
        }
    }

    $insights[] = [
        'type' => 'activity_level',
        'title' => 'Activity Level',
        'value' => $totalTransactions > 5 ? 'Highly Active' : ($totalTransactions > 0 ? 'Moderately Active' : 'Low Activity'),
        'description' => "You've made {$totalTransactions} transactions in the selected period."
    ];

    if ($totalSpent > 0) {
        $insights[] = [
            'type' => 'spending',
            'title' => 'Spending Pattern',
            'value' => '₦' . number_format($totalSpent, 2),
            'description' => "Your total spending on property transactions during this period."
        ];

        // Preferred property types
        $propertyTypes = [];
        foreach ($transactionHistory as $transaction) {
            if (isset($transaction['property_title'])) {
                // This would need better logic to extract property types
            }
        }
    }

    // === Comprehensive Activity Report ===
    $activityReport = [
        'report_info' => [
            'user_id' => $user_id,
            'generated_at' => date('Y-m-d H:i:s'),
            'date_range' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'report_type' => $report_type,
            'period_days' => ceil((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1
        ],
        'user_profile' => $activitySummary,
        'activity_overview' => [
            'property_exploration' => [
                'searches_performed' => $propertySearches[0]['total_searches'] ?? 0,
                'properties_viewed' => $propertyViews[0]['total_views'] ?? 0,
                'wishlist_size' => $wishlistActivity[0]['current_wishlist_count'] ?? 0,
                'items_added_to_wishlist' => $wishlistActivity[0]['items_added_this_period'] ?? 0
            ],
            'transactions' => [
                'total_count' => $transactionActivity[0]['total_transactions'] ?? 0,
                'completed_count' => count(array_filter($transactionHistory, fn($t) => $t['status'] === 'completed')),
                'total_spent' => round($transactionActivity[0]['total_spent'] ?? 0, 2),
                'avg_transaction_value' => round($transactionActivity[0]['avg_transaction_value'] ?? 0, 2),
                'highest_transaction' => round($transactionActivity[0]['highest_transaction'] ?? 0, 2),
                'lowest_transaction' => round($transactionActivity[0]['lowest_transaction'] ?? 0, 2),
                'unique_agents_dealt_with' => $transactionActivity[0]['unique_agents_dealt_with'] ?? 0
            ],
            'bookings' => [
                'total_requests' => $bookingActivity[0]['total_bookings_request'] ?? 0,
                'confirmed_visits' => $bookingActivity[0]['confirmed_bookings'] ?? 0,
                'completed_visits' => $bookingActivity[0]['completed_visits'] ?? 0,
                'unique_agents_contacted' => $bookingActivity[0]['unique_agents_contacted'] ?? 0
            ],
            'communications' => [
                'total_notifications' => $notificationActivity[0]['total_notifications'] ?? 0,
                'unread_notifications' => $notificationActivity[0]['unread_notifications'] ?? 0,
                'notification_types' => $notificationActivity[0]['notification_types'] ?? null
            ]
        ],
        'detailed_history' => [
            'transactions' => array_map(function($transaction) {
                return [
                    'transaction_id' => $transaction['transaction_id'],
                    'amount' => round($transaction['amount'], 2),
                    'type' => $transaction['transaction_type'],
                    'payment_method' => $transaction['payment_method'],
                    'status' => $transaction['status'],
                    'date' => $transaction['created_at'],
                    'property' => [
                        'id' => $transaction['property_id'],
                        'title' => $transaction['property_title'],
                        'price' => round($transaction['property_price'], 2),
                        'location' => $transaction['city'] . ', ' . $transaction['state']
                    ],
                    'agent' => $transaction['agency_name']
                ];
            }, $transactionHistory),
            'recent_bookings' => array_map(function($booking) {
                return [
                    'booking_id' => $booking['booking_id'],
                    'visit_date' => $booking['visit_date'],
                    'end_date' => $booking['end_date'],
                    'status' => $booking['status'],
                    'requested_date' => $booking['created_at'],
                    'property' => [
                        'id' => $booking['property_id'],
                        'title' => $booking['property_title'],
                        'price' => round($booking['price'], 2),
                        'location' => $booking['property_city'] . ', ' . $booking['property_state']
                    ],
                    'agent' => $booking['agency_name']
                ];
            }, $recentBookings),
            'wishlist_additions' => array_map(function($item) {
                return [
                    'added_date' => $item['added_date'],
                    'property' => [
                        'id' => $item['property_id'],
                        'title' => $item['title'],
                        'price' => round($item['price'], 2),
                        'location' => $item['city'] . ', ' . $item['state'],
                        'type' => $item['property_type'],
                        'category' => $item['property_category']
                    ]
                ];
            }, $recentWishlist)
        ],
        'trends_analysis' => [
            'monthly_spending' => array_map(function($month) {
                return [
                    'period' => $month['month'],
                    'transactions' => $month['transactions_count'],
                    'amount_spent' => round($month['monthly_spent'], 2),
                    'avg_transaction' => round($month['avg_monthly_transaction'], 2)
                ];
            }, $monthlySpending),
            'property_interests' => array_map(function($interest) {
                return [
                    'location' => $interest['city'] . ', ' . $interest['state'],
                    'properties_viewed' => $interest['properties_viewed'],
                    'wishlist_additions' => $interest['wishlist_additions'],
                    'purchases' => $interest['properties_purchased']
                ];
            }, $propertyInterests)
        ],
        'behavioral_insights' => $insights
    ];

    $api_status_code_class_call->respondOK($activityReport, "User activity report generated successfully");

} catch (Exception $e) {
    $api_status_code_class_call->respondInternalError($e->getMessage());
}
