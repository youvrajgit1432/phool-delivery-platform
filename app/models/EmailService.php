<?php
// app/models/EmailService.php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $conn;
    private $table_otp = "email_verification_otps";
    private $table_settings = "system_settings";
    
    // SMTP configuration variables (will be loaded from database)
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    private $otp_expiry_minutes;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->loadSMTPSettings();
    }
    
    /**
     * Load SMTP settings from database
     */
    private function loadSMTPSettings() {
        try {
            $query = "SELECT setting_key, setting_value FROM " . $this->table_settings . " 
                     WHERE setting_group IN ('email', 'security')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Set SMTP configuration from database
            $this->smtp_host = $settings['smtp_host'] ?? 'smtp.gmail.com';
            $this->smtp_port = $settings['smtp_port'] ?? 587;
            $this->smtp_username = $settings['smtp_username'] ?? '';
            $this->smtp_password = $settings['smtp_password'] ?? '';
            $this->smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
            $this->otp_expiry_minutes = $settings['otp_expiry_minutes'] ?? 10;
            
            error_log("SMTP Settings loaded from database");
            
        } catch (Exception $e) {
            error_log("Error loading SMTP settings: " . $e->getMessage());
            // Fallback to default values
            $this->setDefaultSMTPSettings();
        }
    }
    
    /**
     * Set default SMTP settings as fallback
     */
    private function setDefaultSMTPSettings() {
        $this->smtp_host = 'smtp.gmail.com';
        $this->smtp_port = 587;
        $this->smtp_username = '';
        $this->smtp_password = '';
        $this->smtp_encryption = 'tls';
        $this->otp_expiry_minutes = 10;
    }
    
    /**
     * Send OTP via email
     */
    public function sendOTP($email, $user_id) {
        error_log("=== START sendOTP ===");
        error_log("User ID: $user_id, Email: $email");
        
        try {
            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address: ' . $email);
            }

            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$this->otp_expiry_minutes} minutes"));
            
            error_log("Generated OTP: $otp, Expires: $expires_at");
            
            // Store OTP in database first
            $storeResult = $this->storeOTP($user_id, $email, $otp, $expires_at);
            error_log("Store OTP result: " . ($storeResult ? 'SUCCESS' : 'FAILED'));
            
            if (!$storeResult) {
                throw new Exception('Failed to store OTP in database');
            }
            
            // Try to send email
            error_log("Attempting to send email...");
            $result = $this->sendEmail($email, $otp);
            
            if ($result['success']) {
                error_log("✅ Email sent successfully to: $email");
                return ['success' => true, 'message' => 'OTP sent successfully'];
            } else {
                // If email fails, delete the stored OTP
                error_log("❌ Email sending failed, deleting OTP from database");
                $this->deleteExistingOTP($user_id);
                return $result;
            }
        } catch (Exception $e) {
            error_log("❌ EmailService Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send OTP: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send email using PHPMailer with database configuration
     */
    private function sendEmail($to_email, $otp) {
        error_log("Starting sendEmail to: $to_email");
        
        try {
            $mail = new PHPMailer(true);

            // Server settings - USING DATABASE CONFIGURATION
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = $this->getEncryptionConstant($this->smtp_encryption);
            $mail->Port = $this->smtp_port;
            
            // Important settings for Windows/XAMPP
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Debugging
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug (Level $level): $str");
            };
            
            // Timeout settings
            $mail->Timeout = 30;
            
            // Recipients - Using configured email
            $mail->setFrom($this->smtp_username, 'Phool Delivery');
            $mail->addAddress($to_email);
            $mail->addReplyTo($this->smtp_username, 'Phool Delivery');
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Email Verification OTP - Phool Delivery';
            $mail->Body = $this->getEmailTemplate($otp);
            $mail->AltBody = "Your verification OTP is: $otp. This OTP will expire in {$this->otp_expiry_minutes} minutes.";
            
            error_log("Attempting to send email via PHPMailer...");
            
            if ($mail->send()) {
                error_log("✅ PHPMailer: Email sent successfully to: $to_email");
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                $error = $mail->ErrorInfo;
                error_log("❌ PHPMailer failed: $error");
                return ['success' => false, 'message' => "Email service error: $error"];
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("❌ PHPMailer Exception: $error");
            return ['success' => false, 'message' => "Email service error: $error"];
        }
    }
    
    /**
     * Convert encryption string to PHPMailer constant
     */
    private function getEncryptionConstant($encryption) {
        switch (strtolower($encryption)) {
            case 'ssl':
                return PHPMailer::ENCRYPTION_SMTPS;
            case 'tls':
            default:
                return PHPMailer::ENCRYPTION_STARTTLS;
        }
    }
    
    /**
     * Store OTP in database
     */
    private function storeOTP($user_id, $email, $otp, $expires_at) {
        error_log("Attempting to store OTP in database...");
        
        try {
            // Delete any existing OTP for this user
            $this->deleteExistingOTP($user_id);
            
            $query = "INSERT INTO " . $this->table_otp . " 
                     (user_id, email, otp, expires_at, created_at) 
                     VALUES (:user_id, :email, :otp, :expires_at, NOW())";
            
            error_log("SQL: $query");
            error_log("Params - user_id: $user_id, email: $email, otp: $otp, expires_at: $expires_at");
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt->bindParam(":otp", $otp, PDO::PARAM_STR);
            $stmt->bindParam(":expires_at", $expires_at, PDO::PARAM_STR);
            
            $result = $stmt->execute();
            
            if ($result) {
                $lastId = $this->conn->lastInsertId();
                error_log("✅ OTP stored successfully! ID: $lastId");
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("❌ Failed to store OTP - Error: " . implode(", ", $errorInfo));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Store OTP Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete existing OTP
     */
    private function deleteExistingOTP($user_id) {
        try {
            $query = "DELETE FROM " . $this->table_otp . " WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result && $stmt->rowCount() > 0) {
                error_log("Deleted existing OTPs for user: $user_id");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Delete OTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP($user_id, $otp) {
        error_log("=== START verifyOTP ===");
        error_log("User ID: $user_id, OTP: $otp");
        
        try {
            // Validate OTP format
            if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
                return ['success' => false, 'message' => 'Invalid OTP format'];
            }

            $query = "SELECT * FROM " . $this->table_otp . " 
                     WHERE user_id = :user_id AND otp = :otp AND expires_at > NOW() 
                     ORDER BY created_at DESC LIMIT 1";
            
            error_log("Verify SQL: $query");
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":otp", $otp, PDO::PARAM_STR);
            $stmt->execute();
            
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otpRecord) {
                error_log("✅ OTP found in database, verifying email...");
                
                // OTP is valid, mark email as verified
                if ($this->markEmailAsVerified($user_id)) {
                    $this->deleteExistingOTP($user_id);
                    error_log("✅ Email verified successfully for user: $user_id");
                    return ['success' => true, 'message' => 'Email verified successfully'];
                } else {
                    error_log("❌ Failed to mark email as verified");
                    return ['success' => false, 'message' => 'Failed to verify email'];
                }
            }
            
            error_log("❌ Invalid or expired OTP");
            return ['success' => false, 'message' => 'Invalid or expired OTP'];
            
        } catch (Exception $e) {
            error_log("❌ Verify OTP Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mark email as verified in customers table
     */
    private function markEmailAsVerified($user_id) {
        try {
            $query = "UPDATE customers SET email_verified_at = NOW() WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                error_log("✅ Email marked as verified in customers table");
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("❌ Failed to mark email as verified: " . implode(", ", $errorInfo));
                return false;
            }
        } catch (Exception $e) {
            error_log("❌ Mark Email Verified Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if OTP exists and is valid
     */
    public function hasPendingOTP($user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_otp . " 
                     WHERE user_id = :user_id AND expires_at > NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $hasPending = $result['count'] > 0;
            
            error_log("hasPendingOTP check: " . ($hasPending ? 'YES' : 'NO'));
            return $hasPending;
            
        } catch (Exception $e) {
            error_log("Has Pending OTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resend OTP
     */
    public function resendOTP($user_id, $email) {
        try {
            // Delete any existing OTP first
            $this->deleteExistingOTP($user_id);
            
            // Send new OTP
            return $this->sendOTP($email, $user_id);
        } catch (Exception $e) {
            error_log("Resend OTP Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to resend OTP: ' . $e->getMessage()];
        }
    }

    /**
     * Email template
     */
    private function getEmailTemplate($otp) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Email Verification - Phool Delivery</title>
            <style>
                body { 
                    font-family: 'Arial', sans-serif; 
                    background-color: #f4f4f4; 
                    margin: 0; 
                    padding: 20px; 
                    line-height: 1.6;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 30px; 
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header { 
                    text-align: center; 
                    color: #e91e63; 
                    margin-bottom: 30px;
                }
                .header h2 {
                    margin: 0;
                    font-size: 28px;
                }
                .header h3 {
                    margin: 10px 0 0 0;
                    font-size: 18px;
                    font-weight: normal;
                    color: #666;
                }
                .otp-code { 
                    font-size: 42px; 
                    font-weight: bold; 
                    text-align: center; 
                    color: #e91e63; 
                    margin: 30px 0;
                    letter-spacing: 5px;
                    background: #f8f8f8;
                    padding: 15px;
                    border-radius: 8px;
                    border: 2px dashed #e91e63;
                }
                .instructions {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    border-left: 4px solid #e91e63;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px; 
                    color: #666; 
                    font-size: 12px;
                    border-top: 1px solid #eee;
                    padding-top: 20px;
                }
                .warning {
                    color: #ff5722;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🌺 Phool Delivery</h2>
                    <h3>Email Verification</h3>
                </div>
                
                <p>Hello,</p>
                <p>Thank you for choosing Phool Delivery! Please use the following One-Time Password (OTP) to verify your email address:</p>
                
                <div class='otp-code'>$otp</div>
                
                <div class='instructions'>
                    <p><strong>Instructions:</strong></p>
                    <ol>
                        <li>Enter this OTP in the verification page</li>
                        <li>Complete your registration process</li>
                        <li>Start enjoying our flower delivery services!</li>
                    </ol>
                </div>
                
                <p class='warning'>⚠️ This OTP will expire in {$this->otp_expiry_minutes} minutes for security reasons.</p>
                
                <p>If you didn't request this verification, please ignore this email or contact our support team immediately.</p>
                
                <div class='footer'>
                    <p>Need help? Contact us at <a href='mailto:support@phooldelivery.example'>support@phooldelivery.example</a></p>
                    <p>&copy; 2024 Phool Delivery. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Reload settings from database (for admin updates)
     */
    public function reloadSettings() {
        $this->loadSMTPSettings();
        return [
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => substr($this->smtp_username, 0, 3) . '...', // Mask for logging
            'otp_expiry_minutes' => $this->otp_expiry_minutes
        ];
    }
}
?>