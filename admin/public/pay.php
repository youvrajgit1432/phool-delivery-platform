<?php
// public/pay.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Fetch active wallet QRs
$wallets = $pdo->query("SELECT * FROM wallet_qrs WHERE status = 'active' ORDER BY wallet_type")->fetchAll();

$page_title = "Payment Methods";
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Payment Methods</h1>
    <a href="qr/add.php" class="btn btn-sm btn-primary">
        <i class="fas fa-plus me-1"></i> Add New Payment Method
    </a>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <?php if (!empty($wallets)): ?>
        <?php foreach ($wallets as $wallet): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo strtoupper($wallet['wallet_type']); ?></h5>
                    <div class="btn-group">
                        <a href="qr/edit.php?id=<?php echo $wallet['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="qr/delete.php?id=<?php echo $wallet['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this payment method?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($wallet['qr_image'])): ?>
                    <img src="<?php echo getWalletQrUrl($wallet['qr_image']); ?>" 
                         class="img-fluid rounded mb-3" 
                         alt="<?php echo $wallet['wallet_type']; ?> QR Code"
                         style="max-height: 250px; object-fit: contain;">
                    <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                        <i class="fas fa-qrcode fa-5x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <p class="card-text">Scan the QR code to make payment with <?php echo ucfirst($wallet['wallet_type']); ?></p>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <small class="text-muted">Added on <?php echo date('M d, Y', strtotime($wallet['created_at'])); ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-qrcode fa-4x text-muted mb-3"></i>
                    <h3>No Payment Methods Available</h3>
                    <p class="text-muted">You haven't added any payment methods yet.</p>
                    <a href="qr/add.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus me-1"></i> Add Your First Payment Method
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($wallets)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Payment Instructions</h5>
                <ol>
                    <li>Open your mobile banking or wallet app</li>
                    <li>Select the "Scan QR Code" option</li>
                    <li>Point your camera at the QR code above</li>
                    <li>Enter the payment amount and confirm the transaction</li>
                    <li>Take a screenshot of the payment confirmation for your records</li>
                </ol>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please note that payments may take up to 24 hours to process.
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
include '../app/views/layouts/footer.php';
?>