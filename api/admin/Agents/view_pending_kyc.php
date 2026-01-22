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

header("Content-type: application/json");

$api_method = "GET";

if (getenv('REQUEST_METHOD') === $api_method) {
    try {
        //Validate Admin Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);

        //Pagination & Sorting Defaults
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 10;

        $sort_by = $_GET['sort_by'] ?? 'created_at';
        $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');
        $search = trim($_GET['search'] ?? '');

        //Whitelist allowed sort columns
        $allowed_sort_columns = ['agency_name', 'business_reg_no', 'status', 'created_at'];
        if (!in_array($sort_by, $allowed_sort_columns)) {
            $sort_by = 'created_at';
        }

        //Whitelist allowed sort order
        $allowed_sort_order = ['ASC', 'DESC'];
        if (!in_array($sort_order, $allowed_sort_order)) {
            $sort_order = 'DESC';
        }

        //Define main table and joins
        $tableName = "kyc_verifications AS k";
        $joins = [
            [
                'type' => 'INNER',
                'table' => 'agents AS a',
                'condition' => 'a.id = k.agent_id'
            ]
        ];

        //Base WHERE condition
        $where = [
            ['column' => 'k.status', 'operator' => '=', 'value' => 'pending']
        ];

        //Add optional search condition
        if (!empty($search)) {
            $where[] = [
                'column' => '(a.agency_name LIKE ? OR k.business_reg_no LIKE ?)',
                'operator' => '',
                'value' => "%$search%"
            ];
        }

        //Query options for selectRows
        $options = [
            'joins' => $joins,
            'orderBy' => in_array($sort_by, ['agency_name', 'status']) ? "a.$sort_by" : "k.$sort_by",
            'orderDirection' => $sort_order,
            'limit' => $limit,
            'pageno' => $page
        ];

        //Fetch pending KYC records
        $pendingKYC = $db_call_class->selectRows(
            $tableName,
            [
                'k.id',
                'a.agency_name',
                'a.email',
                'k.business_reg_no AS cac_number',
                'k.status',
                'k.created_at'
            ],
            $where,
            $options
        );

        //Fetch total count for pagination
        $countResult = $db_call_class->selectRows(
            $tableName,
            'COUNT(*) AS total',
            [['column' => 'k.status', 'operator' => '=', 'value' => 'pending']],
            ['joins' => $joins]
        );

        $totalPending = (int)($countResult[0]['total'] ?? 0);

        //Success Response
        $api_status_code_class_call->respondOK([
            'pending_kyc' => $pendingKYC,
            'pagination' => [
                'current_page' => $page,
                'total_records' => $totalPending,
                'limit_per_page' => $limit,
                'total_pages' => ceil($totalPending / $limit)
            ],
            'sorting' => [
                'sorted_by' => $sort_by,
                'sort_order' => $sort_order
            ]
        ], API_User_Response::$kyc_record_fetched);
    } catch (Exception $e) {
        //Internal Error
        $api_status_code_class_call->respondInternalError(
            $utility_class_call->get_details_from_exception($e)
        );
    }
} else {
    //Wrong Method
    $api_status_code_class_call->respondMethodNotAlowed();
}
