<?php
// app/models/Notification.php
class Notification {
    private $conn;
    private $table_name = "user_notification_preferences";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get notification preferences for user
     */
    public function getPreferences($user_id, $session_id = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE ";
            $params = [];
            
            if ($user_id) {
                $query .= "user_id = :user_id";
                $params[':user_id'] = $user_id;
            } else {
                $query .= "session_id = :session_id";
                $params[':session_id'] = $session_id;
            }
            
            $query .= " ORDER BY updated_at DESC LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
                return $this->formatPreferences($preferences);
            }
            
            return $this->getDefaultPreferences();
            
        } catch (Exception $e) {
            error_log("Error getting notification preferences: " . $e->getMessage());
            return $this->getDefaultPreferences();
        }
    }
    
    /**
     * Save notification preferences
     */
    public function savePreferences($user_id, $session_id, $preferences) {
        try {
            // Check if preferences already exist
            $existing = $this->getExistingRecord($user_id, $session_id);
            
            if ($existing) {
                // Update existing
                $query = "UPDATE " . $this->table_name . " SET 
                         push_notifications_enabled = :push_enabled,
                         main_notification_enabled = :main_enabled,
                         offers_notifications = :offers,
                         delivery_tracking_notifications = :delivery,
                         system_notifications = :system,
                         updated_at = NOW()
                         WHERE id = :id";
            } else {
                // Insert new
                $query = "INSERT INTO " . $this->table_name . " 
                         (user_id, session_id, push_notifications_enabled, main_notification_enabled, 
                          offers_notifications, delivery_tracking_notifications, system_notifications, created_at, updated_at) 
                         VALUES (:user_id, :session_id, :push_enabled, :main_enabled, :offers, :delivery, :system, NOW(), NOW())";
            }
            
            $stmt = $this->conn->prepare($query);
            
            // Bind common parameters
            $stmt->bindValue(':push_enabled', $preferences['push_notifications_enabled'] ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':main_enabled', $preferences['main_notification_enabled'] ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':offers', $preferences['offers_notifications'] ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':delivery', $preferences['delivery_tracking_notifications'] ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':system', $preferences['system_notifications'] ? 1 : 0, PDO::PARAM_INT);
            
            // Bind identifier
            if ($existing) {
                $stmt->bindValue(':id', $existing['id'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':user_id', $user_id ?: null);
                $stmt->bindValue(':session_id', $session_id ?: null);
            }
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error saving notification preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Transfer session preferences to user after login/registration
     */
    public function transferSessionPreferencesToUser($user_id, $session_id) {
        try {
            // Get session preferences
            $sessionPrefs = $this->getPreferences(null, $session_id);
            
            if ($sessionPrefs && !$this->isDefaultPreferences($sessionPrefs)) {
                // Save to user
                $this->savePreferences($user_id, null, $sessionPrefs);
                
                // Remove session preferences
                $this->deleteSessionPreferences($session_id);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error transferring notification preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get existing record
     */
    private function getExistingRecord($user_id, $session_id) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE ";
            $params = [];
            
            if ($user_id) {
                $query .= "user_id = :user_id";
                $params[':user_id'] = $user_id;
            } else {
                $query .= "session_id = :session_id";
                $params[':session_id'] = $session_id;
            }
            
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error getting existing record: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete session preferences
     */
    private function deleteSessionPreferences($session_id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE session_id = :session_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':session_id', $session_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error deleting session preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format preferences for consistent output
     */
    private function formatPreferences($preferences) {
        return [
            'push_notifications_enabled' => (bool)($preferences['push_notifications_enabled'] ?? true),
            'main_notification_enabled' => (bool)($preferences['main_notification_enabled'] ?? true),
            'offers_notifications' => (bool)($preferences['offers_notifications'] ?? true),
            'delivery_tracking_notifications' => (bool)($preferences['delivery_tracking_notifications'] ?? true),
            'system_notifications' => (bool)($preferences['system_notifications'] ?? true)
        ];
    }
    
    /**
     * Get default preferences
     */
    private function getDefaultPreferences() {
        return [
            'push_notifications_enabled' => true,
            'main_notification_enabled' => true,
            'offers_notifications' => true,
            'delivery_tracking_notifications' => true,
            'system_notifications' => true
        ];
    }
    
    /**
     * Check if preferences are default
     */
    private function isDefaultPreferences($preferences) {
        $default = $this->getDefaultPreferences();
        return $preferences === $default;
    }
}
?>