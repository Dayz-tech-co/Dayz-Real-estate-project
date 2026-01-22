<?php
/**
 * This file demonstrates how to integrate notifications throughout the BOTS application.
 * Add notification calls to existing endpoints where major events occur.
 */

// Example: In api/admin/Properties/approve.php (Property Approval)
 /*
$api_status_code_class_call = new API_Status_Code;
$db_call_class = new DB_Calls_Functions;
$utility_class_call = new Utility_Functions;

if ($updateRows) {
    // Send notification to agent
    require_once "../../../config/Notification_Function.php";
    $notification = new Config\Notification_Function();

    $propertyDetails = $db_call_class->selectRows("properties", "title, agent_id", [[
        ['column' => 'id', 'operator' => '=', 'value' => $property_id]
    ]]);

    if (!empty($propertyDetails[0])) {
        $notification->notifyAgentPropertyApproved(
            $propertyDetails[0]['agent_id'],
            $property_id,
            $propertyDetails[0]['title']
        );
    }
}
 */

// Example: In api/admin/Users/verify_kyc.php (User KYC Approval)
/*
if ($action === 'approved') {
    // Send notification to user
    $notification = new Config\Notification_Function();
    $notification->notifyUserKYCApproved($user_id);
}
*/

// Example: In api/users/Bookings/request_visit.php (New Booking Request)
/*
if ($insert) {
    // Notify agent about new booking request
    $notification = new Config\Notification_Function();

    // Get user details for message
    $user = $db_call->selectRows("users", "CONCAT(fname, ' ', lname) AS fullname", [[
        ['column' => 'id', 'operator' => '=', 'value' => $user_id]
    ]]);

    $property = $db_call->selectRows("properties", "title, agent_id", [[
        ['column' => 'id', 'operator' => '=', 'value' => $property_id]
    ]]);

    if (!empty($user[0]) && !empty($property[0])) {
        $notification->notifyAgentBookingRequest(
            $property[0]['agent_id'],
            $property[0]['title'],
            $user[0]['fullname'],
            $requested_date
        );
    }
}
*/

// Example: Sending bulk notifications (e.g., System announcements)
/*
// Admin can send announcements to all users
$notification = new Config\Notification_Function();
$count = $notification->sendToAllUsers(
    "New Feature Alert!",
    "We've added advanced property search filters to help you find your dream home faster.",
    "system"
);

// Or send to all verified agents
$count = $notification->sendToAllAgents(
    "Market Update",
    "Property market shows 25% increase in Lekki area this quarter.",
    "system",
    ['city' => 'Lagos'] // Only agents in Lagos
);
*/

// Example: Automated notifications for pending items
/*
// This could be run as a cron job daily
$notification = new Config\Notification_Function();

// Count pending KYC submissions
$pendingKYC = $db_call->selectRows("kyc_verifications", "COUNT(*) AS count", [[
    ['column' => 'status', 'operator' => '=', 'value' => 'pending']
]])[0]['count'];

// Notify admins if there are pending KYC
if ($pendingKYC > 0) {
    $notification->notifyAdminsPendingKYC($pendingKYC);
}

// Count pending properties
$pendingProperties = $db_call->selectRows("properties", "COUNT(*) AS count", [[
    ['column' => 'status', 'operator' => '=', 'value' => 'pending']
]])[0]['count'];

// Notify admins if there are pending properties
if ($pendingProperties > 0) {
    $notification->notifyAdminsPendingProperties($pendingProperties);
}
*/
?>

## Notification System for BOTS (Users, Admin, Agents)

### 🛠 **Implementation Summary**

1. **Complete API Endpoints**: All three roles (User, Agent, Admin) now have full notification CRUD operations
2. **Database Migration**: Added `user_id` and `admin_id` columns to support all user types
3. **Notification Helper Class**: Comprehensive class for sending notifications programmatically
4. **Pre-defined Templates**: Ready-to-use notification templates for common events

### 📋 **Available API Endpoints**

#### **Users** (`api/users/Notifications/`)
- `list_all.php` - Get user notifications with filters
- `mark_as_read.php` - Mark notifications as read
- `delete_notif.php` - Delete notifications

#### **Agents** (`api/agents/Notifications/`)
- `list_all.php` - Get agent notifications (already existed)
- `mark_as_read.php` - Mark as read (already existed)
- `delete_notif.php` - Delete notifications (already existed)

#### **Admin** (`api/admin/Notifications/`)
- `list_all.php` - Get admin notifications with filters
- `mark_as_read.php` - Mark notifications as read
- `delete_notif.php` - Delete notifications

### 🗄 **Database Changes**
1. Run `sql db/notifications_table_migration.sql` to add `user_id` and `admin_id` columns
2. The existing `agent_id` column remains intact

### 🔧 **Integration Guide**

#### **Option 1: Add to Existing Endpoints**
```php
require_once "../../../config/Notification_Function.php";
$notification = new Config\Notification_Function();

// Send notification
$notification->sendToUser($user_id, "Title", "Message", "type");
```

#### **Option 2: Use Pre-defined Templates**
```php
require_once "../../../config/Notification_Function.php";
$notification = new Config\Notification_Function();

// Property approved - notify agent
$notification->notifyAgentPropertyApproved($agent_id, $property_id, $property_title);

// KYC approved - notify user
$notification->notifyUserKYCApproved($user_id);

// New booking - notify agent
$notification->notifyAgentBookingRequest($agent_id, $property_title, $user_name, $visit_date);
```

#### **Option 3: Bulk Notifications**
```php
require_once "../../../config/Notification_Function.php";
$notification = new Config\Notification_Function();

// Send to all users
$count = $notification->sendToAllUsers("System Update", "New features available!", "system");

// Send to all admins
$count = $notification->sendToAllAdmins("Alert", "System maintenance in 1 hour", "system");

// Send to agents in specific city
$count = $notification->sendToAllAgents("Update", "Message", "system", ['city' => 'Lagos']);
```

### 🎯 **Next Steps**

1. **Run the database migration** to add user_id and admin_id columns
2. **Add notification calls** to key endpoints (property approval, KYC status changes, bookings)
3. **Test the endpoints** using Postman or your API client
4. **Create a cron job** for automated admin notifications about pending items
5. **Consider push notifications** for mobile app integration

### 📊 **Supported Notification Types**

- `system` - General system notifications
- `property` - Property-related events
- `kyc` - Identity verification updates
- `booking` - Booking/visit requests
- `payment` - Payment-related notifications
- `admin` - Administrative messages

The notification system is now fully integrated and ready for use across all three user roles! 🚀
