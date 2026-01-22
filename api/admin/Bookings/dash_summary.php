<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

header("Content-Type: application/json");

$api_status_code_class_call = new API_Status_Code;
$db_call_class              = new DB_Calls_Functions;
$utility_class_call         = new Utility_Functions;

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // 1) Admin validation
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]], ['limit' => 1]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // 2) Core counts
        $totals = $db_call_class->selectRows("bookings", "COUNT(*) AS total_bookings");
        $total_bookings = (int)($totals[0]['total_bookings'] ?? 0);

        $pending = $db_call_class->selectRows(
            "bookings",
            "COUNT(*) AS pending",
            [[['column' => 'status', 'operator' => 'IN', 'value' => ['pending', 'awaiting_admin_approval']]]]
        );
        $pending_approvals = (int)($pending[0]['pending'] ?? 0);

        $statusBreakdown = $db_call_class->selectRows(
            "bookings",
            ["status", "COUNT(*) AS total"],
            [],
            ['groupBy' => 'status']
        );
        $status_counts = [];
        if (!$utility_class_call->input_is_invalid($statusBreakdown)) {
            foreach ($statusBreakdown as $row) {
                $status_counts[$row['status']] = (int)$row['total'];
            }
        }

        $typeBreakdown = $db_call_class->selectRows(
            "bookings",
            ["booking_type", "COUNT(*) AS total"],
            [],
            ['groupBy' => 'booking_type']
        );
        $type_counts = [];
        if (!$utility_class_call->input_is_invalid($typeBreakdown)) {
            foreach ($typeBreakdown as $row) {
                $type_counts[$row['booking_type']] = (int)$row['total'];
            }
        }

        // 3) Payment summaries
        $paymentSummary = $db_call_class->selectRows(
            "bookings",
            [
                "payment_status",
                "COUNT(*) AS total_count",
                "COALESCE(SUM(payment_amount), 0) AS total_amount"
            ],
            [],
            ['groupBy' => 'payment_status']
        );
        $payments = [];
        if (!$utility_class_call->input_is_invalid($paymentSummary)) {
            foreach ($paymentSummary as $row) {
                $payments[$row['payment_status']] = [
                    "count" => (int)$row['total_count'],
                    "amount" => (float)$row['total_amount']
                ];
            }
        }

        // 4) Compose response
        $api_status_code_class_call->respondOK(
            [
                "total_bookings" => $total_bookings,
                "pending_approvals" => $pending_approvals,
                "status_counts" => $status_counts,
                "type_counts" => $type_counts,
                "payment_summary" => $payments
            ],
            "Booking dashboard summary"
        );
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
