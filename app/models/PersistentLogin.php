<?php
class PersistentLogin {
    private $conn;
    private $table_name = "persistent_sessions";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Check for persistent login and restore session if valid
     */
    public function checkPersistentLogin() {
        try {
            // Check for remember token cookie
            if (isset($_COOKIE['remember_token'])) {
                $token = $_COOKIE['remember_token'];
                
                $query = "SELECT ps.*, c.id as customer_id, c.name, c.email, c.phone, c.registration_type 
                         FROM persistent_sessions ps 
                         JOIN customers c ON ps.customer_id = c.id 
                         WHERE ps.session_id = :token AND ps.expires_at > NOW() 
                         AND c.status = 'active'";
                         
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $session = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Restore session data
                    $sessionData = unserialize($session['session_data']);
                    if ($sessionData) {
                        foreach ($sessionData as $key => $value) {
                            $_SESSION[$key] = $value;
                        }
                    }
                    
                    // Restore preference flags from database
                    $this->restorePreferenceFlags($session['preference_flags']);
                    
                    // Update last accessed time
                    $this->updateLastAccessed($token);
                    
                    // Set new remember token cookie (refresh expiration)
                    setcookie('remember_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                    
                    error_log("Persistent login restored for customer: " . $session['customer_id']);
                    return true;
                } else {
                    // Invalid token, clear cookie
                    setcookie('remember_token', '', time() - 3600, '/');
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Persistent login check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore preference flags from database
     */
    private function restorePreferenceFlags($preferenceFlagsJson) {
        if (!empty($preferenceFlagsJson)) {
            $flags = json_decode($preferenceFlagsJson, true);
            if (is_array($flags)) {
                foreach ($flags as $key => $value) {
                    $_SESSION[$key] = $value;
                }
                
                error_log("Preference flags restored: " . print_r($flags, true));
            }
        }
    }

    /**
     * Create persistent login session
     */
    public function createPersistentLogin($customerId, $sessionData) {
        try {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (365 * 24 * 60 * 60));
            
            // Serialize session data
            $serializedData = serialize($sessionData);
            
            // Extract current preference flags
            $preferenceFlags = $this->extractCurrentPreferenceFlags();
            
            $query = "INSERT INTO persistent_sessions 
                     (session_id, customer_id, session_data, expires_at, created_at, preference_flags) 
                     VALUES (:session_id, :customer_id, :session_data, :expires_at, NOW(), :preference_flags)
                     ON DUPLICATE KEY UPDATE 
                     session_data = :session_data, 
                     expires_at = :expires_at,
                     preference_flags = :preference_flags,
                     last_accessed = NOW()";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $token);
            $stmt->bindParam(':customer_id', $customerId);
            $stmt->bindParam(':session_data', $serializedData);
            $stmt->bindParam(':expires_at', $expiresAt);
            $stmt->bindParam(':preference_flags', $preferenceFlags);
            
            if ($stmt->execute()) {
                // Set remember token cookie for 1 year
                setcookie('remember_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Persistent login creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract current preference flags from session
     */
    private function extractCurrentPreferenceFlags() {
        $flags = [
            'language_preference_set' => $_SESSION['language_preference_set'] ?? false,
            'city_preference_set' => $_SESSION['city_preference_set'] ?? false,
            'notification_preference_set' => $_SESSION['notification_preference_set'] ?? false,
            'user_city_id' => $_SESSION['user_city_id'] ?? 2,
            'user_city_name' => $_SESSION['user_city_name'] ?? 'Kathmandu',
            'user_language' => $_SESSION['user_language'] ?? 'en'
        ];
        
        return json_encode($flags);
    }

    /**
     * Update last accessed time
     */
    private function updateLastAccessed($token) {
        try {
            // Also update preference flags when updating last accessed
            $preferenceFlags = $this->extractCurrentPreferenceFlags();
            
            $query = "UPDATE persistent_sessions SET last_accessed = NOW(), preference_flags = :preference_flags WHERE session_id = :token";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':preference_flags', $preferenceFlags);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Update last accessed error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destroy persistent login
     */
    public function destroyPersistentLogin($token = null) {
        try {
            if ($token === null && isset($_COOKIE['remember_token'])) {
                $token = $_COOKIE['remember_token'];
            }
            
            if ($token) {
                $query = "DELETE FROM persistent_sessions WHERE session_id = :token";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
            }
            
            // Clear remember token cookie
            setcookie('remember_token', '', time() - 3600, '/');
            return true;
        } catch (Exception $e) {
            error_log("Persistent login destruction error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $query = "DELETE FROM persistent_sessions WHERE expires_at <= NOW()";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get active sessions for a customer
     */
    public function getActiveSessions($customerId) {
        try {
            $query = "SELECT * FROM persistent_sessions 
                     WHERE customer_id = :customer_id AND expires_at > NOW() 
                     ORDER BY last_accessed DESC";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customerId);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get active sessions error: " . $e->getMessage());
            return [];
        }
    }
}
?>