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
        // Validate Admin
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);

        $agent_id = $utility_class_call->clean_user_data($_GET['agent_id'] ?? '', 1);
        $sort_by = $_GET['sort_by'] ?? 'created_at';
        $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');
        $search = trim($_GET['search'] ?? '');

        if (empty($agent_id)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Allowed sorting
        $allowed_sort_columns = ['status', 'business_reg_no', 'created_at'];
        if (!in_array($sort_by, $allowed_sort_columns)) {
            $sort_by = 'created_at';
        }

        $allowed_sort_order = ['ASC', 'DESC'];
        if (!in_array($sort_order, $allowed_sort_order)) {
            $sort_order = 'DESC';
        }

        // Build where clause
        $where = [
            ['column' => 'k.agent_id', 'operator' => '=', 'value' => $agent_id]
        ];

        if (!empty($search)) {
            $where[] = [
                'column' => '(k.business_reg_no LIKE ? OR k.status LIKE ?)',
                'operator' => '',
                'value' => "%$search%"
            ];
        }

        // Define join
        $joins = [
            [
                'type' => 'INNER',
                'table' => 'agents AS a',
                'condition' => 'a.id = k.agent_id'
            ]
        ];

        // Query options
        $options = [
            'joins' => $joins,
            'orderBy' => "k.$sort_by",
            'orderDirection' => $sort_order
        ];

        // Fetch KYC data
        $kycDetails = $db_call_class->selectRows(
            'kyc_verifications AS k',
            'k.id, k.business_reg_no AS cac_number, k.status, k.created_at, a.agency_name, a.email',
            $where,
            $options
        );

        if (empty($kycDetails)) {
            $api_status_code_class_call->respondNotFound(API_User_Response::$agentkycnotfound);
        }

        $api_status_code_class_call->respondOK([
            "kyc_records" => $kycDetails,
            "sorting" => [
                "sorted_by" => $sort_by,
                "sort_order" => $sort_order
            ]
        ], API_User_Response::$kyc_fetched);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
