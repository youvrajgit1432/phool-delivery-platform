<?php
// vendor-panel/app/services/VendorService.php

namespace App\Services;

use PDO;
use Exception;

/**
 * VendorService - Handles vendor operations
 * Replaces stored procedure: update_vendor_ratings
 */
class VendorService
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Update vendor ratings based on approved reviews
     * Replaces: update_vendor_ratings
     */
    public function updateVendorRatings($vendorId)
    {
        try {
            $this->db->beginTransaction();

            // Get rating statistics from reviews
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_reviews,
                    AVG(rating) as average_rating,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star
                FROM vendor_reviews
                WHERE vendor_id = ? AND status = 'approved'
            ");
            $stmt->execute([$vendorId]);
            $ratings = $stmt->fetch(PDO::FETCH_ASSOC);

            // Prepare data with defaults for nulls
            $totalReviews = $ratings['total_reviews'] ?? 0;
            $averageRating = $ratings['average_rating'] ?? 0;
            $oneStar = $ratings['one_star'] ?? 0;
            $twoStar = $ratings['two_star'] ?? 0;
            $threeStar = $ratings['three_star'] ?? 0;
            $fourStar = $ratings['four_star'] ?? 0;
            $fiveStar = $ratings['five_star'] ?? 0;

            // Check if vendor_ratings record exists
            $checkStmt = $this->db->prepare("
                SELECT id FROM vendor_ratings WHERE vendor_id = ?
            ");
            $checkStmt->execute([$vendorId]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                // Update existing record
                $updateStmt = $this->db->prepare("
                    UPDATE vendor_ratings
                    SET 
                        total_reviews = ?,
                        average_rating = ?,
                        one_star = ?,
                        two_star = ?,
                        three_star = ?,
                        four_star = ?,
                        five_star = ?,
                        updated_at = NOW()
                    WHERE vendor_id = ?
                ");
                $updateStmt->execute([
                    $totalReviews,
                    $averageRating,
                    $oneStar,
                    $twoStar,
                    $threeStar,
                    $fourStar,
                    $fiveStar,
                    $vendorId
                ]);
            } else {
                // Insert new record
                $insertStmt = $this->db->prepare("
                    INSERT INTO vendor_ratings (
                        vendor_id,
                        total_reviews,
                        average_rating,
                        one_star,
                        two_star,
                        three_star,
                        four_star,
                        five_star,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $vendorId,
                    $totalReviews,
                    $averageRating,
                    $oneStar,
                    $twoStar,
                    $threeStar,
                    $fourStar,
                    $fiveStar
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Vendor ratings updated successfully',
                'data' => [
                    'total_reviews' => $totalReviews,
                    'average_rating' => round($averageRating, 2),
                    'star_distribution' => [
                        '1_star' => $oneStar,
                        '2_star' => $twoStar,
                        '3_star' => $threeStar,
                        '4_star' => $fourStar,
                        '5_star' => $fiveStar
                    ]
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error updating vendor ratings: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get vendor ratings
     */
    public function getVendorRatings($vendorId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM vendor_ratings
                WHERE vendor_id = ?
            ");
            $stmt->execute([$vendorId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get vendor details with ratings
     */
    public function getVendorWithRatings($vendorId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    v.*,
                    vr.total_reviews,
                    vr.average_rating,
                    vr.one_star,
                    vr.two_star,
                    vr.three_star,
                    vr.four_star,
                    vr.five_star
                FROM vendors v
                LEFT JOIN vendor_ratings vr ON v.id = vr.vendor_id
                WHERE v.id = ?
            ");
            $stmt->execute([$vendorId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Trigger rating update when a review is approved
     * Call this when a review status changes to 'approved'
     */
    public function onReviewApproved($vendorId)
    {
        return $this->updateVendorRatings($vendorId);
    }
}
