<?php
class Customer {
    private $conn;
    private $table_name = "customers";

    public $id;
    public $name;
    public $email;
    public $phone;
    public $password;
    public $registration_type;
    public $address;
    public $city;
    public $street;
    public $customer_type = 'normal';
    public $status = 'active';
    public $verification_status = 'pending';
    public $loyalty_points = 0;
    public $last_order_at;
    public $created_at;
    public $updated_at;
    public $contact; // Secondary contact number

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . "
                    SET name=:name, email=:email, phone=:phone, password=:password,
                    registration_type=:registration_type, address=:address, city=:city, 
                    customer_type=:customer_type, status=:status, 
                    verification_status=:verification_status, loyalty_points=:loyalty_points,
                    contact=:contact, created_at=NOW(), updated_at=NOW()";

            $stmt = $this->conn->prepare($query);

            // Sanitize inputs
            $this->name = htmlspecialchars(strip_tags($this->name));
            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->phone = htmlspecialchars(strip_tags($this->phone));
            $this->registration_type = htmlspecialchars(strip_tags($this->registration_type));
            $this->address = htmlspecialchars(strip_tags($this->address));
            $this->city = htmlspecialchars(strip_tags($this->city));
            $this->contact = htmlspecialchars(strip_tags($this->contact ?? ''));

            // Hash password
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);

            // Bind parameters
            $stmt->bindParam(":name", $this->name);
            $stmt->bindParam(":email", $this->email);
            $stmt->bindParam(":phone", $this->phone);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->bindParam(":registration_type", $this->registration_type);
            $stmt->bindParam(":address", $this->address);
            $stmt->bindParam(":city", $this->city);
            $stmt->bindParam(":customer_type", $this->customer_type);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":verification_status", $this->verification_status);
            $stmt->bindParam(":loyalty_points", $this->loyalty_points);
            $stmt->bindParam(":contact", $this->contact);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Customer creation error: " . $e->getMessage());
            return false;
        }
    }

    public function readOne() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Set object properties
                $this->name = $row['name'];
                $this->email = $row['email'];
                $this->phone = $row['phone'];
                $this->password = $row['password'];
                $this->registration_type = $row['registration_type'];
                $this->address = $row['address'];
                $this->city = $row['city'];
                $this->street = $row['street'];
                $this->customer_type = $row['customer_type'];
                $this->status = $row['status'];
                $this->verification_status = $row['verification_status'];
                $this->loyalty_points = $row['loyalty_points'];
                $this->last_order_at = $row['last_order_at'];
                $this->created_at = $row['created_at'];
                $this->updated_at = $row['updated_at'];
                
                return $row;
            }
            return false;
        } catch (Exception $e) {
            error_log("Customer read error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find customer by ID
     */
    public function findById($customer_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? AND status = 'active' LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $customer_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (Exception $e) {
            error_log("Customer findById error: " . $e->getMessage());
            return false;
        }
    }

    public function findByPhone($phone) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE phone = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $phone);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->registration_type = $row['registration_type'];
            return $row;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET name=:name, email=:email, phone=:phone, registration_type=:registration_type,
                address=:address, city=:city, contact=:contact, updated_at=NOW()
                WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->registration_type = htmlspecialchars(strip_tags($this->registration_type));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->contact = htmlspecialchars(strip_tags($this->contact ?? ''));

        // Bind parameters
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":registration_type", $this->registration_type);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":contact", $this->contact);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function emailExists() {
        $query = "SELECT id, name, password, phone, registration_type, status, verification_status
                 FROM " . $this->table_name . " 
                 WHERE email = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->phone = $row['phone'];
            $this->password = $row['password'];
            $this->registration_type = $row['registration_type'];
            $this->status = $row['status'];
            $this->verification_status = $row['verification_status'];
            return true;
        }
        
        return false;
    }

    public function phoneExists() {
        $query = "SELECT id, name, email, registration_type, verification_status 
                 FROM " . $this->table_name . " 
                 WHERE phone = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->phone);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->registration_type = $row['registration_type'];
            $this->verification_status = $row['verification_status'];
            return true;
        }
        
        return false;
    }

    public function updateVerification($status) {
        $query = "UPDATE " . $this->table_name . " 
                 SET verification_status = ? 
                 WHERE id = ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $status);
        $stmt->bindParam(2, $this->id);
        
        return $stmt->execute();
    }

    public function updateLastOrder() {
        $query = "UPDATE " . $this->table_name . " 
                 SET last_order_at = NOW() 
                 WHERE id = ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        return $stmt->execute();
    }

    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get customer by identifier (email or phone)
     */
    public function findByIdentifier($identifier) {
        try {
            // Try email first
            $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? AND status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $identifier);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Try phone
            $query = "SELECT * FROM " . $this->table_name . " WHERE phone = ? AND status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $identifier);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error finding customer by identifier: " . $e->getMessage());
            return null;
        }
    }

    /**
     * NEW METHOD: Update customer's preferred city
     */
    public function updatePreferredCity($customer_id, $city_id, $city_name) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET city = :city_name, updated_at = NOW() 
                     WHERE id = :id";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':city_name', $city_name);
            $stmt->bindParam(':id', $customer_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error updating customer preferred city: " . $e->getMessage());
            return false;
        }
    }
}
?>