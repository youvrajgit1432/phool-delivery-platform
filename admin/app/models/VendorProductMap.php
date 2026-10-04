<?php
/**
 * VendorProductMap Model - Handles vendor-product mapping operations
 * Used by admin panel to manage which vendors supply which products
 */
class VendorProductMap
{
    private $conn;

    /**
     * Constructor
     * @param PDO $conn Database connection
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a new vendor-product mapping
     * @param array $data Mapping data (vendor_id, product_id, vendor_price, vendor_stock, status)
     * @return int|false The inserted ID or false on failure
     */
    public function create($data = [])
    {
        try {
            $query = "INSERT INTO vendor_product_map 
                     (vendor_id, product_id, vendor_price, vendor_stock, status, created_at, updated_at)
                     VALUES (:vendor_id, :product_id, :vendor_price, :vendor_stock, :status, NOW(), NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':vendor_id', $data['vendor_id'], PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $data['product_id'], PDO::PARAM_INT);
            $stmt->bindParam(':vendor_price', $data['vendor_price']);
            $stmt->bindParam(':vendor_stock', $data['vendor_stock'], PDO::PARAM_INT);
            $stmt->bindParam(':status', $data['status']);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("[VendorProductMap] Create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a mapping by ID
     * @param int $id Mapping ID
     * @return array|false
     */
    public function getById($id)
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM vendor_product_map WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[VendorProductMap] GetById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all mappings for a product
     * @param int $productId Product ID
     * @return array
     */
    public function getByProductId($productId)
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT vpm.*, v.store_name, v.average_rating, v.status as vendor_status
                 FROM vendor_product_map vpm
                 JOIN vendors v ON vpm.vendor_id = v.id
                 WHERE vpm.product_id = ?
                 ORDER BY vpm.vendor_price ASC"
            );
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[VendorProductMap] GetByProductId error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update a mapping
     * @param int $id Mapping ID
     * @param array $data Data to update
     * @return bool
     */
    public function update($id, $data = [])
    {
        try {
            $fields = [];
            $params = [];

            foreach ($data as $key => $value) {
                if (in_array($key, ['vendor_price', 'vendor_stock', 'is_available', 'preparation_time', 'status', 'minimum_order_quantity', 'notes'])) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (empty($fields)) {
                return false;
            }

            $fields[] = "updated_at = NOW()";
            $params[':id'] = $id;

            $query = "UPDATE vendor_product_map SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("[VendorProductMap] Update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a mapping (hard delete)
     * @param int $id Mapping ID
     * @param int $productId Product ID (for verification)
     * @return bool
     */
    public function delete($id, $productId = null)
    {
        try {
            if ($productId) {
                $stmt = $this->conn->prepare("DELETE FROM vendor_product_map WHERE id = ? AND product_id = ?");
                return $stmt->execute([$id, $productId]);
            } else {
                $stmt = $this->conn->prepare("DELETE FROM vendor_product_map WHERE id = ?");
                return $stmt->execute([$id]);
            }
        } catch (PDOException $e) {
            error_log("[VendorProductMap] Delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a vendor already supplies a product
     * @param int $vendorId Vendor ID
     * @param int $productId Product ID
     * @return bool
     */
    public function exists($vendorId, $productId)
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) as count FROM vendor_product_map WHERE vendor_id = ? AND product_id = ?"
            );
            $stmt->execute([$vendorId, $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("[VendorProductMap] Exists error: " . $e->getMessage());
            return false;
        }
    }
}
