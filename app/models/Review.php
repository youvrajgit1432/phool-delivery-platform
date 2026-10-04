<?php
class Review {
    private $conn;
    private $table_name = "reviews";
    
    public $id;
    public $user_id;
    public $product_id;
    public $rating;
    public $title;
    public $content;
    public $status;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (user_id, product_id, rating, title, content, status, created_at, updated_at) 
                 VALUES (:user_id, :product_id, :rating, :title, :content, :status, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->product_id = htmlspecialchars(strip_tags($this->product_id));
        $this->rating = htmlspecialchars(strip_tags($this->rating));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->status = 'pending';
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":product_id", $this->product_id);
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":content", $this->content);
        $stmt->bindParam(":status", $this->status);
        
        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function readAll($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT r.*, p.name_en as product_name_en, p.name_ne as product_name_ne, 
                         c.name as customer_name, c.profile_image
                 FROM " . $this->table_name . " r
                 LEFT JOIN products p ON r.product_id = p.id
                 LEFT JOIN customers c ON r.user_id = c.id
                 WHERE r.status = 'approved'
                 ORDER BY r.created_at DESC
                 LIMIT :limit OFFSET :offset";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readByUser($user_id, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT r.*, p.name_en as product_name_en, p.name_ne as product_name_ne,
                         p.primary_image, p.slug_en, p.slug_ne
                 FROM " . $this->table_name . " r
                 LEFT JOIN products p ON r.product_id = p.id
                 WHERE r.user_id = :user_id
                 ORDER BY r.created_at DESC
                 LIMIT :limit OFFSET :offset";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readByProduct($product_id, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT r.*, c.name as customer_name, c.profile_image
                 FROM " . $this->table_name . " r
                 LEFT JOIN customers c ON r.user_id = c.id
                 WHERE r.product_id = :product_id AND r.status = 'approved'
                 ORDER BY r.created_at DESC
                 LIMIT :limit OFFSET :offset";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats($product_id = null) {
        if ($product_id) {
            $query = "SELECT 
                        COUNT(*) as total_reviews,
                        AVG(rating) as average_rating,
                        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
                     FROM " . $this->table_name . " 
                     WHERE product_id = :product_id AND status = 'approved'";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':product_id', $product_id);
        } else {
            $query = "SELECT 
                        COUNT(*) as total_reviews,
                        AVG(rating) as average_rating
                     FROM " . $this->table_name . " 
                     WHERE status = 'approved'";
                     
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [0, 0, 0, 0, 0]
            ];
        }
        
        return [
            'total_reviews' => (int)$stats['total_reviews'],
            'average_rating' => $stats['average_rating'] ? round((float)$stats['average_rating'], 1) : 0,
            'rating_distribution' => $product_id ? [
                (int)$stats['rating_5'],
                (int)$stats['rating_4'],
                (int)$stats['rating_3'],
                (int)$stats['rating_2'],
                (int)$stats['rating_1']
            ] : []
        ];
    }

    public function hasUserReviewed($user_id, $product_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                 WHERE user_id = :user_id AND product_id = :product_id";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    public function getTotalCount($user_id = null, $product_id = null) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'approved'";
        
        if ($user_id) {
            $query .= " AND user_id = :user_id";
        }
        
        if ($product_id) {
            $query .= " AND product_id = :product_id";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        
        if ($product_id) {
            $stmt->bindParam(':product_id', $product_id);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    }
}
?>