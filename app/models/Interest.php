<?php
class Interest {
    private $conn;
    private $table_name = "customer_interests";

    public $id;
    public $customer_id;
    public $product_type;
    public $delivery_address;
    public $quantity_needed;
    public $event_type;
    public $event_date;
    public $special_requirements;
    public $status;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (customer_id, product_type, delivery_address, quantity_needed, event_type, event_date, special_requirements, status, created_at) 
                 VALUES (:customer_id, :product_type, :delivery_address, :quantity_needed, :event_type, :event_date, :special_requirements, 'pending', NOW())";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->customer_id = htmlspecialchars(strip_tags($this->customer_id));
        $this->product_type = htmlspecialchars(strip_tags($this->product_type));
        $this->delivery_address = htmlspecialchars(strip_tags($this->delivery_address));
        $this->quantity_needed = htmlspecialchars(strip_tags($this->quantity_needed));
        $this->event_type = htmlspecialchars(strip_tags($this->event_type));
        $this->event_date = htmlspecialchars(strip_tags($this->event_date));
        $this->special_requirements = htmlspecialchars(strip_tags($this->special_requirements));

        // Bind values
        $stmt->bindParam(":customer_id", $this->customer_id);
        $stmt->bindParam(":product_type", $this->product_type);
        $stmt->bindParam(":delivery_address", $this->delivery_address);
        $stmt->bindParam(":quantity_needed", $this->quantity_needed);
        $stmt->bindParam(":event_type", $this->event_type);
        $stmt->bindParam(":event_date", $this->event_date);
        $stmt->bindParam(":special_requirements", $this->special_requirements);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getInterestsByArea($area) {
        $query = "SELECT product_type, COUNT(*) as demand_count 
                 FROM " . $this->table_name . " 
                 WHERE delivery_address LIKE :area AND status = 'pending' 
                 GROUP BY product_type 
                 ORDER BY demand_count DESC";

        $stmt = $this->conn->prepare($query);
        $area = "%" . $area . "%";
        $stmt->bindParam(":area", $area);
        $stmt->execute();

        return $stmt;
    }

    public function getPopularDemandAreas($limit = 5) {
        $query = "SELECT delivery_address, COUNT(*) as demand_count 
                 FROM " . $this->table_name . " 
                 WHERE status = 'pending' 
                 GROUP BY delivery_address 
                 ORDER BY demand_count DESC 
                 LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }
}
?>