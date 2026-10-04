<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Get event ID from URL
$event_id = $_GET['id'] ?? null;

if (!$event_id) {
    $_SESSION['error_message'] = "Event ID is required!";
    header("Location: ../events_offers.php");
    exit;
}

// Fetch event data for confirmation message
try {
    $stmt = $pdo->prepare("SELECT * FROM special_events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        $_SESSION['error_message'] = "Event not found!";
        header("Location: ../events_offers.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching event: " . $e->getMessage();
    header("Location: ../events_offers.php");
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM special_events WHERE id = ?");
        
        if ($stmt->execute([$event_id])) {
            $_SESSION['success_message'] = "Event '{$event['event_name']}' deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete event.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting event: " . $e->getMessage();
    }
    
    header("Location: ../events_offers.php");
    exit;
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 text-danger">Delete Event</h1>
    <a href="../events_offers.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Events & Offers
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6 class="alert-heading">Warning!</h6>
                    <p class="mb-0">You are about to delete an event. This action cannot be undone.</p>
                </div>
                
                <div class="event-info p-3 border rounded bg-light">
                    <h6>Event Details:</h6>
                    <p><strong>Event Name:</strong> <?php echo htmlspecialchars($event['event_name']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($event['event_description']); ?></p>
                    <p><strong>Discount:</strong> <?php echo $event['discount_percentage']; ?>% OFF</p>
                    <p><strong>Duration:</strong> 
                        <?php echo date('M j, Y', strtotime($event['start_date'])); ?> 
                        to 
                        <?php echo date('M j, Y', strtotime($event['end_date'])); ?>
                    </p>
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $event['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $event['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                    <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($event['created_at'])); ?></p>
                </div>
                
                <form method="POST" class="mt-4">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-trash me-2"></i>Confirm Delete
                        </button>
                        <a href="../events_offers.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include '../../app/views/layouts/footer.php';
?>