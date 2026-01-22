<?php

namespace Config;

/**
 * System Messages Class
 *
 * PHP version 5.4
 */
class API_User_Response
{

    /**
     * Welcome message
     *
     * @var string
     */
    // General errors
    public  static $invalidUserDetail = "Invalid username or password";
    public  static $invalidreCAPTCHA = "reCAPTCHA challenge Verification failed";
    public  static $loginSuccessful = "Login successful";
    public  static $registerSuccessful = "User account created successfully";
    public  static $agentregisterSuccessful = "Agent account created successfully";
    public  static $sureveyCreated = "Created successfully";
    public  static $sureveyUpdated = "Updated successfully";
    public  static $unauthorized_token = "Unauthorized";
    public  static $fileSizeTooLarge = "File Size is too Large (Max 2MB)";
    public static $db_error = "Database error, try again later";
   
    public static function fileSizeTooBig($dataName)
    {
        return "$dataName File Size is too Large (Max 2MB)";
    }
    public static function minimumWithdrawal($amount)
    {
        return "The minumum you can withdraw is $amount";
    }

    public  static $onFileAtATime = "One file at a time is allowed";
    public  static $fileTypeNotAllowed = "File type not allowed";
    public  static $fileinvalid = "File Uploaded is not valid";
    public  static $url_not_valid = "URL changed, try again later";
    public  static $user_has_no_access = "You are not allowed to access this resource";
    public  static $user_permanetly_banned = "You have been permanently banned from this platform with the name associated to your bank account details flagged<br>Contact support with your user details if you think this was done in error.";
    public static $otpsentalready = "You need to wait for at least 20 seconds before you can resend";
    public static $userNotFound = "User not found";
    public static $data_found = "Data found";
    public static $missingrequiredfields = "Missing required fields";
    public static $invaliddecision = "Decision must be either 'approved' or 'rejected'.";

