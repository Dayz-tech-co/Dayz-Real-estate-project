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
        // Validate User Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 3);
        $user_pubkey = $decodedToken->usertoken;

        // Fetch User
        $getUser = $db_call_class->selectRows("users", "id, fname, lname, email, phoneno, city, state, kyc_verified", [[
            ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getUser)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $user = $getUser[0];
        $user_id = $user['id'];

        // === Wishlist Summary ===
        $wishlistCount = $db_call_class->selectRows(
            "wishlist",
            "COUNT(*) AS count",
            [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]]
        );
        $wishlist_count = (int)($wishlistCount[0]['count'] ?? 0);

        // Recent Wishlist Items (top 5)
        $recentWishlist = $db_call_class->selectRows(
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

        // === Booking/Visit Requests ===
        $bookingCount = $db_call_class->selectRows(
            "bookings",
            "COUNT(*) AS count",
            [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]]
        );
        $booking_count = (int)($bookingCount[0]['count'] ?? 0);

        // Recent Bookings (top 5)
        $recentBookings = $db_call_class->selectRows(
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

        // === Payment/Transaction History ===
        $transactionCount = $db_call_class->selectRows(
            "transactions",
            "COUNT(*) AS count",
            [[['column' => 'user_id', 'operator' => '=', 'value' => $user_id]]]
        );
        $transaction_count = (int)($transactionCount[0]['count'] ?? 0);

        // Recent Transactions (top 5)
        $recentTransactions = $db_call_class->selectRows(
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

        // === Property Views/Search History ===
        $totalPropertyViews = $db_call_class->selectRows(
            "apicalllog a",
            "COUNT(*) AS count",
            [[
                ['column' => 'a.user_id', 'operator' => '=', 'value' => $user_pubkey],
                ['column' => 'a.apimethod', 'operator' => '=', 'value' => 'GET']
            ]]
        );
        $property_views = (int)($totalPropertyViews[0]['count'] ?? 0);

        // === Notifications ===
        $unreadNotifications = $db_call_class->selectRows(
            "notifications",
            "COUNT(*) AS count",
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
        // Properties in user's city/state or recent category
        $recommended = [];

        if (!empty($user['city']) || !empty($user['state'])) {
            $recommended = $db_call_class->selectRows(
                "properties",
                [
                    "id",
                    "title",
                    "price",
                    "city",
                    "state",
                    "property_type",
                    "property_category",
                    "thumbnail"
                ],
                [[
                    ['column' => 'status', 'operator' => '=', 'value' => 'approved'],
                    ['column' => 'sold_status', 'operator' => '=', 'value' => 'available'],
                    ['column' => 'city', 'operator' => '=', 'value' => $user['city']],
                    // OR condition for state to be added
                ]],
                [
                    'limit' => 6,
                    'pageno' => 1,
                    'orderBy' => 'featured DESC, created_at DESC'
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

        $api_status_code_class_call->respondOK($dashboard, "User dashboard data retrieved successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
