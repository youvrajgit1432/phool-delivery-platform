<?php
// public/expense/init_categories.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();

$pdo = getDBConnection();

// Phool Delivery Expense Categories
$phool_categories = [
    'Delivery & Transportation' => 'Delivery partner payments, Local transportation charges, Courier/parcel service fees, Intercity delivery charges',
    'Vehicle & Bike Maintenance' => 'Regular servicing, Spare parts replacement, Engine oil, Tyre & brake maintenance, Emergency repair costs',
    'Fuel Costs' => 'Petrol or diesel purchases, Fuel for delivery bikes, Generator fuel',
    'Packaging & Production Materials' => 'Flower wrapping papers, Ribbons, stickers, Flower bouquets & basket materials, Boxes and special gift packaging',
    'Office & Operational Expenses' => 'Office rent, Electricity bills, Water bills, Internet and Wi-Fi, Office cleaning, Staff salaries, Office supplies',
    'Software & Digital Tools' => 'Domain/hosting renewal, SMS API cost, Payment gateway fees, Software licensing',
    'Marketing & Branding' => 'Facebook ads, Google ads, Posters & print materials, Discounts, promo codes'
];

// Farm Expense Categories
$farm_categories = [
    'Seeds & Seedlings' => 'Vegetable seeds, Grass seeds, Seedlings for seasonal farming, Hybrid lemon saplings',
    'Fertilizers & Soil Enhancers' => 'Organic fertilizers, Chemical fertilizers (DAP, Urea, Potash), Compost, Soil improvement materials',
    'Pesticides & Crop Protection' => 'Insecticides, Fungicides, Herbicides, Natural pest-control solutions',
    'Animal Feed & Nutrition' => 'Cow feed, Buffalo feed, Mineral supplements, Fodder cultivation expenses',
    'Veterinary & Animal Care' => 'Vaccinations, Vet doctor visits, Medicines, Deworming & health-related supplies',
    'Farm Labor & Operations' => 'Labor wages, Harvesting labor, Farm maintenance workers, Seasonal workers',
    'Tools, Machinery & Equipment' => 'Farm tools (spades, pipes, sprayers), Irrigation equipment, Water pump costs, Machinery servicing and repairs',
    'Transportation & Logistics' => 'Transport of feed or fertilizers, Moving fruits and vegetables to market, Fuel for farm vehicles',
    'Infrastructure & Utilities' => 'Water supply, Electricity bills, Shed maintenance, Land rent, Storage room expenses'
];

try {
    // Insert Phool Delivery Categories
    foreach ($phool_categories as $name => $description) {
        $stmt = $pdo->prepare("INSERT INTO expense_categories (parent_category, name, description) VALUES ('phool_delivery', ?, ?)");
        $stmt->execute([$name, $description]);
    }
    
    // Insert Farm Expense Categories
    foreach ($farm_categories as $name => $description) {
        $stmt = $pdo->prepare("INSERT INTO expense_categories (parent_category, name, description) VALUES ('farm_expenses', ?, ?)");
        $stmt->execute([$name, $description]);
    }
    
    echo "Expense categories initialized successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>