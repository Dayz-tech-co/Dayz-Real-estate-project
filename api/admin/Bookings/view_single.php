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

$api_method = "POST";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        // 1) Validate admin token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);
        $admin_pubkey = $decodedToken->usertoken;

        // Ensure admin exists
        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]], ['limit' => 1]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // 2) Read input (JSON or form)
        $data = Utility_Functions::getRequestData();
        $booking_id = Utility_Functions::normalizeInt($data['booking_id'] ?? null);
        if (empty($booking_id)) {
            $api_status_code_class_call->respondBadRequest("booking_id is required and must be numeric");
        }

        // 3) Fetch booking with joins
        $bookingRows = $db_call_class->selectRows(
            "bookings b",
            [
                "b.id",
                "b.booking_type",
                "b.user_id",
                "b.property_id",
                "b.agent_id",
                "b.visit_date",
                "b.scheduled_time",
                "b.visit_status",
                "b.end_date",
                "b.approved_by",
                "b.approved_at",
                "b.admin_comment",
                "b.requested_date",
                "b.message",
                "b.status",
                "b.payment_status",
                "b.payment_reference",
                "b.payment_amount",
                "b.created_at",
                "b.updated_at",
                "u.fname AS user_fname",
                "u.lname AS user_lname",
                "u.email AS user_email",
                "u.phoneno AS user_phone",
                "a.agency_name AS agent_agency",
                "a.email AS agent_email",
                "a.phoneno AS agent_phone",
                "a.status AS agent_status",
                "p.title AS property_title",
                "p.property_category",
                "p.property_type",
                "p.price",
                "p.city",
                "p.state",
                "p.location",
                "p.slug AS property_slug"
            ],
            [[
                ['column' => 'b.id', 'operator' => '=', 'value' => (int)$booking_id]
            ]],
            [
                'limit' => 1,
                'joins' => [
                    ['type' => 'LEFT', 'table' => 'users u', 'condition' => 'b.user_id = u.id'],
                    ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 'b.agent_id = a.id'],
                    ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 'b.property_id = p.id'],
                ]
            ]
        );

        if ($utility_class_call->input_is_invalid($bookingRows)) {
            $api_status_code_class_call->respondNotFound("Booking not found");
        }

        $b = $bookingRows[0];

        // 4) Optional admin action history (best effort)
        $history = [];
        try {
            $history = $db_call_class->selectRows(
                "booking_status_history h",
                [
                    "h.id",
                    "h.booking_id",
                    "h.old_status",
                    "h.new_status",
                    "h.note",
                    "h.reason",
                    "h.changed_by",
                    "h.created_at",
                    "adm.id AS admin_id",
                    "adm.fname AS admin_fname",
                    "adm.lname AS admin_lname",
                    "adm.email AS admin_email"
                ],
                [[
                    ['column' => 'h.booking_id', 'operator' => '=', 'value' => (int)$booking_id]
                ]],
                [
                    'joins' => [
                        ['type' => 'LEFT', 'table' => 'admins adm', 'condition' => 'h.changed_by = adm.adminpubkey']
                    ],
                    'orderBy' => 'h.created_at',
                    'orderDirection' => 'DESC'
                ]
            );
        } catch (\Exception $e) {
            $history = [];
        }

        // 5) Shape response
        $payload = [
            "booking" => [
                "id" => $b['id'],
                "booking_type" => $b['booking_type'],
                "status" => $b['status'],
                "visit_status" => $b['visit_status'],
                "visit_date" => $b['visit_date'],
                "scheduled_time" => $b['scheduled_time'],
                "end_date" => $b['end_date'],
                "requested_date" => $b['requested_date'],
                "message" => $b['message'],
                "admin_comment" => $b['admin_comment'],
                "approved_by" => $b['approved_by'],
                "approved_at" => $b['approved_at'],
                "created_at" => $b['created_at'],
                "updated_at" => $b['updated_at'],
            ],
            "user" => [
                "id" => $b['user_id'],
                "fname" => $b['user_fname'],
                "lname" => $b['user_lname'],
                "email" => $b['user_email'],
                "phoneno" => $b['user_phone'],
                "full_name" => trim(($b['user_fname'] ?? '') . " " . ($b['user_lname'] ?? ''))
            ],
            "agent" => [
                "id" => $b['agent_id'],
                "agency_name" => $b['agent_agency'],
                "email" => $b['agent_email'],
                "phoneno" => $b['agent_phone'],
                "status" => $b['agent_status']
            ],
            "property" => [
                "id" => $b['property_id'],
                "title" => $b['property_title'],
                "category" => $b['property_category'],
                "type" => $b['property_type'],
                "price" => $b['price'],
                "city" => $b['city'],
                "state" => $b['state'],
                "location" => $b['location'],
                "slug" => $b['property_slug']
            ],
            "payment" => [
                "status" => $b['payment_status'],
                "reference" => $b['payment_reference'],
                "amount" => $b['payment_amount']
            ],
            "admin_history" => $history
        ];

        $api_status_code_class_call->respondOK($payload, "Booking details retrieved successfully");
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

