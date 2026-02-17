<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status = new API_Status_Code;
$db = new DB_Calls_Functions;
$util = new Utility_Functions;

header("Content-Type: application/json; charset=UTF-8;");
$api_method = "POST";

if ($_SERVER['REQUEST_METHOD'] !== $api_method) {
    $api_status->respondMethodNotAlowed();
    exit;
}

try {
    // === 1. Validate Agent ===
    $decoded = $api_status->ValidateAPITokenSentIN(1, 2);
    $agent_pubkey = $decoded->usertoken;

    $agentRow = $db->selectRows(
        "agents",
        "id, agency_name, email, kyc_verified",
        [[['column' => 'agentpubkey', 'operator' => '=', 'value' => $agent_pubkey]]]
    );

    if ($util->input_is_invalid($agentRow)) {
        $api_status->respondUnauthorized();
    }

    $agent = $agentRow[0];
    $agent_id = $agent['id'];

    if (strtolower($agent['kyc_verified']) !== 'verified') {
        $api_status->respondBadRequest("KYC not verified");
    }

    // === 2. Properties Summary ===
    $properties = $db->selectRows(
        "properties",
        "id, status, sold_status, featured",
        [[['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id]]]
    );

    $propertyStats = [
        'total' => count($properties),
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
        'flagged' => 0,
        'featured' => 0,
        'sold' => 0,
        'rented' => 0,
        'available' => 0
    ];

    foreach ($properties as $p) {
        $status = strtolower($p['status']);
        $sold = strtolower($p['sold_status'] ?? 'available');

        if (isset($propertyStats[$status])) $propertyStats[$status]++;
        if ($p['featured'] == 1) $propertyStats['featured']++;

        if ($sold === 'sold') $propertyStats['sold']++;
        elseif ($sold === 'rented') $propertyStats['rented']++;
        else $propertyStats['available']++;
    }

    // === 3. Financial Performance ===
    $completedTxns = $db->selectRows(
        "transactions",
        [
            "transaction_type",
            "SUM(amount) AS total_amount",
            "SUM(agent_amount) AS agent_earning"
        ],
        [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
            ['column' => 'status', 'operator' => '=', 'value' => 'completed']
        ]],
        [
            'groupBy' => 'transaction_type'
        ]
    );

    $total_sales = 0;
    $total_rentals = 0;
    $total_agent_earnings = 0;

    foreach ($completedTxns as $t) {
        if ($t['transaction_type'] === 'buy') {
            $total_sales = (float)$t['total_amount'];
        } elseif ($t['transaction_type'] === 'rent') {
            $total_rentals = (float)$t['total_amount'];
        }
        $total_agent_earnings += (float)$t['agent_earning'];
    }

    // === 4. Recent Bookings ===
    $recentBookings = $db->selectRows(
        "bookings b",
        [
            "b.id",
            "b.visit_date",
            "b.end_date",
            "b.status",
            "b.created_at",
            "p.title AS property_title",
            "u.fullname",
            "u.email",
            "u.phoneno"
        ],
        [],
        [
            'joins' => [
                ['type' => 'INNER', 'table' => 'properties p', 'condition' => 'b.property_id = p.id'],
                ['type' => 'INNER', 'table' => 'users u', 'condition' => 'b.user_id = u.id']
            ],
            'whereRaw' => "p.agent_id = {$agent_id}",
            'orderBy' => 'b.created_at',
            'orderDirection' => 'DESC',
            'limit' => 10,
            'pageno' => 1
        ]
    );

    // === 5. Notifications Count ===
    $notif = $db->selectRows(
        "notifications",
        ["COUNT(*) AS unread"],
        [[
            ['column' => 'agent_id', 'operator' => '=', 'value' => $agent_id],
            ['column' => 'is_read', 'operator' => '=', 'value' => 0]
        ]]
    );

    $unread_notifications = (int)($notif[0]['unread'] ?? 0);

    // === 6. Dashboard Payload ===
    $payload = [
        "agent_info" => [
            "id" => $agent_id,
            "agency_name" => $agent['agency_name'],
            "email" => $agent['email'],
            "kyc_verified" => $agent['kyc_verified']
        ],
        "summary" => [
            "properties" => $propertyStats,
            "financials" => [
                "total_sales" => $total_sales,
                "total_rentals" => $total_rentals,
                "total_agent_earnings" => $total_agent_earnings
            ],
            "unread_notifications" => $unread_notifications
        ],
        "recent_bookings" => $recentBookings
    ];

    $api_status->respondOK($payload, "Agent dashboard data retrieved successfully.");

} catch (Exception $e) {
    $api_status->respondInternalError($util->get_details_from_exception($e));
}
