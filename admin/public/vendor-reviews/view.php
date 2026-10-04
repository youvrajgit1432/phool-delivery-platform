<?php
/**
 * Vendor Reviews Management
 * Admin can view and manage customer reviews for vendors
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;

if (!$vendor_id) {
    header('Location: ../vendors.php');
    exit;
}

// Fetch vendor details
try {
    $db = getDBConnection();
    
    // Get vendor info
    $vendor_query = "SELECT * FROM vendors WHERE id = ?";
    $vendor_stmt = $db->prepare($vendor_query);
    $vendor_stmt->execute([$vendor_id]);
    $vendor = $vendor_stmt->fetch();
    
    if (!$vendor) {
        header('Location: ../vendors.php?error=vendor_not_found');
        exit;
    }
    
    // Get reviews with pagination
    $page = $_GET['page'] ?? 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $reviews_query = "SELECT * FROM vendor_reviews 
                      WHERE vendor_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT ? OFFSET ?";
    $reviews_stmt = $db->prepare($reviews_query);
    $reviews_stmt->execute([$vendor_id, $per_page, $offset]);
    $reviews = $reviews_stmt->fetchAll();
    
    // Get total reviews count
    $count_query = "SELECT COUNT(*) as total FROM vendor_reviews WHERE vendor_id = ?";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([$vendor_id]);
    $count_result = $count_stmt->fetch();
    $total_reviews = $count_result['total'];
    $total_pages = ceil($total_reviews / $per_page);
    
    // Get review statistics
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        AVG(rating) as avg_rating,
                        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                    FROM vendor_reviews 
                    WHERE vendor_id = ?";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$vendor_id]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Database error occurred";
}

$page_title = 'Vendor Reviews';
$current_page = 'vendor-reviews';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .review-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #fff;
        }
        .rating-display {
            font-size: 18px;
            color: #ffc107;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .review-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card.five-star { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); }
        .stat-card.four-star { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.three-star { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                    <h1><i class="fas fa-star"></i> Vendor Reviews</h1>
                    <a href="../vendors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Vendors
                    </a>
                </div>

                <!-- Vendor Info -->
                <div class="alert alert-info mb-4">
                    <strong>Store:</strong> <?php echo htmlspecialchars($vendor['store_name']); ?> 
                    | <strong>Email:</strong> <?php echo htmlspecialchars($vendor['email']); ?>
                </div>

                <!-- Review Statistics -->
                <div class="review-stats">
                    <div class="stat-card">
                        <div class="h4"><?php echo round($stats['avg_rating'], 1); ?></div>
                        <small>Average Rating</small>
                    </div>
                    <div class="stat-card five-star">
                        <div class="h4"><?php echo $stats['five_star'] ?? 0; ?></div>
                        <small>5 Star</small>
                    </div>
                    <div class="stat-card four-star">
                        <div class="h4"><?php echo $stats['four_star'] ?? 0; ?></div>
                        <small>4 Star</small>
                    </div>
                    <div class="stat-card three-star">
                        <div class="h4"><?php echo $stats['three_star'] ?? 0; ?></div>
                        <small>3 Star</small>
                    </div>
                    <div class="stat-card">
                        <div class="h4"><?php echo $stats['total'] ?? 0; ?></div>
                        <small>Total Reviews</small>
                    </div>
                </div>

                <!-- Reviews List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($reviews)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No reviews found for this vendor.
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5><?php echo htmlspecialchars($review['title']); ?></h5>
                                                <span class="status-badge status-<?php echo $review['status']; ?>">
                                                    <?php echo ucfirst($review['status']); ?>
                                                </span>
                                            </div>
                                            <div class="rating-display mb-2">
                                                <?php 
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                    }
                                                ?>
                                                <span class="ms-2 text-muted"><?php echo $review['rating']; ?>/5</span>
                                            </div>
                                            <p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                            <small class="text-muted">
                                                By <strong><?php echo htmlspecialchars($review['customer_name']); ?></strong>
                                                on <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="btn-group-vertical w-100">
                                                <?php if ($review['status'] !== 'approved'): ?>
                                                    <button class="btn btn-sm btn-success approve-review" 
                                                            data-review-id="<?php echo $review['id']; ?>">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($review['status'] !== 'rejected'): ?>
                                                    <button class="btn btn-sm btn-danger reject-review" 
                                                            data-review-id="<?php echo $review['id']; ?>">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-secondary delete-review" 
                                                        data-review-id="<?php echo $review['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?vendor_id=<?php echo $vendor_id; ?>&page=1">First</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?vendor_id=<?php echo $vendor_id; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?vendor_id=<?php echo $vendor_id; ?>&page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?vendor_id=<?php echo $vendor_id; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?vendor_id=<?php echo $vendor_id; ?>&page=<?php echo $total_pages; ?>">Last</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Approve review
            document.querySelectorAll('.approve-review').forEach(btn => {
                btn.addEventListener('click', function() {
                    const reviewId = this.dataset.reviewId;
                    if (confirm('Approve this review?')) {
                        fetch('../../api/review-update-status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `review_id=${reviewId}&status=approved`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        });
                    }
                });
            });

            // Reject review
            document.querySelectorAll('.reject-review').forEach(btn => {
                btn.addEventListener('click', function() {
                    const reviewId = this.dataset.reviewId;
                    if (confirm('Reject this review?')) {
                        fetch('../../api/review-update-status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `review_id=${reviewId}&status=rejected`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        });
                    }
                });
            });

            // Delete review
            document.querySelectorAll('.delete-review').forEach(btn => {
                btn.addEventListener('click', function() {
                    const reviewId = this.dataset.reviewId;
                    if (confirm('Delete this review permanently?')) {
                        fetch('../../api/review-delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `review_id=${reviewId}`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
