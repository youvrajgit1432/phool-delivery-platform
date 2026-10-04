
<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Add New Event";

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
                INSERT INTO special_events 
                (event_name, event_description, discount_percentage, start_date, end_date, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$event_name, $event_description, $discount_percentage, $start_date, $end_date, $is_active])) {
                $_SESSION['success_message'] = "Event '$event_name' added successfully!";
                header("Location: ../events_offers.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to add event.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error adding event: " . $e->getMessage();
        }
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Event</h1>
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
                <h5 class="card-title mb-0">Event Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addEventForm">
                    <div class="mb-3">
                        <label for="event_name" class="form-label">Event Name *</label>
                        <input type="text" class="form-control" id="event_name" name="event_name" 
                               placeholder="e.g., Dashain Festival, Tihar Celebration" 
                               value="<?php echo isset($_POST['event_name']) ? htmlspecialchars($_POST['event_name']) : ''; ?>" 
                               required maxlength="255">
                        <div class="form-text">Enter a descriptive name for the event.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_description" class="form-label">Event Description</label>
                        <textarea class="form-control" id="event_description" name="event_description" 
                                  rows="3" placeholder="Brief description of the event..."><?php echo isset($_POST['event_description']) ? htmlspecialchars($_POST['event_description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="discount_percentage" class="form-label">Discount Percentage *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" 
                                           min="0" max="100" step="0.01" 
                                           value="<?php echo isset($_POST['discount_percentage']) ? $_POST['discount_percentage'] : '10'; ?>" 
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
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
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
                                       value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d'); ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('+7 days')); ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../events_offers.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addEventForm');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    startDate.min = today;
    endDate.min = today;
    
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