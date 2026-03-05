<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\Mail_SMS_Responses;
use Config\API_User_Response;
use Config\Utility_Functions;

header("Content-Type: application/json");
$api_method = 'POST';

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;
$mail_sms_call = new Mail_SMS_Responses;
$utility_class_call = new Utility_Functions;

if (getenv('REQUEST_METHOD') == $api_method) {
    try {
        $db_conn = \Config\DB_Calls_Functions::getDBConnection();
        $db_name = '';
        if ($db_conn) {
            $db_name_result = $db_conn->query("SELECT DATABASE() AS db_name");
            if ($db_name_result) {
                $db_name_row = $db_name_result->fetch_assoc();
                $db_name = $db_name_row['db_name'] ?? '';
            }
        }

        $columnExists = function (string $table, string $column) use ($db_call, $db_name) {
            $rows = $db_call->selectRows(
                "INFORMATION_SCHEMA.COLUMNS",
                "COLUMN_NAME",
                [
                    [
                        ['column' => 'TABLE_SCHEMA', 'operator' => '=', 'value' => $db_name],
                        ['column' => 'TABLE_NAME', 'operator' => '=', 'value' => $table],
                        ['column' => 'COLUMN_NAME', 'operator' => '=', 'value' => $column],
                        'operator' => 'AND'
                    ]
                ]
            );
            return !empty($rows);
        };

        // Get post data
        $full_name = isset($_POST["full_name"]) ? $utility_class_call->clean_user_data($_POST["full_name"], 1) : '';
        $agency_name = isset($_POST["agency_name"]) ? $utility_class_call->clean_user_data($_POST["agency_name"], 1) : '';
        $email = isset($_POST["email"]) ? $utility_class_call->clean_user_data($_POST["email"], 1) : '';
        $password = isset($_POST["password"]) ? $utility_class_call->clean_user_data($_POST["password"], 1) : '';
        $phoneno = isset($_POST["phoneno"]) ? $utility_class_call->clean_user_data($_POST["phoneno"], 1) : '';
        $business_address = isset($_POST["business_address"]) ? $utility_class_call->clean_user_data($_POST["business_address"], 1) : '';
        $city = isset($_POST["city"]) ? $utility_class_call->clean_user_data($_POST["city"], 1) : '';
        $state = isset($_POST["state"]) ? $utility_class_call->clean_user_data($_POST["state"], 1) : '';
        $postal_code = isset($_POST["postal_code"]) ? $utility_class_call->clean_user_data($_POST["postal_code"], 1) : '';
        $streetname = isset($_POST["streetname"]) ? $utility_class_call->clean_user_data($_POST["streetname"], 1) : '';
        $country = isset($_POST["country"]) ? $utility_class_call->clean_user_data($_POST["country"], 1) : '';
        $license_number_raw = $_POST["license_number"] ?? ($_POST["cac_number"] ?? '');
        $cac_number = $utility_class_call->clean_user_data($license_number_raw, 1);
        $years_of_experience = isset($_POST["years_experience"]) ? $utility_class_call->clean_user_data($_POST["years_experience"], 1) : '';

        // Validate required inputs
        if (
            $utility_class_call->input_is_invalid($agency_name) ||
            $utility_class_call->input_is_invalid($email) ||
            $utility_class_call->input_is_invalid($phoneno) ||
            $utility_class_call->input_is_invalid($password)
        ) {
            $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
        } elseif (!$utility_class_call->isPasswordStrong($password)) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidPassword);
        } elseif (!$utility_class_call->isEmailValid($email)) {
            $api_status_call->respondBadRequest(API_User_Response::$invalidemail);
        } elseif ($db_call->checkIfRowExistAndCount('agents', [
            [['column' => 'email', 'operator' => '=', 'value' => $email]]
        ]) > 0) {
            $api_status_call->respondBadRequest(API_User_Response::$already_created_record);
        } elseif ($_ENV["ALLOW_USER_TO_LOGIN_REGISTER"] == 0) {
            $api_status_call->respondNotCompleted(API_User_Response::$serverUnderMaintainance);
        } else {
            // Process registration
            $systemname = $_ENV["APP_NAME"];
            $password = $utility_class_call->Password_encrypt($password);
            $status = 'pending';
            $kyc_verified = 0;
            $is_suspended = 0;

            // Generate Agent Public Key
            $Agent_pub_Key = $db_call->createUniqueRandomStringForATableCol(
                20,
                'agents',
                'Agentpubkey',
                $systemname,
                true,
                true,
                true
            );

            // After successful registration
            $systemname = $_ENV['APP_NAME'];
            $subject = "Welcome to $systemname, $agency_name";
            $messageText = "Welcome to $systemname! Your agent account has been successfully created.";
            $messagetitle = "Welcome Aboard 🎉";
            $greetingText = "Hello $agency_name";
            $mailText = "We’re excited to have you join the $systemname network. Start verifying your email to unlock all agent features.";
            $messageHTML = $mail_sms_call->generalMailTemplate($messagetitle, $greetingText, $mailText, "Let's Go 🚀");

            $mail_sms_call->sendUserMail($subject, $email, $messageText, $messageHTML);


            // Insert agent record
            $insertData = [
                'agency_name' => $agency_name,
                'email' => $email,
                'password' => $password,
                'phoneno' => $phoneno,
                'status' => $status,
                'business_address' => $business_address,
                'country' => $country,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'streetname' => $streetname,
                'Kycdocs' => '',
                'Agentpubkey' => $Agent_pub_Key
            ];

            if ($columnExists('agents', 'full_name')) {
                $insertData['full_name'] = $full_name;
            }
            if ($columnExists('agents', 'years_of_experience')) {
                $insertData['years_of_experience'] = $years_of_experience;
            }

            $InsertResponseData = $db_call->insertRow("agents", $insertData);

            if ($InsertResponseData > 0) {
                // Seed agent KYC identity record in kyc_verifications (not agents table)
                if (!$utility_class_call->input_is_invalid($cac_number)) {
                    $existingKyc = $db_call->selectRows("kyc_verifications", "id", [[
                        ['column' => 'agent_id', 'operator' => '=', 'value' => $InsertResponseData]
                    ]]);

                    $kycPayload = [
                        'agent_id' => $InsertResponseData,
                        'agency_name' => $agency_name,
                        'business_reg_no' => $cac_number,
                        'government_id_type' => 'CAC',
                        'government_id_number' => $cac_number,
                        'address' => $business_address,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'status' => 'pending',
                        'verified' => 0,
                        'updated_at' => date("Y-m-d H:i:s")
                    ];

                    if (empty($existingKyc)) {
                        $kycPayload['created_at'] = date("Y-m-d H:i:s");
                        $db_call->insertRow("kyc_verifications", $kycPayload);
                    } else {
                        $db_call->updateRows("kyc_verifications", $kycPayload, [
                            ['column' => 'agent_id', 'operator' => '=', 'value' => $InsertResponseData]
                        ]);
                    }
                }

                // Generate session
                $sesscode = $db_call->createUniqueRandomStringForATableCol(20, "user_sessions", "sessioncode", time(), true, true, true);
                $ipaddress = $utility_class_call->getIpAddress();
                $browser = $utility_class_call->getBrowserInfo()['name'] . ' on ' . ucfirst($utility_class_call->getBrowserInfo()['platform']);
                $location = '';

                $db_call->insertRow("user_sessions", [
                    'email' => $email,
                    'sessioncode' => $sesscode,
                    'ipaddress' => $ipaddress,
                    'browser' => $browser,
                    'forwho' => 2, // 2 for agent
                    'location' => $location
                ]);

                // Create JWT token
                $tokentype = 1;
                $accesstoken = $api_status_call->getTokenToSendAPI($Agent_pub_Key, $tokentype, 2);

                $maindata = [
                    'access_token' => $accesstoken,
                    'email_verified' => 0,
                    'phone_no_verified' => 0,
                    'phone_no_added' => 1
                ];

                $api_status_call->respondOK([$maindata], API_User_Response::$agentregisterSuccessful);
            } else {
                $api_status_call->respondInternalError(API_User_Response::$error_creating_record, API_User_Response::$error_creating_record);
            }
        }
    } catch (\Exception $e) {
        $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_call->respondMethodNotAlowed();
}
