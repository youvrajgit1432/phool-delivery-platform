<?php
namespace Phool\DeliveryPanel\Controllers;

use Phool\DeliveryPanel\Models\Ticket;

// Ensure models are properly loaded with their dependencies
require_once dirname(__DIR__) . '/models/BaseModel.php';
require_once dirname(__DIR__) . '/models/Ticket.php';

class SupportController {
    private $ticketModel;
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo === null) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        if (!$pdo) {
            throw new \Exception('Database connection not available');
        }
        $this->pdo = $pdo;
        $this->ticketModel = new Ticket($this->pdo);
    }

    /**
     * List all support tickets for the logged-in rider
     */
    public function tickets() {
        if (!isset($_SESSION['rider_id'])) {
            return ['error' => 'Unauthorized'];
        }

        $riderId = $_SESSION['rider_id'];
        $status = $_GET['status'] ?? null;

        $tickets = $this->ticketModel->getByRiderId($riderId, $status);
        $stats = $this->ticketModel->getStats($riderId);

        return [
            'tickets' => $tickets,
            'stats' => $stats,
            'categories' => Ticket::getCategories(),
            'statuses' => Ticket::getStatuses(),
            'priorities' => Ticket::getPriorities()
        ];
    }

    /**
     * Create a new support ticket
     */
    public function createTicket() {
        if (!isset($_SESSION['rider_id'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $riderId = $_SESSION['rider_id'];
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'other');
        $priority = trim($_POST['priority'] ?? 'medium');

        // Validate input
        if (empty($subject) || empty($description)) {
            return ['success' => false, 'message' => 'Subject and description are required'];
        }

        if (strlen($subject) > 255) {
            return ['success' => false, 'message' => 'Subject cannot exceed 255 characters'];
        }

        if (strlen($description) > 5000) {
            return ['success' => false, 'message' => 'Description cannot exceed 5000 characters'];
        }

        try {
            $created = $this->ticketModel->create($riderId, $subject, $description, $category, $priority);
            
            if ($created) {
                return [
                    'success' => true,
                    'message' => 'Support ticket created successfully',
                    'ticketNumber' => 'TKT-' . date('Y') . '-' . rand(1000, 9999)
                ];
            }

            return ['success' => false, 'message' => 'Failed to create ticket'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get ticket details
     */
    public function viewTicket($ticketId) {
        if (!isset($_SESSION['rider_id'])) {
            return ['error' => 'Unauthorized'];
        }

        $riderId = $_SESSION['rider_id'];
        $ticket = $this->ticketModel->getById($ticketId, $riderId);

        if (!$ticket) {
            return ['error' => 'Ticket not found'];
        }

        return ['ticket' => $ticket];
    }

    /**
     * Reply to support ticket
     */
    public function replyTicket($ticketId) {
        if (!isset($_SESSION['rider_id'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $riderId = $_SESSION['rider_id'];
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            return ['success' => false, 'message' => 'Message cannot be empty'];
        }

        if (strlen($message) > 5000) {
            return ['success' => false, 'message' => 'Message cannot exceed 5000 characters'];
        }

        try {
            $ticket = $this->ticketModel->getById($ticketId, $riderId);
            
            if (!$ticket) {
                return ['success' => false, 'message' => 'Ticket not found'];
            }

            // Append message to response_message field (simple approach)
            $existingMessages = $ticket->response_message ? $ticket->response_message . "\n---\n" : "";
            $timestamp = date('M d, Y H:i A');
            $newMessage = $existingMessages . "You ($timestamp):\n" . htmlspecialchars($message);

            $updated = $this->ticketModel->addMessage($ticketId, $riderId, $newMessage);

            if ($updated) {
                return [
                    'success' => true,
                    'message' => 'Reply sent successfully'
                ];
            }

            return ['success' => false, 'message' => 'Failed to send reply'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Close ticket
     */
    public function closeTicket($ticketId) {
        if (!isset($_SESSION['rider_id'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $riderId = $_SESSION['rider_id'];

        try {
            $updated = $this->ticketModel->updateStatus($ticketId, $riderId, 'closed');

            if ($updated) {
                return ['success' => true, 'message' => 'Ticket closed successfully'];
            }

            return ['success' => false, 'message' => 'Failed to close ticket'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
