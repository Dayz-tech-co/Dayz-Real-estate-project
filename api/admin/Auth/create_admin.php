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
$cacheit = 0;

$expired_data = ['hr' => 0, 'min' => 0, 'sec' => 20];

$api_status_call = new Config\API_Status_Code;
$db_call = new Config\DB_Calls_Functions;

if(getenv('REQUEST_METHOD') == $api_method){
    try{

       

    #Get Post Data
    $email = isset($_POST["email"])? $utility_class_call->clean_user_data($_POST["email"],1) : '';
    $password = isset($_POST["password"])? $utility_class_call->clean_user_data($_POST["password"], 1) : '';
    $phoneno = isset($_POST["phoneno"])? $utility_class_call->clean_user_data($_POST["phoneno"], 1): '';
     $fname = isset($_POST["fname"])? $utility_class_call->clean_user_data($_POST["fname"], 1): '';
     $lname = isset($_POST["lname"])? $utility_class_call->clean_user_data($_POST["lname"], 1): '';
    if ($utility_class_call->input_is_invalid($email) || $utility_class_call->input_is_invalid($password) || $utility_class_call->input_is_invalid($phoneno)|| $utility_class_call->input_is_invalid($fname) || $utility_class_call->input_is_invalid($lname)){
        $api_status_call->respondBadRequest(API_User_Response::$request_body_invalid);
    } elseif (!$utility_class_call->isPasswordStrong($password)){ // checking if data is valid
        $api_status_call->respondBadRequest(API_User_Response::$invalidPassword);
    } elseif ($db_call->checkIfRowExistAndCount('admins',
    [
        [
            [
                'column' => 'email', 'operator' => '=', 'value' => $email
            ],
        ]
    ])>0){
        // checking if data is valid
        $api_status_call->respondBadRequest(API_User_Response::dataAlreadyExist("Email"));
    } 
    elseif (!$utility_class_call->isEmailValid($email)) {
        $api_status_call->respondBadRequest(API_User_Response::dataInvalid('Email'));
    } elseif ($_ENV["ALLOW_USER_TO_LOGIN_REGISTER"] ==0){
        $api_status_call->respondNotCompleted(API_User_Response::$serverUnderMaintainance);
    } else{
        // getting system settings
        $systemname = $_ENV["APP_NAME"];
        $password = $utility_class_call->Password_encrypt($password);
        //  creating user details
        $status = 1;
        //  generating admin pub key
        // $length, $tablename, $tablecolname, $tokentag, $addnumbers, $addcapitailetters, $addsmallletters
        $Admin_pub_Key = $db_call->createUniqueRandomStringForATableCol(20,'admins', "adminpubkey","$systemname", true, true, true,);

        $InsertResponseData = $db_call->insertRow("admins", ["email"=>$email, "password" =>$password, 'phoneno'=>$phoneno, 'status' => $status, 'fname' => $fname, 'lname' => $lname, 'adminpubkey' =>$Admin_pub_Key]);
        if($InsertResponseData>0){
            $last_id = $InsertResponseData;
            // saving Admin login session
            $sesscode = $db_call->createUniqueRandomStringForATableCol(20, "user_sessions", "sessioncode", time(), true, true, true);
            $ipaddress = $utility_class_call->getIpAddress();
            $browser = ' '.$utility_class_call->getBrowserInfo()['name']. ' on '.ucfirst($utility_class_call->getBrowserInfo()['platform']);
            $location = '';

            // Put sessioncode inside database 

            $db_call->insertRow("user_sessions", ["email" => $email, "sessioncode" => $sesscode, 'ipaddress' =>$ipaddress, 'browser' =>$browser, 'forwho'=> 1, 'location' => $location]);

            // generating user access token
            $emailverified = 0;
            $tokentype = 1;
            $accesstoken = $api_status_call->getTokenToSendAPI($Admin_pub_Key, $tokentype);
            $maindata['access_token']=$accesstoken;
            $maindata['email_verified']=0;
            $maindata['phone_no_verified']=0;
            $maindata['phone_no_added']=1;

            $maindata=[$maindata];
            $text=API_User_Response::$registerSuccessful;
            $api_status_call->respondOK($maindata,$text);
        } else{
            $api_status_call->respondInternalError(API_User_Response::$error_creating_record,API_User_Response::$error_creating_record);
        }
    }


    } catch(\Exception $e){
        $api_status_call->respondInternalError($utility_class_call->get_details_from_exception($e));
    }
} else {
    $api_status_call->respondMethodNotAlowed();
}
?>