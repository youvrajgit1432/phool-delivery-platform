<?php
class Media {
    private $conn;
    private $table_name = "media_items";

    public $id;
    public $title_en;
    public $title_ne;
    public $description_en;
    public $description_ne;
    public $file_path;
    public $thumbnail_path;
    public $custom_thumbnail;
    public $media_type;
    public $file_size;
    public $duration;
    public $category_id;
    public $views_count;
    public $likes_count;
    public $status;
    public $uploaded_by;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Input validation helper
    private function validateInput($input, $type = 'string', $max_length = 255) {
        if ($input === null || $input === '') {
            return false;
        }

        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT) !== false && $input > 0;
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) !== false && $input >= 0;
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            case 'string':
            default:
                $clean_input = trim(strip_tags($input));
                return strlen($clean_input) <= $max_length && !empty($clean_input);
        }
    }

    // Read all media items with optional filters
    public function read($filters = []) {
        // Validate filters
        $valid_filters = [];
        
        if (!empty($filters['category_id']) && $this->validateInput($filters['category_id'], 'int')) {
            $valid_filters['category_id'] = (int)$filters['category_id'];
        }
        
        if (!empty($filters['media_type']) && in_array($filters['media_type'], ['image', 'video'])) {
            $valid_filters['media_type'] = $filters['media_type'];
        }
        
        if (!empty($filters['search']) && $this->validateInput($filters['search'], 'string', 100)) {
            $valid_filters['search'] = $filters['search'];
        }
        
        if (!empty($filters['limit']) && $this->validateInput($filters['limit'], 'int')) {
            $valid_filters['limit'] = min((int)$filters['limit'], 100); // Prevent excessive limits
            if (!empty($filters['offset']) && $this->validateInput($filters['offset'], 'int')) {
                $valid_filters['offset'] = (int)$filters['offset'];
            }
        }

        $query = "SELECT mi.*, mc.name_en as category_name_en, mc.name_ne as category_name_ne 
                  FROM " . $this->table_name . " mi 
                  LEFT JOIN media_categories mc ON mi.category_id = mc.id 
                  WHERE mi.status = 'active'";

        // Add validated filters
        if (!empty($valid_filters['category_id'])) {
            $query .= " AND mi.category_id = :category_id";
        }
        if (!empty($valid_filters['media_type'])) {
            $query .= " AND mi.media_type = :media_type";
        }
        if (!empty($valid_filters['search'])) {
            $query .= " AND (mi.title_en LIKE :search OR mi.title_ne LIKE :search OR mi.description_en LIKE :search OR mi.description_ne LIKE :search)";
        }

        // Add ordering
        $query .= " ORDER BY mi.created_at DESC";

        // Add pagination
        if (!empty($valid_filters['limit'])) {
            $query .= " LIMIT :limit";
            if (!empty($valid_filters['offset'])) {
                $query .= " OFFSET :offset";
            }
        }

        try {
            $stmt = $this->conn->prepare($query);

            // Bind filters
            if (!empty($valid_filters['category_id'])) {
                $stmt->bindValue(':category_id', $valid_filters['category_id'], PDO::PARAM_INT);
            }
            if (!empty($valid_filters['media_type'])) {
                $stmt->bindValue(':media_type', $valid_filters['media_type']);
            }
            if (!empty($valid_filters['search'])) {
                $search_term = '%' . $valid_filters['search'] . '%';
                $stmt->bindValue(':search', $search_term);
            }
            if (!empty($valid_filters['limit'])) {
                $stmt->bindValue(':limit', $valid_filters['limit'], PDO::PARAM_INT);
                if (!empty($valid_filters['offset'])) {
                    $stmt->bindValue(':offset', $valid_filters['offset'], PDO::PARAM_INT);
                }
            }

            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            error_log("Media read error: " . $e->getMessage());
            return false;
        }
    }

    // Get media by ID with validation
    public function readOne() {
        if (!$this->validateInput($this->id, 'int')) {
            return false;
        }

        $query = "SELECT mi.*, mc.name_en as category_name_en, mc.name_ne as category_name_ne 
                  FROM " . $this->table_name . " mi 
                  LEFT JOIN media_categories mc ON mi.category_id = mc.id 
                  WHERE mi.id = ? AND mi.status = 'active' 
                  LIMIT 0,1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $this->title_en = $row['title_en'];
                $this->title_ne = $row['title_ne'];
                $this->description_en = $row['description_en'];
                $this->description_ne = $row['description_ne'];
                $this->file_path = $row['file_path'];
                $this->thumbnail_path = $row['thumbnail_path'];
                $this->custom_thumbnail = $row['custom_thumbnail'];
                $this->media_type = $row['media_type'];
                $this->file_size = $row['file_size'];
                $this->duration = $row['duration'];
                $this->category_id = $row['category_id'];
                $this->category_name_en = $row['category_name_en'];
                $this->category_name_ne = $row['category_name_ne'];
                $this->views_count = $row['views_count'];
                $this->likes_count = $row['likes_count'];
                $this->uploaded_by = $row['uploaded_by'];
                $this->created_at = $row['created_at'];
                $this->updated_at = $row['updated_at'];
                
                // Increment view count
                $this->incrementViews();
                
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Media readOne error for ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    // Increment view count with transaction safety
    private function incrementViews() {
        $query = "UPDATE " . $this->table_name . " 
                  SET views_count = views_count + 1 
                  WHERE id = ?";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Media incrementViews error: " . $e->getMessage());
        }
    }

    // Get all categories
    public function getCategories() {
        $query = "SELECT * FROM media_categories WHERE status = 'active' ORDER BY name_en";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            error_log("Media getCategories error: " . $e->getMessage());
            return false;
        }
    }

    // Get popular media (most viewed)
    public function getPopularMedia($limit = 6) {
        if (!$this->validateInput($limit, 'int')) {
            $limit = 6;
        }
        $limit = min($limit, 20); // Prevent excessive limits

        $query = "SELECT mi.*, mc.name_en as category_name_en, mc.name_ne as category_name_ne 
                  FROM " . $this->table_name . " mi 
                  LEFT JOIN media_categories mc ON mi.category_id = mc.id 
                  WHERE mi.status = 'active' 
                  ORDER BY mi.views_count DESC 
                  LIMIT :limit";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            error_log("Media getPopularMedia error: " . $e->getMessage());
            return false;
        }
    }
}
?>