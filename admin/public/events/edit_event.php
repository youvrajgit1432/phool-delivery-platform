<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Edit Event";

// Get event ID from URL
$event_id = $_GET['id'] ?? null;

if (!$event_id) {
    $_SESSION['error_message'] = "Event ID is required!";
    header("Location: ../events_offers.php");
    exit;
}

// Fetch event data
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = trim($_POST['event_name']);
    $event_description = trim($_POST['event_description']);
    $discount_percentage = $_POST['discount_percentage'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    if (empty($event_name)) {
        $_SESSION['error_message'] = "Event name is required!";
    } elseif ($discount_percentage < 0 || $discount_percentage > 100) {
        $_SESSION['error_message'] = "Discount percentage must be between 0 and 100!";
    } elseif ($start_date > $end_date) {
        $_SESSION['error_message'] = "End date cannot be before start date!";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE special_events 
                SET event_name = ?, event_description = ?, discount_percentage = ?, 
                    start_date = ?, end_date = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$event_name, $event_description, $discount_percentage, $start_date, $end_date, $is_active, $event_id])) {
                $_SESSION['success_message'] = "Event '$event_name' updated successfully!";
                header("Location: ../events_offers.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to update event.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating event: " . $e->getMessage();
        }
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Event</h1>
    <a href="../events_offers.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Events & Offers
    </a>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Event Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editEventForm">
                    <div class="mb-3">
                        <label for="event_name" class="form-label">Event Name *</label>
                        <input type="text" class="form-control" id="event_name" name="event_name" 
                               placeholder="e.g., Dashain Festival, Tihar Celebration" 
                               value="<?php echo htmlspecialchars($event['event_name']); ?>" 
                               required maxlength="255">
                        <div class="form-text">Enter a descriptive name for the event.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_description" class="form-label">Event Description</label>
                        <textarea class="form-control" id="event_description" name="event_description" 
                                  rows="3" placeholder="Brief description of the event..."><?php echo htmlspecialchars($event['event_description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="discount_percentage" class="form-label">Discount Percentage *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" 
                                           min="0" max="100" step="0.01" 
                                           value="<?php echo $event['discount_percentage']; ?>" 
                                           required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Discount percentage applied during this event.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                        <?php echo $event['is_active'] == 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                                <div class="form-text">Enable or disable this event.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $event['start_date']; ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $event['end_date']; ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../events_offers.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Event Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Event Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?></p>
                        <?php if ($event['updated_at'] && $event['updated_at'] != $event['created_at']): ?>
                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($event['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php
                        $is_current = date('Y-m-d') >= $event['start_date'] && date('Y-m-d') <= $event['end_date'];
                        $days_remaining = $is_current ? ceil((strtotime($event['end_date']) - time()) / (60 * 60 * 24)) : 0;
                        ?>
                        <p><strong>Current Status:</strong> 
                            <span class="badge <?php echo $is_current ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $is_current ? 'Active Now' : 'Not Active'; ?>
                            </span>
                        </p>
                        <?php if ($is_current): ?>
                        <p><strong>Days Remaining:</strong> <?php echo $days_remaining; ?> days</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editEventForm');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    form.addEventListener('submit', function(e) {
        const eventName = document.getElementById('event_name').value.trim();
        const discount = parseFloat(document.getElementById('discount_percentage').value);
        
        if (!eventName) {
            e.preventDefault();
            alert('Please enter an event name.');
            return false;
        }
        
        if (discount < 0 || discount > 100) {
            e.preventDefault();
            alert('Discount percentage must be between 0 and 100.');
            return false;
        }
        
        if (startDate.value > endDate.value) {
            e.preventDefault();
            alert('End date cannot be before start date.');
            return false;
        }
    });
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>