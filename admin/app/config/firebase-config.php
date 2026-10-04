<?php
// firebase-config.php — sanitized public configuration.
// All values come from environment variables. No keys are committed.

class FirebaseConfig {
    private static $instance = null;
    private $config;

    private function __construct() {
        $this->config = [
            'project_id'           => getenv('FIREBASE_PROJECT_ID') ?: '',
            'project_number'       => getenv('FIREBASE_PROJECT_NUMBER') ?: '',
            'api_key'              => getenv('FIREBASE_API_KEY') ?: '',
            'server_key'           => getenv('FIREBASE_SERVER_KEY') ?: '',
            'service_account_file' => getenv('FIREBASE_SERVICE_ACCOUNT_FILE') ?: (__DIR__ . '/service-account-key.json'),
            'vapid_public_key'     => getenv('FIREBASE_VAPID_PUBLIC_KEY') ?: '',
            'vapid_private_key'    => getenv('FIREBASE_VAPID_PRIVATE_KEY') ?: '',
        ];
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new FirebaseConfig();
        }
        return self::$instance;
    }

    public function get($key) {
        return $this->config[$key] ?? null;
    }

    public function getAll() {
        return $this->config;
    }

    public function getFcmUrl() {
        return "https://fcm.googleapis.com/v1/projects/{$this->config['project_id']}/messages:send";
    }

    public function getLegacyFcmUrl() {
        return "https://fcm.googleapis.com/fcm/send";
    }

    public function getServerKey() {
        return $this->getSystemSetting('fcm_server_key') ?? $this->config['server_key'];
    }

    public function getVapidPublicKey() {
        return $this->getSystemSetting('vapid_public_key') ?? $this->config['vapid_public_key'];
    }

    public function getVapidPrivateKey() {
        return $this->getSystemSetting('vapid_private_key') ?? $this->config['vapid_private_key'];
    }

    private function getSystemSetting($key) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['setting_value'] ?? null;
        } catch (Exception $e) {
            error_log("[FirebaseConfig] Error getting system setting: " . $e->getMessage());
            return null;
        }
    }
}
?>
