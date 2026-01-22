<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\Mail_SMS_Responses;
use Config\API_User_Response;

header("Content-Type: application/json");
$api_method = 'POST';

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;
$mail_sms_call = new Mail_SMS_Responses;

if (getenv('REQUEST_METHOD') == $api_method) {
    try {
        // Get post data
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
            $InsertResponseData = $db_call->insertRow("agents", [
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
                'Agentpubkey' => $Agent_pub_Key
                
            ]);

            if ($InsertResponseData > 0) {
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
                $tokentype = 2;
                $accesstoken = $api_status_call->getTokenToSendAPI($Agent_pub_Key, $tokentype);

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
