<?php

namespace Config;

class Notification_Queue
{
    private $db;

    public function __construct()
    {
        $this->db = new DB_Calls_Functions();
    }

    /**
     * Queue a notification for async processing
     * @param array $notificationData Data for the notification job
     * @return bool Success status
     */
    public function queueNotification(array $notificationData): bool
    {
        try {
            $queueData = [
                'type' => $notificationData['type'] ?? 'notification',
                'recipient_type' => $notificationData['recipient_type'], // 'user', 'agent', 'admin'
                'recipient_id' => $notificationData['recipient_id'],
                'subject' => $notificationData['subject'] ?? null,
                'message' => $notificationData['message'],
                'data' => json_encode($notificationData['data'] ?? []),
                'priority' => $notificationData['priority'] ?? 'normal', // 'low', 'normal', 'high'
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => $notificationData['max_attempts'] ?? 3,
                'next_attempt_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $result = $this->db->insertRow("notification_queue", $queueData);
            return $result > 0;
        } catch (\Exception $e) {
            error_log("Error queuing notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue an email notification
     */
    public function queueEmail(string $to, string $subject, string $message, string $altText = '', array $options = []): bool
    {
        return $this->queueNotification([
            'type' => 'email',
            'recipient_type' => $options['recipient_type'] ?? 'external',
            'recipient_id' => $options['recipient_id'] ?? 0,
            'subject' => $subject,
            'message' => $message,
            'data' => [
                'to' => $to,
                'alt_text' => $altText,
                'template' => $options['template'] ?? null,
                'attachments' => $options['attachments'] ?? []
            ],
            'priority' => $options['priority'] ?? 'normal',
            'max_attempts' => $options['max_attempts'] ?? 3
        ]);
    }

    /**
     * Queue an SMS notification
     */
    public function queueSMS(string $to, string $message, array $options = []): bool
    {
        return $this->queueNotification([
            'type' => 'sms',
            'recipient_type' => $options['recipient_type'] ?? 'external',
            'recipient_id' => $options['recipient_id'] ?? 0,
            'subject' => null,
            'message' => $message,
            'data' => [
                'to' => $to,
                'use_whatsapp' => $options['use_whatsapp'] ?? false
            ],
            'priority' => $options['priority'] ?? 'high', // SMS usually higher priority
            'max_attempts' => $options['max_attempts'] ?? 2
        ]);
    }

    /**
     * Queue an in-app notification
     */
    public function queueInAppNotification(int $userId, string $title, string $message, string $type = 'system', array $options = []): bool
    {
        return $this->queueNotification([
            'type' => 'in_app',
            'recipient_type' => $options['recipient_type'] ?? 'user',
            'recipient_id' => $userId,
            'subject' => $title,
            'message' => $message,
            'data' => [
                'notification_type' => $type,
                'admin_comment' => $options['admin_comment'] ?? null
            ],
            'priority' => $options['priority'] ?? 'normal',
            'max_attempts' => 1 // In-app notifications are simple DB inserts
        ]);
    }

    /**
     * Process pending notifications (called by background worker)
     * @param int $limit Maximum number of notifications to process
     * @return int Number of notifications processed
     */
    public function processQueue(int $limit = 10): int
    {
        try {
            // Get pending notifications that are ready to be processed
            $pendingNotifications = $this->db->selectRows(
                "notification_queue",
                "*",
                [[
                    ['column' => 'status', 'operator' => '=', 'value' => 'pending'],
                    ['column' => 'next_attempt_at', 'operator' => '<=', 'value' => date('Y-m-d H:i:s')]
                ]],
                [
                    'limit' => $limit,
                    'order_by' => 'priority DESC, created_at ASC' // High priority first, then FIFO
                ]
            );

            $processed = 0;
            foreach ($pendingNotifications as $notification) {
                if ($this->processNotification($notification)) {
                    $processed++;
                }
            }

            return $processed;
        } catch (\Exception $e) {
            error_log("Error processing notification queue: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Process a single notification
     */
    private function processNotification(array $notification): bool
    {
        try {
            $this->markAsProcessing($notification['id']);

            $success = false;
            $errorMessage = null;

            switch ($notification['type']) {
                case 'email':
                    $data = json_decode($notification['data'], true);
                    $success = $this->sendQueuedEmail($data, $errorMessage);
                    break;

                case 'sms':
                    $data = json_decode($notification['data'], true);
                    $success = $this->sendQueuedSMS($data, $errorMessage);
                    break;

                case 'in_app':
                    $data = json_decode($notification['data'], true);
                    $success = $this->sendQueuedInAppNotification($notification, $data, $errorMessage);
                    break;

                default:
                    $errorMessage = "Unknown notification type: {$notification['type']}";
            }

            if ($success) {
                $this->markAsCompleted($notification['id']);
                return true;
            } else {
                $this->handleFailure($notification, $errorMessage);
                return false;
            }
        } catch (\Exception $e) {
            $this->handleFailure($notification, $e->getMessage());
            return false;
        }
    }

    private function sendQueuedEmail(array $data, ?string &$errorMessage): bool
    {
        try {
            $mailSms = new Mail_SMS_Responses();

            // Retry logic with exponential backoff
            $maxRetries = 3;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $result = $mailSms->sendThePhpMailerMail(
                    $data['subject'] ?? 'Notification',
                    $data['to'],
                    $data['alt_text'] ?? '',
                    $data['message'],
                    ''
                );

                if ($result === true) {
                    return true;
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt - 1));
                }
            }

            $errorMessage = "Email sending failed after {$maxRetries} attempts";
            return false;
        } catch (\Exception $e) {
            $errorMessage = "Email sending error: " . $e->getMessage();
            return false;
        }
    }

    private function sendQueuedSMS(array $data, ?string &$errorMessage): bool
    {
        try {
            $mailSms = new Mail_SMS_Responses();

            // SMS has fewer retries due to cost
            $maxRetries = 2;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                if ($data['use_whatsapp']) {
                    $result = $mailSms->sendUserWhatsappOTP($data['to'], $data['message']);
                } else {
                    $result = $mailSms->sendUserSMSOTP($data['to'], $data['message']);
                }

                if ($result === true) {
                    return true;
                }

                // Wait before retry
                if ($attempt < $maxRetries) {
                    sleep(1);
                }
            }

            $errorMessage = "SMS sending failed after {$maxRetries} attempts";
            return false;
        } catch (\Exception $e) {
            $errorMessage = "SMS sending error: " . $e->getMessage();
            return false;
        }
    }

    private function sendQueuedInAppNotification(array $notification, array $data, ?string &$errorMessage): bool
    {
        try {
            $notifyFunc = new Notification_Function();

            $result = false;
            switch ($notification['recipient_type']) {
                case 'user':
                    $result = $notifyFunc->sendToUser(
                        $notification['recipient_id'],
                        $notification['subject'],
                        $notification['message'],
                        $data['notification_type'] ?? 'system'
                    );
                    break;

                case 'agent':
                    $result = $notifyFunc->sendToAgent(
                        $notification['recipient_id'],
                        $notification['subject'],
                        $notification['message'],
                        $data['notification_type'] ?? 'system'
                    );
                    break;

                case 'admin':
                    $result = $notifyFunc->sendToAdmin(
                        $notification['recipient_id'],
                        $notification['subject'],
                        $notification['message'],
                        $data['notification_type'] ?? 'system'
                    );
                    break;
            }

            if (!$result) {
                $errorMessage = "Failed to send in-app notification";
            }

            return $result;
        } catch (\Exception $e) {
            $errorMessage = "In-app notification error: " . $e->getMessage();
            return false;
        }
    }

    private function markAsProcessing(int $id): void
    {
        $this->db->updateRows(
            "notification_queue",
            [
                'status' => 'processing',
                'processing_at' => date('Y-m-d H:i:s')
            ],
            [['column' => 'id', 'operator' => '=', 'value' => $id]]
        );
    }

    private function markAsCompleted(int $id): void
    {
        $this->db->updateRows(
            "notification_queue",
            [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ],
            [['column' => 'id', 'operator' => '=', 'value' => $id]]
        );
    }

    private function handleFailure(array $notification, string $errorMessage): void
    {
        $attempts = $notification['attempts'] + 1;
        $maxAttempts = $notification['max_attempts'];

        if ($attempts >= $maxAttempts) {
            // Mark as failed
            $this->db->updateRows(
                "notification_queue",
                [
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'failed_at' => date('Y-m-d H:i:s'),
                    'attempts' => $attempts
                ],
                [['column' => 'id', 'operator' => '=', 'value' => $notification['id']]]
            );

            // Log to file
            $this->logFailedNotification($notification, $errorMessage);
        } else {
            // Schedule retry with exponential backoff
            $nextAttempt = date('Y-m-d H:i:s', strtotime("+".pow(2, $attempts)." minutes"));

            $this->db->updateRows(
                "notification_queue",
                [
                    'status' => 'pending',
                    'error_message' => $errorMessage,
                    'attempts' => $attempts,
                    'next_attempt_at' => $nextAttempt
                ],
                [['column' => 'id', 'operator' => '=', 'value' => $notification['id']]]
            );
        }
    }

    private function logFailedNotification(array $notification, string $errorMessage): void
    {
        $logData = [
            'notification_id' => $notification['id'],
            'type' => $notification['type'],
            'recipient_type' => $notification['recipient_type'],
            'recipient_id' => $notification['recipient_id'],
            'error' => $errorMessage,
            'attempts' => $notification['attempts'] + 1,
            'data' => $notification['data'],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $logFile = __DIR__ . '/../logs/notification_failures_' . date('Y-m-d') . '.log';
        $logEntry = json_encode($logData) . PHP_EOL;

        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        try {
            $stats = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'total' => 0
            ];

            $result = $this->db->selectRows(
                "notification_queue",
                "status, COUNT(*) as count",
                [],
                ['group_by' => 'status']
            );

            foreach ($result as $row) {
                $stats[$row['status']] = (int)$row['count'];
                $stats['total'] += (int)$row['count'];
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting queue stats: " . $e->getMessage());
            return [];
        }
    }
}
