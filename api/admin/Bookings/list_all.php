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

        $getAdmin = $db_call_class->selectRows("admins", "id", [[
            ['column' => 'adminpubkey', 'operator' => '=', 'value' => $admin_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getAdmin)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        // 2) Collect filters (JSON or form)
        $data          = Utility_Functions::getRequestData();
        $status        = isset($data['status']) ? Utility_Functions::sanitizeString($data['status']) : '';
        $booking_type  = isset($data['booking_type']) ? Utility_Functions::sanitizeString($data['booking_type']) : '';
        $agent_id      = Utility_Functions::normalizeInt($data['agent_id'] ?? null);
        $property_id   = Utility_Functions::normalizeInt($data['property_id'] ?? null);
        $date_from     = isset($data['date_from']) ? Utility_Functions::sanitizeString($data['date_from']) : '';
        $date_to       = isset($data['date_to']) ? Utility_Functions::sanitizeString($data['date_to']) : '';
        [$page_no, $limit] = Utility_Functions::normalizePagination($data['page'] ?? 1, $data['limit'] ?? 20);
        $sort_by_input = isset($data['sort_by']) ? Utility_Functions::sanitizeString($data['sort_by']) : '';
        $sort_dir      = isset($data['sort_dir']) ? strtoupper(Utility_Functions::sanitizeString($data['sort_dir'])) : 'DESC';
        if (!in_array($sort_dir, ['ASC', 'DESC'])) {
            $sort_dir = 'DESC';
        }

        // Whitelist sortable columns
        $sortable = [
            'created_at'   => 'b.created_at',
            'visit_date'   => 'b.visit_date',
            'status'       => 'b.status',
            'booking_type' => 'b.booking_type',
            'payment_status' => 'b.payment_status'
        ];
        $orderBy = $sortable[$sort_by_input] ?? 'b.created_at';

        // 3) Validate enums and dates
        if (!Utility_Functions::validateEnum($status, Utility_Functions::$bookingStatus)) {
            $api_status_code_class_call->respondBadRequest("Invalid status value");
        }
        if (!Utility_Functions::validateEnum($booking_type, Utility_Functions::$bookingTypes)) {
            $api_status_code_class_call->respondBadRequest("Invalid booking_type value");
        }
        if (!Utility_Functions::validateDateYmd($date_from) || !Utility_Functions::validateDateYmd($date_to)) {
            $api_status_code_class_call->respondBadRequest("Invalid date format, use YYYY-MM-DD");
        }
        if (!Utility_Functions::ensureDateOrder($date_from, $date_to)) {
            $api_status_code_class_call->respondBadRequest("date_to must be >= date_from");
        }

        // 4) Build where conditions
        $where = [];
        if (!empty($status)) {
            $where[] = [['column' => 'b.status', 'operator' => '=', 'value' => $status]];
        }
        if (!empty($booking_type)) {
            $where[] = [['column' => 'b.booking_type', 'operator' => '=', 'value' => $booking_type]];
        }
        if (!empty($agent_id)) {
            $where[] = [['column' => 'b.agent_id', 'operator' => '=', 'value' => $agent_id]];
        }
        if (!empty($property_id)) {
            $where[] = [['column' => 'b.property_id', 'operator' => '=', 'value' => $property_id]];
        }
        if (!empty($date_from) && !empty($date_to)) {
            $where[] = [
                ['column' => 'b.visit_date', 'operator' => '>=', 'value' => $date_from],
                ['column' => 'b.visit_date', 'operator' => '<=', 'value' => $date_to]
            ];
        } elseif (!empty($date_from)) {
            $where[] = [['column' => 'b.visit_date', 'operator' => '>=', 'value' => $date_from]];
        } elseif (!empty($date_to)) {
            $where[] = [['column' => 'b.visit_date', 'operator' => '<=', 'value' => $date_to]];
        }

        // 4) Counts (total and by status)
        $totalRowsRes = $db_call_class->selectRows(
            "bookings b",
            "COUNT(*) as total_rows",
            $where
        );
        $total_rows   = isset($totalRowsRes[0]['total_rows']) ? (int)$totalRowsRes[0]['total_rows'] : 0;

        // Status counts (unfiltered)
        $statusCounts = $db_call_class->selectRows(
            "bookings b",
            ["status", "COUNT(*) as total"],
            [],
            [
                'groupBy' => 'status'
            ]
        );
        $status_summary = [];
        if (!$utility_class_call->input_is_invalid($statusCounts)) {
            foreach ($statusCounts as $row) {
                $status_summary[$row['status']] = (int)$row['total'];
            }
        }

        // 5) Fetch paginated data
        $bookings = $db_call_class->selectRows(
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
                "a.agency_name AS agent_agency",
                "a.email AS agent_email",
                "p.title AS property_title",
                "p.property_category",
                "p.property_type",
                "p.city",
                "p.state"
            ],
            $where,
            [
                'joins' => [
                    ['type' => 'LEFT', 'table' => 'users u', 'condition' => 'b.user_id = u.id'],
                    ['type' => 'LEFT', 'table' => 'agents a', 'condition' => 'b.agent_id = a.id'],
                    ['type' => 'LEFT', 'table' => 'properties p', 'condition' => 'b.property_id = p.id'],
                ],
                'orderBy' => $orderBy,
                'orderDirection' => $sort_dir,
                'limit' => $limit,
                'pageno' => $page_no
            ]
        );

        $api_status_code_class_call->respondOK(
            [
                "pagination" => [
                    "page" => $page_no,
                    "limit" => $limit,
                    "total" => $total_rows,
                    "total_pages" => $limit > 0 ? ceil($total_rows / $limit) : 0
                ],
                "counts" => [
                    "by_status" => $status_summary
                ],
                "data" => $bookings
            ],
            "Bookings fetched successfully"
        );
    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
