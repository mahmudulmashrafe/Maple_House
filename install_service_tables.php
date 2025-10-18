<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Create service_purchases table
    $sql1 = "CREATE TABLE IF NOT EXISTS service_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resident_id INT NOT NULL,
        service_name VARCHAR(100) NOT NULL,
        quantity INT NOT NULL,
        price_per_unit DECIMAL(10, 2) NOT NULL,
        total_price DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        purchase_date DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    )";
    
    $db->exec($sql1);
    echo "✓ Table 'service_purchases' created successfully<br>";
    
    // Create resident_service_quotas table
    $sql2 = "CREATE TABLE IF NOT EXISTS resident_service_quotas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resident_id INT NOT NULL,
        service_name VARCHAR(100) NOT NULL,
        month VARCHAR(7) NOT NULL,
        additional_quota INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
        UNIQUE KEY unique_resident_service_month (resident_id, service_name, month)
    )";
    
    $db->exec($sql2);
    echo "✓ Table 'resident_service_quotas' created successfully<br>";
    
    // Create indexes
    $sql3 = "CREATE INDEX IF NOT EXISTS idx_purchase_date ON service_purchases(purchase_date)";
    $db->exec($sql3);
    echo "✓ Index 'idx_purchase_date' created successfully<br>";
    
    $sql4 = "CREATE INDEX IF NOT EXISTS idx_resident_month ON resident_service_quotas(resident_id, month)";
    $db->exec($sql4);
    echo "✓ Index 'idx_resident_month' created successfully<br>";
    
    echo "<br><strong style='color: green;'>All tables and indexes created successfully!</strong><br>";
    echo "<br><a href='admin/service_revenue.php'>Go to Service Revenue Page</a>";
    
} catch (PDOException $e) {
    echo "<strong style='color: red;'>Error:</strong> " . $e->getMessage();
}
?>
