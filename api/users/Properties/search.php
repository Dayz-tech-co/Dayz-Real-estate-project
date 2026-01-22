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

header("Content-Type: application/json");

$api_method = "POST";

if (getenv("REQUEST_METHOD") === $api_method) {
    try {

        
        $decodedToken = $api_status_code_class_call->ValidateAPITokenSentIN(1, 3);
        $user_pubkey = $decodedToken->usertoken;

        $getUser = $db_call_class->selectRows("users", "id, fname, lname, email, kyc_verified", [[
            ['column' => 'userpubkey', 'operator' => '=', 'value' => $user_pubkey]
        ]]);

        if ($utility_class_call->input_is_invalid($getUser)) {
            $api_status_code_class_call->respondUnauthorized();
        }

        $user_id = $getUser[0]['id'];
        $fullname = $getUser[0]['fname'] . " " . $getUser[0]['lname'];
        $user_email = $getUser[0]['email'];
        // -------------------------------------------------------

        //Collect request body (JSON)
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $keyword        = $data['keyword'] ?? '';
        $property_type  = $data['property_type'] ?? '';
        $property_cat   = $data['property_category'] ?? '';
        $city           = $data['city'] ?? '';
        $state          = $data['state'] ?? '';
        $min_price      = isset($data['min_price']) ? (float)$data['min_price'] : 0;
        $max_price      = isset($data['max_price']) ? (float)$data['max_price'] : 0;
        $bed            = isset($data['bed']) ? (int)$data['bed'] : 0;
        $verified       = isset($data['verified']) ? $data['verified'] : '';
        $featured       = isset($data['featured']) ? $data['featured'] : '';

        // Pagination
        $page = isset($data["page"]) ? max(1, (int)$data["page"]) : 1;
        $limit = isset($data["limit"]) ? max(1, (int)$data["limit"]) : 20;
        $offset = ($page - 1) * $limit;

        // Build dynamic SQL
        $sql = "
            SELECT *
            FROM properties
            WHERE status = 'approved'
              AND deleted_at IS NULL
        ";

        $params = [];

        if (!empty($keyword)) {
            $sql .= " AND (title LIKE :kw OR description LIKE :kw OR location LIKE :kw)";
            $params[':kw'] = "%$keyword%";
        }

        if (!empty($property_type)) {
            $sql .= " AND property_type = :ptype";
            $params[':ptype'] = $property_type;
        }

        if (!empty($property_cat)) {
            $sql .= " AND property_category = :pcat";
            $params[':pcat'] = $property_cat;
        }

        if (!empty($city)) {
            $sql .= " AND city = :city";
            $params[':city'] = $city;
        }

        if (!empty($state)) {
            $sql .= " AND state = :state";
            $params[':state'] = $state;
        }

        if ($min_price > 0) {
            $sql .= " AND price >= :minp";
            $params[':minp'] = $min_price;
        }

        if ($max_price > 0) {
            $sql .= " AND price <= :maxp";
            $params[':maxp'] = $max_price;
        }

        if ($bed > 0) {
            $sql .= " AND bed >= :bed";
            $params[':bed'] = $bed;
        }

        if ($verified !== '') {
            $sql .= " AND verified = :verified";
            $params[':verified'] = (int)$verified;
        }

        if ($featured !== '') {
            $sql .= " AND featured = :featured";
            $params[':featured'] = (int)$featured;
        }

        //Count for pagination
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") AS tcount";
        $total = $db_call_class->selectRows($countSql, $params)[0]['total'] ?? 0;

        //Pagination apply
        $sql .= " ORDER BY featured DESC, created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = (int)$limit;
        $params[':offset'] = (int)$offset;

        $properties = $db_call_class->selectRows($sql, $params);

        //Decode image JSON
        foreach ($properties as &$p) {
            $p['images'] = $utility_class_call->input_is_invalid($p['images']) ? json_decode($p['images'], true) : [];
        }

        $payload = [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "count" => count($properties),
            "properties" => $properties
        ];

        $api_status_code_class_call->respondOK($payload, API_User_Response::$propertyFetched);

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }

} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}

