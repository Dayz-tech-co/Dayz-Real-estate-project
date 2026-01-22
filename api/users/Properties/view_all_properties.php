<?php

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-Type: application/json");

$api_method = "POST";

if (getenv("REQUEST_METHOD") === $api_method) {
    try {
        // No authentication required for viewing all properties

        // Collect request body (JSON) for pagination
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        // Pagination
        $page = isset($data["page"]) ? max(1, (int)$data["page"]) : 1;
        $limit = isset($data["limit"]) ? max(1, (int)$data["limit"]) : 20;
        $offset = ($page - 1) * $limit;

        // Fetch total count using direct SQL
        $countSql = "
            SELECT COUNT(*) as total
            FROM properties
            WHERE status = 'approved'
              AND deleted_at IS NULL
        ";
        $countResult = $db_call_class->selectRows($countSql);
        $total = $countResult[0]['total'] ?? 0;

        // Fetch properties with pagination using direct SQL
        $propertiesSql = "
            SELECT id, title, description, price, property_category, property_type, bed, bath, balc, hall, kitc, floor, asize, city, state, location, feature, images, thumbnail, featured, verified, created_at, updated_at
            FROM properties
            WHERE status = 'approved'
              AND deleted_at IS NULL
            ORDER BY featured DESC, created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $paginatedProperties = $db_call_class->selectRows($propertiesSql, [
            ':limit' => $limit,
            ':offset' => $offset
        ]);

        if ($utility_class_call->input_is_invalid($paginatedProperties)) {
            $paginatedProperties = [];
        }

        // Decode images and split features
        foreach ($paginatedProperties as &$p) {
            $p['images'] = $utility_class_call->input_is_invalid($p['images']) ? [] : json_decode($p['images'], true);
            $p['features'] = $utility_class_call->input_is_invalid($p['feature']) ? [] : explode(',', $p['feature']);
            unset($p['feature']); // Remove original feature field
        }

        $payload = [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "count" => count($paginatedProperties),
            "properties" => $paginatedProperties
        ];

        $api_status_code_class_call->respondOK($payload, "Properties fetched successfully.");

    } catch (Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
?>