    public static $otpSendAlreadyToday = "You can only use this method once a day.";
    public static $otpSendAlreadyToday3 = "You can only use this method three times a day.";
    public static $invalidOtporExpired = "Invalid or expire OTP inputted";
    public  static $user_account_deleted = "Account details deleted from our server";
    public  static $toomanyrequest = "Too many request";
    public  static $request_method_invalid = "Request method used not allowed.";
    public  static $request_not_processed = "Request not processed.";
    public  static $request_body_invalid = "Ensure to input valid details in all fields";
    public  static $Task_fail = "Failed to delete task.";
    public  static $no_task_title = "No task found with the given title.";
    public  static $error_deleting_record = "error_deleting_record";
    public  static $startDateIsInvalid = "Start date is invalid";
    public  static $endtimeIsInvalid = "End Time can not be in the past";
    public static $task_created_suceessful = "Task Successfully Created.";
    public static $adminnotexisting = "The admin assigning the task does not exist";
    public static $admindeleted = "Admin deleted";
    public static $usercannotassign = "Users can't assign task to Admins";
    public static $notaskfoundforadmin = "No tasks found for this admin";
    public static $task_retieved_successfully = "Tasks retrieved successfully";
    public static $notasktitlefound = "No task with the title found.";
    public static $unabletodeletetask = "Unable to delete task. Try again.";
    public static $failtoupdatetask = "Failed to update task status.";
    public static $invalidaction = "Invalid action.";
    public static $notaskfoundforuser = "Task not found for this user.";
    public static $taskdeleted = 'Task deleted successfully.';
    public static $novalidtask = "No valid update field provided.";
    public static $taskupdatedsuccessfully = "Task updated successfully.";
    public static $updatefailed = "Task update failed.";
    public static $pendingapproval = "Task marked as pending approval.";
    public static $notaskpending = "No task pending approval with that title.";
    public static $taskapproved = "Task approved and marked as completed.";
    public static $approvalfailed = "Failed to approve task.";
    public static $taskrejected = "Task rejected and moved back to pending.";
    public static $rejectionfailed = "Failed to reject task.";
    public static $unknowntaskdecision = "Decision must be either 'approve' or 'reject'.";
    public static $usernotexisting = "User with this ID does not exist.";
    public static $useranduserdatadeleted = "User and all assigned tasks deleted successfully.";
    public static $unabletodeleteuser = "Something went wrong. Deletion failed.";
    public static $usersfetched =  "Users fetched successfully.";
    public static $userfetched = "User retrieved successfully.";
    public static $titlerequired = "Title is required for single task view";
    public static $mustbeallorsingle = "Invalid request type. Must be 'all' or 'single";
    public  static $timeIsInvalid = "Time can not be in the past";
    public  static $data_Valid = "Data valid";
    public  static $data_InValid = "Data invalid";
    public  static $data_not_found = "Data not found";
    public  static $error_creating_record = "Error creating record";
    public  static $already_created_record = "Data already created";
    public  static $data_updated = "Data updated successfully";
    public  static $error_updating_record = "Data already updated";
    public  static $internal_error = "Oops an error occured, try again later";
    public static $serverUnderMaintainance = "Server under maintainance, try again later";
    public static $invalidemail = "Invalid email address";
    public static $emailAlreadyVerified = "Email already verified";
    public static $emailVerifiedSuccessFully = "Email verified successfully";
    public static $phoneNoVerifiedSuccessFully = "Phone number verified successfully";
    public static $phonenumberAlreadyVerified = "Phone number already verified";
    public static $accountVerifiedSuccessfully = "Account verified successfully";
    public static $phoneCallNotAllowedToday = "Phone call is currently not available";
    public static $trysmsbeforephonecall = "You need to try SMS before phone call";
    public static $errorSendingMail = "Error sending email. Try again later!";
    public static $errorSendingSms = "Error sending SMS. Try again later!";
    public static $emailotpSentSuccessfully = "Email sent successfully, please check your mail inbox or spam";
    public static $pendingagentsfetched = "Pending agents fetched successfully.";
    public static $smsSentSuccessfully = "OTP sent successfully, please check your phone";
    public static $agentapproved = "Agent approved successfully";
    public static $agentnotpending = "Agent is not pending approval";
    public static $failetoapproveagent = "Failed to approve agent";
    public static $agentnotfound = "Agent not found";
    public static $agentidrequired = "Agent ID is required";
    public static $agentrejected = "Agent rejected successfully";
    public static $failtorejectagent = "Failed to reject agent";
    public static $agentsfetchedsuccessfully = "Agents Fetched Successfully";
    public static $agentswithstatusnotfound = "No agents found with status";
    public static $invalidstatusfilter = "Invalid status type. Allowed: pending, approved, rejected, suspended";
    public static $failtosuspendagent = "Failed to suspend agent";
    public  static $agentsuspended = "Agent suspended successfully";
    public static $agentupdated = "Agent status updated successfully";
    public static $failtoupdateagent = "Failed to update agent status";
    public static $agentidandnewstatusrequired = "Agent ID and new status are required";
    public static $agent_email_not_found = "No email address found for this agent. Please register first.";
    public static $agent_phone_not_found = "No phone number found for this agent. Please register first.";
    public static $no_valid_update_field = "No valid update fields provided.";
    public static $profile_updated_successfully = "Profile updated successfully and confirmation email sent.";
    public static $usersfetchedsuccessfully = "Users fetched successfully";
    public static $user_id_required = "User ID is required.";
    public static $failtoapproveuser = "Failed to approve user";
    public static $userapproved =  "User approved successfully";
    public static $failtorejectuser = "Failed to reject user";
    public static $userrejected = "User rejected successfully";
    public static $failtosuspenduser = "Failed to suspend user";
    public static $usersuspended = "User suspended successfully";
    public static $failtoupdateuser = "Failed to update user status";
    public static $userstatusupdated = "User status updated successfully";
    public static $propertyFetched = "Property fetched successfully";
    public static $propertiesfetched = "Properties retrieved successfully";
    public static $propertynotfound = "Property not found";
    public static $propertyapproved = "Property approved successfully";
    public static $failtoapproveproperty = "Failed to approve property";
    public static $failtorejectproperty = "Failed to reject property";
    public static $propertyrejected = "Property rejected successfully";
    public static $propertyupdated = "Property updated successfully";
    public static $failtoupdateproperty = "Failed to update property";
    public static $failtoflaproperty = "Failed to flag property";
    public static $propertyflagged = "Property flagged as fraudulent";
    public static $propertycreatedsuccessfully = "Property created successfully";
    public static $propertycreationfailed = "Property creation failed";
    public static $propertydeleted = "Property deleted successfully";
    public static $failedtodeleteproperty = "Failed to delete property";
    public static $propertycannotbeupdated = "Only rejected or pending properties can be updated.";
    public static $agentnotapprovetoaddproperty = "Agent is not approved to list properties yet.";
    public static $propertyidandresonrequired = "Property ID and reason for rejection are required.";
    public static $propertyidrequired = "Property ID is required";
    public static $propertydetailsretrieved = "Property details retrieved successfully";
    public static $propertyalreadydeleted  = "Property already deleted.";
    public static $invalidpropertystatus = "Invalid status type. Allowed: pending, approved, rejected, flagged";
    public static $agentundersuspensionorflagged = "This agent is already under suspension or flagged.";
    public static $nopropertypending = "No pending properties found.";
    public static $pendingpropertiesretrieved = "Pending properties successfully retrieved.";
    public static $pendingpropertyretrieved = "Pending property successfully retrieved.";
    public static $transactionsfetched = "Transactions retrieved successfully";
    public static $transactionnotfound = "Transaction not found";
    public static $transactionsuccessful = "Transaction completed successfully";
    public static $transactionidrequired = "Transaction ID is required";
    public static $commissionsettled = "Commission calculated and settled successfully.";
    public static $commissionfailed = "Failed to persist commission settlement. DB returned:";
    public static $invalidcommissionpercentage = "Invalid commission_percentage format. Use numbers like 10 or 7.5 or '10%'.";
    public static $commissionalreadysettled = "Commission already settled for this transaction.";
    public static $commissionstatusnotallowed = "Commission settlement allowed only for transactions with status 'completed'. Current status:";
    public static $invalidtransactionstatus = "Invalid transaction status:";
    public static $validcommissionnumbers = "commission_percentage must be between 0 and 100.";
    public static $transactionupdated = "Transaction status updated successfully";
    public static $transactionfailed = "Failed to update transaction status.";
    public static $transidandstatusneeded = "Transaction ID and status are required.";
    public static $invalidreporttype = "Invalid report type. Use 'monthly' or 'annual'.";
    public static $periodrecordnotfound = "No transactions found for the selected period.";
    public static $reportfetchedsuccessfully = "Sales report fetched successfully.";
    public static $activitiesfetchedsuccessfully = "Active statistics fetched successfully.";
    public static $verificationSent = "Verification code sent successfully";
   public static $mailsendingfailed = "Mail sending not configured on this server.";
   public static $kycverificationsuccessful = "KYC verification updated successfully.";
   public static $kycAlreadyVerified = "KYC already verified.";
   public static $kycsubmittedfailed = "Failed to submit KYC. Try again later.";
   public static $kycSubmittedSuccessfully = "KYC submitted successfully and is pending verification.";
   public static $kycStatusFetched = "KYC status fetched successfully.";
   public static $kycnotverified = "KYC not verified.";
   public static $kycnotfound = "KYC record not found.";
   public static $failtoupdatekyc = "Failed to update KYC record.";
   public static $pendingkycretrieved = "Pending KYC requests retrieved successfully.";
   public static $agentkycnotfound = "No KYC record found for this agent.";
   public static $kyc_record_fetched = "KYC record(s) fetched successfully.";
   public static $kyc_fetched = "KYC record fetched successfully.";
   public static $kycpendingreview = "Your KYC submission is already pending review. Please wait until it is processed.";
   public static $kyc_required = "KYC verification required to access this feature.";
   public static $no_update_field = "No valid update field provided.";
   public static $email_already_exist = "Email already being used before.";
   public static $phone_already_exist = "Phone number already being used before.";
   public static $acct_suspended = "Your account has been suspended. Contact support.";
   public static $acct_frozen = "Your account is frozen. Contact support.";
   public static $acct_rejected =  "Your KYC has been rejected. Contact support for clarification.";
   public static $password_reset_successfully = "Password successfully reset and confirmation email sent.";
   public static $password_reset_otp = "Password reset OTP sent successfully.";
   public static $notification_not_found = "No notification IDs provided.";
   public static $notification_deleted_successfully = "Notifications deleted successfully.";
   public static $notification_read = "Notifications marked as read successfully.";
   public static $notification_retrieved = "Notifications retrieved successfully.";
   public static $userRegisterSuccessful = "User registered successfully. Please verify your email and phone number.";
   public static $user_email_not_found = "No email address found for this user. Please register first.";
   public static $user_phone_not_found = "No phone number found for this user. Please register first.";
   public static $alreadyInWishlist = "Property is already in wishlist.";
   public static $unabletoaddtoWishlist = "Unable to add to wishlist. Try again.";
   public static $addedToWishlist = "Added to wishlist.";
   public static $wishlistFetched = "Wishlist fetched successfully.";
   public static $wishlistEmpty = "No properties in wishlist.";
   public static $wishlistItemNotFound = "Property not found in wishlist.";
   public static $wishlistRemoved = "Property removed from wishlist successfully.";
   public static $bookingsnotfound = "No bookings found";
     public static function lengthError($dataName, $length)
    {
        return "$dataName cannot be more than $length characters long and cannot contain emojis";
    }
    public static function lengthMinMaxError($dataName, $length, $minlen)
    {
        return "$dataName cannot be more than $length or lesser than $minlen and cannot contain emojis";
    }
    public static $invalidPassword = "For security purpose,password requires at least 1 lower and upper case character, 1 number, 1 special character and must be at least 6 characters long";
}
