<?php
// public/actions/verify-customer.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database and services
$pdo = getDBConnection();
$messageService = getMessageService();

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid customer ID.";
    header("Location: ../customer-verification.php");
    exit;
}

$customer_id = intval($_GET['id']);
$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '../customer-verification.php';

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: ../customer-verification.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_method = $_POST['verification_method'];
    $notes = trim($_POST['notes']);
    
    try {
        $stmt = $pdo->prepare("UPDATE customers SET verification_status = 'verified', verified_at = NOW(), verification_method = ?, verification_notes = ?, verified_by = ? WHERE id = ?");
        
        if ($stmt->execute([$verification_method, $notes, $_SESSION['admin_id'], $customer_id])) {
            // Create verification success message using the message service
            if (method_exists($messageService, 'createVerificationMessage')) {
                $messageService->createVerificationMessage($customer_id, 'verified');
            }
            
            $_SESSION['success_message'] = "Customer verified successfully!";
            header("Location: $redirect_url");
            exit;
        } else {
            $error = "Failed to verify customer.";
        }
    } catch (PDOException $e) {
        error_log("Error verifying customer: " . $e->getMessage());
        $error = "Database error occurred while verifying customer.";
    }
}

// Set page title
$page_title = "Verify Customer - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Verify Customer</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-secondary">Back to Verification Center</a>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Customer Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $customer['customer_type'])); ?></p>
                            <p><strong>Registered:</strong> <?php echo date('M d, Y', strtotime($customer['created_at'])); ?></p>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="verification_method" class="form-label">Verification Method *</label>
                            <select class="form-select" id="verification_method" name="verification_method" required>
                                <option value="">Select verification method</option>
                                <option value="phone_verification">Phone Verification</option>
                                <option value="email_verification">Email Verification</option>
                                <option value="manual_verification">Manual Verification</option>
                                <option value="document_verification">Document Verification</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Verification Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Add details about the verification process, any documents reviewed, or other relevant information..."></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-success">Verify Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>