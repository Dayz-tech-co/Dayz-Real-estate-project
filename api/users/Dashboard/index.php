<?php
require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_call = new API_Status_Code;
$db_call = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

$api_method = "POST";
header("Content-Type: application/json");

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== $api_method) {
    $api_status_call->respondMethodNotAlowed();
    exit;
}

try {
    // === Validate User Token ===
    $decodedToken = $api_status_call->ValidateAPITokenSentIN(1, 3);
    $user_pubkey = $decodedToken->usertoken;

    // === Fetch User Info ===
    $getUser = $db_call->selectRows(
        "users",
        "id, fname, lname, email, phoneno, city, state, kyc_verified",
        [[['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]]]
    );

    if ($utility_class_call->input_is_invalid($getUser)) {
        $api_status_call->respondUnauthorized();
    }

    $user = $getUser[0];
    $user_id = $user['id'];

    // === Wishlist Summary ===
    $wishlistCount = $db_call->selectRows(
        "wishlist",
        ["COUNT(*) AS count"],
        [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]]
    );
    $wishlist_count = (int)($wishlistCount[0]['count'] ?? 0);

    $recentWishlist = $db_call->selectRows(
        "wishlist w",
        [
            "w.created_at",
            "p.id AS property_id",
            "p.title",
            "p.price",
            "p.city",
            "p.state",
            "p.property_type",
            "p.thumbnail"
        ],
        [[['column' => 'w.user_id', 'operator' => '=', 'value' => $user_id]]],
        [
            'limit' => 5,
            'pageno' => 1,
            'joins' => [
                ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 'w.property_id = p.id']
            ],
            'orderBy' => 'w.created_at',
            'orderDirection' => 'DESC'
        ]
    );

    // === Booking Summary ===
    $bookingCount = $db_call->selectRows(
        "bookings",
        ["COUNT(*) AS count"],
        [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]]
    );
    $booking_count = (int)($bookingCount[0]['count'] ?? 0);

    $recentBookings = $db_call->selectRows(
        "bookings b",
        [
            "b.id AS booking_id",
            "b.visit_date",
            "b.end_date",
            "b.status",
            "b.created_at AS booking_created",
            "p.id AS property_id",
            "p.title",
            "p.price",
            "p.city",
            "p.state",
            "p.property_type",
            "a.agency_name"
        ],
        [[['column' => 'b.user_id', 'operator' => '=', 'value' => $user_id]]],
        [
            'limit' => 5,
            'pageno' => 1,
            'joins' => [
                ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 'b.property_id = p.id'],
                ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 'p.agent_id = a.id']
            ],
            'orderBy' => 'b.created_at',
            'orderDirection' => 'DESC'
        ]
    );

    // === Transactions Summary ===
    $transactionCount = $db_call->selectRows(
        "transactions",
        ["COUNT(*) AS count"],
        [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]]
    );
    $transaction_count = (int)($transactionCount[0]['count'] ?? 0);

    $recentTransactions = $db_call->selectRows(
        "transactions t",
        [
            "t.id",
            "t.transaction_id",
            "t.amount",
            "t.transaction_type",
            "t.payment_method",
            "t.status",
            "t.created_at",
            "p.id AS property_id",
            "p.title",
            "p.price",
            "a.agency_name"
        ],
        [[['column' => 't.user_id', 'operator' => '=', 'value' => $user_id]]],
        [
            'limit' => 5,
            'pageno' => 1,
            'joins' => [
                ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 't.property_id = p.id'],
                ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 'p.agent_id = a.id']
            ],
            'orderBy' => 't.created_at',
            'orderDirection' => 'DESC'
        ]
    );

    // === Property Views ===
    $totalPropertyViews = $db_call->selectRows(
        "apicalllog a",
        ["COUNT(*) AS count"],
        [[
            ['column' => 'a.user_id', 'operator' => '=', 'value' => $user_pubkey],
            ['column' => 'a.apimethod', 'operator' => '=', 'value' => 'GET']
        ]]
    );
    $property_views = (int)($totalPropertyViews[0]['count'] ?? 0);

    // === Notifications ===
    $unreadNotifications = $db_call->selectRows(
        "notifications",
        ["COUNT(*) AS count"],
        [[
            ['column' => 'user_id', 'operator' => '=', 'value' => $user_id],
            ['column' => 'is_read', 'operator' => '=', 'value' => 0]
        ]]
    );
    $unread_count = (int)($unreadNotifications[0]['count'] ?? 0);

    // === KYC Status ===
    $kycStatus = [
        'verified' => $user['kyc_verified'] === 'verified' ? 1 : 0,
        'status' => $user['kyc_verified'],
        'doc_required' => $user['kyc_verified'] === 'verified' ? 'None' : 'ID documents needed'
    ];

    // === Personalized Recommendations ===
    $recommended = [];
    if (!empty($user['city']) || !empty($user['state'])) {
        $whereConditions = [
            ['column' => 'status', 'operator' => '=', 'value' => 'approved'],
            ['column' => 'sold_status', 'operator' => '=', 'value' => 'available']
        ];

        if (!empty($user['city'])) {
            $whereConditions[] = ['column' => 'city', 'operator' => '=', 'value' => $user['city']];
        }

        if (!empty($user['state'])) {
            $whereConditions[] = ['column' => 'state', 'operator' => '=', 'value' => $user['state']];
        }

        $recommended = $db_call->selectRows(
            "properties",
            ["id", "title", "price", "city", "state", "property_type", "property_category", "thumbnail"],
            [$whereConditions],
            [
                'limit' => 6,
                'pageno' => 1,
                'orderBy' => 'created_at',  // single column
                'orderDirection' => 'DESC'
            ]
        );
    }

    // === Dashboard Response ===
    $dashboard = [
        "user_info" => [
            "id" => $user_id,
            "fullname" => $user['fname'] . ' ' . $user['lname'],
            "email" => $user['email'],
            "phone" => $user['phoneno'],
            "city" => $user['city'],
            "state" => $user['state']
        ],
        "kyc_status" => $kycStatus,
        "stats" => [
            "wishlist_count" => $wishlist_count,
            "booking_count" => $booking_count,
            "transaction_count" => $transaction_count,
            "property_views" => $property_views,
            "unread_notifications" => $unread_count
        ],
        "recent_activity" => [
            "bookings" => $recentBookings,
            "transactions" => $recentTransactions,
            "wishlist" => $recentWishlist
        ],
        "recommendations" => [
            "properties" => $recommended,
            "based_on" => !empty($user['city']) ? "Your location ({$user['city']}, {$user['state']})" : "Popular listings"
        ]
    ];

    $api_status_call->respondOK($dashboard, "User dashboard data retrieved successfully.");

} catch (Exception $e) {
    $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
}
