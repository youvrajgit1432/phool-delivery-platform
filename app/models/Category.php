<?php
// app/models/Category.php
class Category {
    private $conn;
    private $table_name = "categories";
    
    public $id;
    public $name_en;
    public $name_ne;
    public $description_en;
    public $description_ne;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read all categories with language support
    public function read($language = 'en') {
        // Validate language parameter
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        
        $query = "SELECT id, {$name_field} as name, {$description_field} as description, 
                         status, created_at 
                 FROM " . $this->table_name . " 
                 WHERE status = 'active' 
                 ORDER BY name ASC";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Read single category
    public function readOne() {
        // Validate ID
        if (!is_numeric($this->id) || $this->id <= 0) {
            return false;
        }
        
        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE id = ? LIMIT 0,1";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->name_en = $row['name_en'];
            $this->name_ne = $row['name_ne'];
            $this->description_en = $row['description_en'];
            $this->description_ne = $row['description_ne'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Get localized category data
    public function getLocalizedData($language = 'en') {
        // Validate language parameter
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        return [
            'id' => (int)$this->id,
            'name' => $language === 'ne' ? $this->name_ne : $this->name_en,
            'description' => $language === 'ne' ? $this->description_ne : $this->description_en,
            'status' => $this->status,
            'created_at' => $this->created_at
        ];
    }

    // Security: Input validation helper method
    private function validateInput($input, $type = 'string', $max_length = 255) {
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            case 'string':
                $input = trim($input);
                return strlen($input) <= $max_length ? htmlspecialchars($input, ENT_QUOTES, 'UTF-8') : false;
            case 'status':
                return in_array($input, ['active', 'inactive']) ? $input : false;
            default:
                return false;
        }
    }

    // Security: Create category with validation
    public function create() {
        // Validate inputs
        $name_en = $this->validateInput($this->name_en, 'string', 255);
        $name_ne = $this->validateInput($this->name_ne, 'string', 255);
        $description_en = $this->validateInput($this->description_en, 'string', 1000);
        $description_ne = $this->validateInput($this->description_ne, 'string', 1000);
        $status = $this->validateInput($this->status, 'status');

        if (!$name_en || !$name_ne || !$status) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " 
                 (name_en, name_ne, description_en, description_ne, status, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())";
                 
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $name_en,
            $name_ne,
            $description_en ?: '',
            $description_ne ?: '',
            $status
        ]);
    }

    // Security: Update category with validation
    public function update() {
        // Validate ID
        if (!is_numeric($this->id) || $this->id <= 0) {
            return false;
        }

        // Validate inputs
        $name_en = $this->validateInput($this->name_en, 'string', 255);
        $name_ne = $this->validateInput($this->name_ne, 'string', 255);
        $description_en = $this->validateInput($this->description_en, 'string', 1000);
        $description_ne = $this->validateInput($this->description_ne, 'string', 1000);
        $status = $this->validateInput($this->status, 'status');

        if (!$name_en || !$name_ne || !$status) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " 
                 SET name_en = ?, name_ne = ?, description_en = ?, description_ne = ?, status = ?
                 WHERE id = ?";
                 
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $name_en,
            $name_ne,
            $description_en ?: '',
            $description_ne ?: '',
            $status,
            $this->id
        ]);
    }

    // Security: Delete category with validation
    public function delete() {
        // Validate ID
        if (!is_numeric($this->id) || $this->id <= 0) {
            return false;
        }

        // Use transaction for data consistency
        try {
            $this->conn->beginTransaction();

            $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
            $result = $stmt->execute();

            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Security: Check if category exists and is active
    public function exists() {
        if (!is_numeric($this->id) || $this->id <= 0) {
            return false;
        }

        $query = "SELECT id FROM " . $this->table_name . " 
                 WHERE id = ? AND status = 'active'";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
}
?>