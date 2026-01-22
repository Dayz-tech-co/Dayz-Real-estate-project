<?php

namespace Config;

class Notification_Function
{
    private $db;
    private $mail_sms_call;

    public function __construct()
    {
        $this->db = new DB_Calls_Functions();
        $this->mail_sms_call = new Mail_SMS_Responses();
    }


    private function insertNotification($data)
    {
        $data['is_read'] = 0;
        $data['created_at'] = date("Y-m-d H:i:s");
        return $this->db->insertRow("notifications", $data) > 0;
    }


    /**
     * Send notification to a user
     * @param int $user_id Target user ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (e.g., 'property', 'kyc', 'admin', 'system', 'payment')
     * @return bool Success status
     */


    public function sendToUser($user_id, $title, $message, $type = 'system')
    {
        try {
            $notificationData = [
                'user_id' => $user_id,
                'title' => $title,
                'message' => $message,
                'admin_comment' => $reason ?? null,
                'type' => 'kyc',
                'is_read' => 0,
                'created_at' => date("Y-m-d H:i:s")
            ];


            $result = $this->db->insertRow("notifications", $notificationData);
            return $this->insertNotification(['user_id' => $user_id, 'title' => $title, 'message' => $message, 'type' => $type]);
        } catch (\Exception $e) {
            error_log("Error sending notification to user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to an agent
     * @param int $agent_id Target agent ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @return bool Success status
     */
    public function sendToAgent($agent_id, $title, $message, $type = 'system')
    {
        try {
            $notificationData = [
                "agent_id" => $agent_id,
                "title" => $title,
                "message" => $message,
                "type" => $type,
                "is_read" => 0,
                "created_at" => date("Y-m-d H:i:s")
            ];

            $result = $this->db->insertRow("notifications", $notificationData);
            return $result > 0;
        } catch (\Exception $e) {
            error_log("Error sending notification to agent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to an admin
     * @param int $admin_id Target admin ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @return bool Success status
     */
    public function sendToAdmin($admin_id, $title, $message, $type = 'system')
    {
        try {
            $notificationData = [
                "admin_id" => $admin_id,
                "title" => $title,
                "message" => $message,
                "type" => $type,
                "is_read" => 0,
                "created_at" => date("Y-m-d H:i:s")
            ];

            $result = $this->db->insertRow("notifications", $notificationData);
            return $result > 0;
        } catch (\Exception $e) {
            error_log("Error sending notification to admin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all active admins
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @return int Number of notifications sent
     */
    public function sendToAllAdmins($title, $message, $type = 'system')
    {
        try {
            $admins = $this->db->selectRows("admins", "id", [[
                ['column' => 'status', 'operator' => '=', 'value' => 1] // Active admins only
            ]]);

            $sent = 0;
            foreach ($admins as $admin) {
                if ($this->sendToAdmin($admin['id'], $title, $message, $type)) {
                    $sent++;
                }
            }

            return $sent;
        } catch (\Exception $e) {
            error_log("Error sending notification to all admins: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send notification to all agents with specific criteria
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $criteria Additional filters (e.g., ['status' => 'approved', 'kyc_verified' => 'verified'])
     * @return int Number of notifications sent
     */
    public function sendToAllAgents($title, $message, $type = 'system', $criteria = [])
    {
        try {
            $conditions = [['column' => 'status', 'operator' => '=', 'value' => 'approved']];

            // Add additional criteria
            foreach ($criteria as $column => $value) {
                $conditions[] = ['column' => $column, 'operator' => '=', 'value' => $value];
            }

            $agents = $this->db->selectRows("agents", "id", $conditions);

            $sent = 0;
            foreach ($agents as $agent) {
                if ($this->sendToAgent($agent['id'], $title, $message, $type)) {
                    $sent++;
                }
            }

            return $sent;
        } catch (\Exception $e) {
            error_log("Error sending notification to all agents: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send notification to all users with specific criteria
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $criteria Additional filters
     * @return int Number of notifications sent
     */
    public function sendToAllUsers($title, $message, $type = 'system', $criteria = [])
    {
        try {
            $conditions = [['column' => 'status', 'operator' => '=', 'value' => 'approved']];

            // Add additional criteria
            foreach ($criteria as $column => $value) {
                $conditions[] = ['column' => $column, 'operator' => '=', 'value' => $value];
            }

            $users = $this->db->selectRows("users", "id", $conditions);

            $sent = 0;
            foreach ($users as $user) {
                if ($this->sendToUser($user['id'], $title, $message, $type)) {
                    $sent++;
                }
            }

            return $sent;
        } catch (\Exception $e) {
            error_log("Error sending notification to all users: " . $e->getMessage());
            return 0;
        }
    }

    // Pre-defined notification templates for common events

    /**
     * Send property approval notification to agent
     */
    public function notifyAgentPropertyApproved($agent_id, $property_id, $property_title)
    {
        $title = "Property Approved";
        $message = "Congratulations! Your property '{$property_title}' has been approved and is now live.";

        return $this->sendToAgent($agent_id, $title, $message, 'property');
    }

    /**
     * Send property rejection notification to agent
     */
    public function notifyAgentPropertyRejected($agent_id, $property_id, $property_title, $reason)
    {
        $title = "Property Rejected";
        $message = "Your property '{$property_title}' has been rejected. Reason: {$reason}";

        return $this->sendToAgent($agent_id, $title, $message, 'property');
    }

    /**
     * Send KYC approval notification
     */
    public function notifyUserKYCApproved($user_id)
    {
        $title = "KYC Verified";
        $message = "Great news! Your KYC verification has been approved. You now have full access to all features.";

        return $this->sendToUser($user_id, $title, $message, 'kyc');
    }

    /**
     * Send User KYC rejection notification
     */
    public function notifyUserKYCRejected($user_id, $reason = null)
    {
        $title = "KYC Verification Failed";
        $message = "We could not verify your KYC documents." . ($reason ? " Reason: {$reason}" : "") . " Please re-submit valid documents.";

        return $this->sendToUser($user_id, $title, $message, 'kyc');
    }


    /**
     * Send Agent KYC rejection with admin comment
     */
    public function notifyAgentKYCRejected($agent_id, $admin_comment)
    {
        $title = "KYC Verification Failed";
        $message = "Your KYC verification was rejected. Admin comment: {$admin_comment}. Please re-submit correct documents.";

        return $this->sendToAgent($agent_id, $title, $message, 'kyc');
    }

    /**
     * Notify all admins about a KYC action taken  
     * $type = 'user' OR 'agent'
     * $action = 'approved' OR 'rejected'
     */
    public function notifyAdminKYCAction($target_id, $action, $type)
    {
        $targetType = ($type === 'agent') ? "Agent" : "User";
        $title = "KYC {$action}";
        $message = "{$targetType} with ID {$target_id} has had their KYC {$action}.";

        return $this->sendToAllAdmins($title, $message, 'kyc');
    }



    /**
     * Send booking confirmation to agent
     */
    public function notifyAgentBookingRequest($agent_id, $property_title, $user_name, $visit_date)
    {
        $title = "New Property Visit Request";
        $message = "{$user_name} has requested to visit your property '{$property_title}' on {$visit_date}.";

        return $this->sendToAgent($agent_id, $title, $message, 'booking');
    }

    /**
     * Send booking confirmation to user
     */
    public function notifyUserBookingConfirmed($user_id, $property_title)
    {
        $title = "Booking Confirmed";
        $message = "Your visit request for '{$property_title}' has been confirmed. The agent will contact you soon.";

        return $this->sendToUser($user_id, $title, $message, 'booking');
    }

    /**
     * Notify admins about pending KYC
     */
    public function notifyAdminsPendingKYC($count)
    {
        $title = "Pending KYC Verifications";
        $message = "There are {$count} KYC documents pending verification.";

        return $this->sendToAllAdmins($title, $message, 'kyc');
    }

    /**
     * Notify admins about pending properties
     */
    public function notifyAdminsPendingProperties($count)
    {
        $title = "Pending Property Approvals";
        $message = "There are {$count} properties awaiting approval.";

        return $this->sendToAllAdmins($title, $message, 'property');
    }
}
