<?php
// Include customer model instead of user model
require_once '../app/models/Customer.php';

// Handle customer authentication instead of user authentication
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $database = new Database();
    $db = $database->getConnection();
    $customer = new Customer($db);
    
    if ($_POST['action'] == 'login') {
        $customer->email = $_POST['email'];
        
        if ($customer->emailExists() && password_verify($_POST['password'], $customer->password)) {
            // Return customer data instead of user data
            $response = [
                'status' => 'success',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone
                ]
            ];
        }
    }
}
?>