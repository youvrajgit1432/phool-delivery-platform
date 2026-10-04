<?php
namespace Phool\DeliveryPanel\Services;

use GuzzleHttp\Client as GuzzleClient;

class NotificationService {
    protected $twilioSid;
    protected $twilioToken;
    protected $twilioPhone;
    protected $mailer;

    public function __construct() {
        $this->twilioSid = getenv('TWILIO_ACCOUNT_SID');
        $this->twilioToken = getenv('TWILIO_AUTH_TOKEN');
        $this->twilioPhone = getenv('TWILIO_PHONE_NUMBER');
    }

    /**
     * Send SMS notification via Twilio
     * 
     * @param string $phone Phone number with country code
     * @param string $message Message text (max 160 chars)
     * @return bool
     */
    public function sendSMS($phone, $message) {
        try {
            if (!$this->twilioSid || !$this->twilioToken) {
                error_log('Twilio credentials not configured');
                return false;
            }

            $client = new GuzzleClient();
            
            $response = $client->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json",
                [
                    'auth' => [$this->twilioSid, $this->twilioToken],
                    'form_params' => [
                        'From' => $this->twilioPhone,
                        'To' => $phone,
                        'Body' => $message
                    ]
                ]
            );

            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            error_log('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification via PHPMailer
     * 
     * @param string $email Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string|null $textBody Plain text fallback
     * @return bool
     */
    public function sendEmail($email, $subject, $htmlBody, $textBody = null) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer();
            
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = getenv('MAIL_HOST');
            $mail->Port = getenv('MAIL_PORT');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('MAIL_USERNAME');
            $mail->Password = getenv('MAIL_PASSWORD');
            $mail->SMTPSecure = 'tls';

            $mail->setFrom(getenv('MAIL_FROM_ADDRESS'), 'Phool Delivery');
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            if ($textBody) {
                $mail->AltBody = $textBody;
            }

            return $mail->send();
        } catch (\Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification via Firebase Cloud Messaging
     * 
     * @param string|array $deviceTokens Firebase device token(s)
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendPushNotification($deviceTokens, $title, $body, $data = []) {
        try {
            $firebaseKey = getenv('FIREBASE_SERVER_KEY');
            
            if (!$firebaseKey) {
                error_log('Firebase key not configured');
                return false;
            }

            $tokens = is_array($deviceTokens) ? $deviceTokens : [$deviceTokens];
            
            $client = new GuzzleClient();
            
            $payload = [
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ],
                'data' => $data
            ];

            $response = $client->post(
                'https://fcm.googleapis.com/fcm/send',
                [
                    'headers' => [
                        'Authorization' => 'key=' . $firebaseKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $payload
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log('Push notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send multi-channel notification
     * 
     * @param int $riderId
     * @param array $notification
     * @return array Delivery status for each channel
     */
    public function sendMultiChannel($riderId, $notification) {
        // Retrieve rider contact details (phone, email, push token)
        // Send via all available channels
        // Log delivery status
        
        return [
            'sms' => false,
            'email' => false,
            'push' => false
        ];
    }

    /**
     * Save notification to database
     * 
     * @param int $riderId
     * @param string $title
     * @param string $message
     * @param string $type order/payment/alert/system
     * @param int|null $orderId
     */
    public function saveNotification($riderId, $title, $message, $type = 'system', $orderId = null) {
        // Insert into notifications table
    }

    /**
     * Get unread notification count
     * 
     * @param int $riderId
     * @return int
     */
    public function getUnreadCount($riderId) {
        // Query notifications where is_read = 0
        return 0;
    }
}
