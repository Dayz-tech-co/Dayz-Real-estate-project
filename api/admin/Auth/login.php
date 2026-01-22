<?php


require __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


require_once __DIR__ . "/../../../bootstrap.php";

use Config\API_Status_Code;
use Config\API_User_Response;
use Config\DB_Calls_Functions;
use Config\Utility_Functions;

$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

header("Content-type: application/json");
$apimethod = "POST";

if (getenv("REQUEST_METHOD") == $apimethod) {
    try {

        $maindata["frozedata"] = "";

        #Get Post Data
        $email = isset($_POST["email"]) ? $utility_class_call->clean_user_data($_POST["email"], 1) : '';
        $password = isset($_POST["password"]) ? $utility_class_call->clean_user_data($_POST["password"], 1) : "";



        $responseData = $db_call_class->selectRows(
            "admins",
            "id, email, emailverified, password, adminpubkey, phoneno, status, phoneverified",
            [
                [
                    ["column" => "email", "operator" => "=", "value" => $email],
                    "operator" => "OR"
                ]
            ]

        );
        if ($utility_class_call->input_is_invalid($email) || $utility_class_call->input_is_invalid($password)) {
            // Checking if data is empty
            $api_status_code_class_call->respondBadRequest(API_User_Response::$request_body_invalid);
        } elseif ($utility_class_call->input_is_invalid($responseData)) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$invalidUserDetail);
        } elseif ($_ENV["ALLOW_USER_TO_LOGIN_REGISTER"] == 0) {
            $api_status_code_class_call->respondBadRequest(API_User_Response::$serverUnderMaintainance);
        } else {
            $found = $responseData[0];
            $admin_id = $found["id"];
            $dash_mail = $found["email"];
            $emailverified = $found["emailverified"];
            $phoneverified = $found["phoneverified"];
            $pass = $found["password"];
            $phone = $found["phoneno"];
            $adminpubkey = $found["adminpubkey"];
            $statusis = $found["status"];
            $banreason = "You have been Banned";

            // if($utility_class_call->is_theGoogleCaptchaValid($googlecode)) {
            // verify the new password with the db pass
            $verifypass = $utility_class_call->is_password_hash_valid($password, $pass);
            if ($verifypass) {
                $systemname = $_ENV["APP_NAME"];
                // on every login update admin pub key to logout of other devices
                $adminpubkey = $db_call_class->createUniqueRandomStringForATableCol(29, "admins", "adminpubkey", "$systemname", true, true, true);
                $updateddate = $db_call_class->updateRows(
                    "admins",
                    ["adminpubkey" => $adminpubkey],

                    [

                        ["column" => 'id', "operator" => "=", "value" => $admin_id]

                    ]
                );
                if ($updateddate) {

                    if ($statusis == 1) {
                        $maindata = [];

                        $maindata["email_verified"] = $emailverified;
                        $maindata["phoneverified"] = $phoneverified;
                        $maindata["phone_no"] = $phone;
                        $maindata["email"] = $dash_mail;

                        #To Check For 2FA Authentication
                        $ipaddress = $utility_class_call->getIpAddress();
                        $getcount = 0;
                        $responseData = $db_call_class->selectRows(
                            "user_sessions",
                            "email",
                            [
                                [
                                    [
                                        "column" => "ipaddress",
                                        "operator" => "=",
                                        "value" => $ipaddress
                                    ]
                                ]
                            ]

                        );
                        if (!$utility_class_call->input_is_invalid($responseData)) {
                            $getcount = count($responseData);
                        }
                        $datatosave = time();
                        $db_call_class->updateRows(
                            "admins",
                            ["login_last_with" => 2, "last_time_logged_in" => $datatosave],
                            [
                                ["column" => "id", "operator" => "=", "value" => $admin_id]
                            ]
                        );


                        // saving user login session
                        $seescode = $db_call_class->createUniqueRandomStringForATableCol(20, "user_sessions", "sessioncode", time(), true, true, true);
                        $browser = ' ' . $utility_class_call->getBrowserInfo()['name'] . ' on ' . ucfirst($utility_class_call->getBrowserInfo()['platform']);
                        $location = '';
                        //Put sessioncode inside database
                        $db_call_class->insertRow("user_sessions", ["email" => $email, 'sessioncode' => $seescode, 'ipaddress' => $ipaddress, 'browser' => $browser, 'forwho' => 1, 'location' => $location,]);
                        // generating user access token

                        $accesstoken = $api_status_code_class_call->getTokenToSendAPI($adminpubkey,);
                        $maindata['access_token'] = $accesstoken;

                        $maindata = [$maindata];
                        $text = API_User_Response::$loginSuccessful;
                        $api_status_code_class_call->respondOK($maindata, $text);
                    } elseif ($statusis == 2) { //suspended
                        $api_status_code_class_call->respondBadRequest($banreason);
                    } elseif ($statusis == 3) { //frozen
                        $api_status_code_class_call->respondBadRequest($banreason);
                    } elseif ($statusis == 0) { //banned
                        $api_status_code_class_call->respondBadRequest($banreason);
                    } elseif ($statusis == 4) { //deleted
                        $api_status_code_class_call->respondBadRequest(API_User_Response::$user_account_deleted);
                    } else {
                        $api_status_code_class_call->respondBadRequest(API_User_Response::$user_permanetly_banned);
                    }
                } else {
                    $api_status_code_class_call->respondBadRequest(API_User_Response::$invalidreCAPTCHA);
                }
            } else {
                $api_status_code_class_call->respondBadRequest(API_User_Response::$invalidUserDetail);
            }
            // }else{
            //     $api_status_code_class_call->respondBadRequest(API_User_Response::$invalidreCAPTCHA);
            // }
        }
    } catch (\Exception $e) {
        $api_status_code_class_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_code_class_call->respondMethodNotAlowed();
}
