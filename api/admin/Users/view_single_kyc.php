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
        // Validate Admin Token
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 1);

        // Collect Input
        $user_id = $utility_class_call->clean_user_data($_GET['user_id'] ?? '', 1);
        $sort_by = $_GET['sort_by'] ?? 'created_at';
        $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');
        $search = trim($_GET['search'] ?? '');

        if (empty($user_id)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$missingrequiredfields);
        }

        // Allowed sorting
        $allowed_sort_columns = ['status', 'government_id_type', 'created_at'];
        if (!in_array($sort_by, $allowed_sort_columns)) {
            $sort_by = 'created_at';
        }

        $allowed_sort_order = ['ASC', 'DESC'];
        if (!in_array($sort_order, $allowed_sort_order)) {
            $sort_order = 'DESC';
        }

        // Build WHERE clause
        $where = [
            ['column' => 'k.user_id', 'operator' => '=', 'value' => $user_id]
        ];

        if (!empty($search)) {
            $where[] = [
                'column' => '(k.government_id_number LIKE ? OR k.status LIKE ?)',
                'operator' => '',
                'value' => "%$search%"
            ];
        }

        // Define JOIN
        $joins = [
            [
                'type' => 'INNER',
                'table' => 'users AS u',
                'condition' => 'u.id = k.user_id'
            ]
        ];

        // Query Options
        $options = [
            'joins' => $joins,
            'orderBy' => "k.$sort_by",
            'orderDirection' => $sort_order
        ];

        // Execute Query
        $kycDetails = $db_call_class->selectRows(
            'users_kyc_verifications AS k',
            '
                k.id,
                k.government_id_type,
                k.government_id_number,
                k.document_front,
                k.document_back,
                k.proof_of_address_type,
                k.proof_of_address_document,
                k.address,
                k.city,
                k.state,
                k.country,
                k.status,
                k.verified,
                k.proof_of_address_status,
                k.proof_of_address_admin_comment,
                k.admin_comment,
                k.created_at,
                u.fname,
                u.lname,
                u.email
            ',
            $where,
            $options
        );

        if (empty($kycDetails)) {
            $api_status_code_class_call->respondNotFound("User KYC record not found.");
        }

        // Return Response
        $api_status_code_class_call->respondOK([
            "kyc_records" => $kycDetails,
            "sorting" => [
                "sorted_by" => $sort_by,
                "sort_order" => $sort_order
            ]
        ], "User KYC fetched successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($e->getMessage());
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
