<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo "Access denied. Please login as admin.";
    exit();
}

echo "<h1>Contact Messages Test Page</h1>";
echo "<p>Session user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Session role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<p>✅ Database connection successful</p>";
    
    // Check if contact_messages table exists
    $check_table = $db->query("SHOW TABLES LIKE 'contact_messages'");
    if ($check_table->rowCount() > 0) {
        echo "<p>✅ contact_messages table exists</p>";
        
        // Get message count
        $count_stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages");
        $count = $count_stmt->fetchColumn();
        echo "<p>📧 Total messages in database: " . $count . "</p>";
        
        // Get recent messages
        $messages_stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
        $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($messages)) {
            echo "<h3>Recent Messages:</h3>";
            echo "<ul>";
            foreach ($messages as $msg) {
                echo "<li>" . htmlspecialchars($msg['name']) . " - " . htmlspecialchars($msg['subject']) . " (" . $msg['status'] . ")</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No messages found in database</p>";
        }
        
    } else {
        echo "<p>❌ contact_messages table does not exist</p>";
        echo "<p>Creating table...</p>";
        
        // Create the table
        $create_table_sql = "
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            replied_at TIMESTAMP NULL,
            admin_notes TEXT,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_email (email)
        )";
        
        $db->exec($create_table_sql);
        echo "<p>✅ Table created successfully</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='contact_messages.php'>Go to Contact Messages Page</a></p>";
echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
?>
